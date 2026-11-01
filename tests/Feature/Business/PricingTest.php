<?php

namespace Tests\Feature\Business;

use App\Models\Company;
use App\Models\Organization;
use App\Models\PolicyVersion;
use App\Models\Price;
use App\Models\PriceList;
use App\Models\ProductVariant;
use App\Services\Business\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBusinessData;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use BuildsBusinessData, RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
    }

    public function test_policy_versions_require_independent_approval_and_become_immutable(): void
    {
        $author = $this->staffOf($this->org, ['policies.view', 'policies.manage', 'policies.approve']);
        $approver = $this->staffOf($this->org, ['policies.view', 'policies.approve']);

        $this->actingAs($author)->postJson(route('admin.policies.store'), ['policy_code' => 'Tax.VAT', 'rule_body' => '{"rate": "14.00"}', 'effective_from' => now()->toDateTimeString()])->assertOk();
        $this->actingAs($author)->postJson(route('admin.policies.store'), ['policy_code' => 'tax.vat', 'rule_body' => ['rate' => '15.00'], 'effective_from' => now()->toDateTimeString()])->assertOk();
        $this->assertSame([1, 2], PolicyVersion::query()->where('policy_code', 'tax.vat')->orderBy('version')->pluck('version')->all());

        $this->actingAs($author)->postJson(route('admin.policies.store'), ['policy_code' => 'x', 'rule_body' => '[1,2]', 'effective_from' => now()->toDateTimeString()])
            ->assertStatus(422)->assertJsonValidationErrors(['rule_body']);

        $policy = PolicyVersion::query()->where('version', 1)->firstOrFail();
        $this->actingAs($author)->postJson(route('admin.policies.approve', $policy))->assertStatus(422)->assertJsonPath('message', __('admin.errors.self_approval'));
        $this->actingAs($approver)->postJson(route('admin.policies.approve', $policy))->assertOk();
        $this->assertSame('approved', $policy->fresh()->status->value);
        $this->assertSame($approver->id, $policy->fresh()->approved_by);

        $this->actingAs($author)->putJson(route('admin.policies.update', $policy), ['rule_body' => ['rate' => '0'], 'effective_from' => now()->toDateTimeString()])->assertForbidden();
        $this->actingAs($approver)->postJson(route('admin.policies.retire', $policy))->assertOk();
        $this->assertSame('retired', $policy->fresh()->status->value);
        $this->assertDatabaseHas('audit_logs', ['event' => 'policy.approved', 'auditable_id' => $policy->id]);
    }

    public function test_prices_need_an_approved_tax_policy_and_are_never_overwritten(): void
    {
        $manager = $this->staffOf($this->org, ['pricing.view', 'pricing.manage']);
        $list = PriceList::factory()->create(['organization_id' => $this->org->id]);
        $variant = ProductVariant::factory()->create(['organization_id' => $this->org->id]);
        $draftPolicy = PolicyVersion::query()->forceCreate(['organization_id' => $this->org->id, 'policy_code' => 'tax.draft', 'version' => 1, 'rule_body' => ['a' => 1], 'status' => 'draft', 'effective_from' => now()]);
        $tax = $this->approvedPolicy($this->org);

        $this->actingAs($manager)->postJson(route('admin.price-lists.prices.store', $list), ['variant_id' => $variant->id, 'unit_price' => '100', 'tax_policy_id' => $draftPolicy->id])
            ->assertStatus(422)->assertJsonValidationErrors(['tax_policy_id']);
        $this->actingAs($manager)->postJson(route('admin.price-lists.prices.store', $list), ['variant_id' => $variant->id, 'unit_price' => '-1', 'min_quantity' => 0, 'tax_policy_id' => $tax->id])
            ->assertStatus(422)->assertJsonValidationErrors(['unit_price', 'min_quantity']);
        $this->actingAs($manager)->postJson(route('admin.price-lists.prices.store', $list), ['variant_id' => $variant->id, 'unit_price' => '100.123', 'tax_policy_id' => $tax->id])
            ->assertStatus(422)->assertJsonValidationErrors(['unit_price']);

        // Existing current price (started yesterday, open-ended).
        $old = Price::query()->create(['organization_id' => $this->org->id, 'price_list_id' => $list->id, 'variant_id' => $variant->id, 'min_quantity' => 1, 'unit_price' => '50000.00', 'tax_policy_id' => $tax->id, 'valid_from' => now()->subDay()]);

        $this->actingAs($manager)->postJson(route('admin.price-lists.prices.store', $list), ['variant_id' => $variant->id, 'unit_price' => '47999.99', 'tax_policy_id' => $tax->id])->assertOk();

        $old->refresh();
        $this->assertNotNull($old->valid_to, 'The previous price must be closed, not overwritten.');
        $this->assertSame('50000.00', $old->unit_price);
        $this->assertSame(2, Price::query()->count());
        $current = app(PricingService::class)->currentPrice($list, $variant);
        $this->assertSame('47999.99', $current->unit_price);
        $this->assertDatabaseHas('audit_logs', ['event' => 'price.created']);

        // Effective prices are immutable history.
        $this->actingAs($manager)->putJson(route('admin.prices.update', $current), ['unit_price' => '1'])
            ->assertStatus(422)->assertJsonPath('message', __('admin.errors.price_history_immutable'));
        $this->actingAs($manager)->deleteJson(route('admin.prices.destroy', $old))->assertStatus(422);
    }

    public function test_overlapping_bounded_prices_are_rejected_and_future_prices_are_editable(): void
    {
        $manager = $this->staffOf($this->org, ['pricing.view', 'pricing.manage']);
        $list = PriceList::factory()->create(['organization_id' => $this->org->id]);
        $variant = ProductVariant::factory()->create(['organization_id' => $this->org->id]);
        $tax = $this->approvedPolicy($this->org);
        $from = now()->addDays(10)->startOfDay();

        $this->actingAs($manager)->postJson(route('admin.price-lists.prices.store', $list), ['variant_id' => $variant->id, 'unit_price' => '900', 'tax_policy_id' => $tax->id, 'valid_from' => $from->toDateTimeString(), 'valid_to' => $from->copy()->addDays(5)->toDateTimeString()])->assertOk();
        $this->actingAs($manager)->postJson(route('admin.price-lists.prices.store', $list), ['variant_id' => $variant->id, 'unit_price' => '850', 'tax_policy_id' => $tax->id, 'valid_from' => $from->copy()->addDays(2)->toDateTimeString(), 'valid_to' => $from->copy()->addDays(3)->toDateTimeString()])
            ->assertStatus(422)->assertJsonPath('message', __('admin.errors.price_overlap'));
        // A different quantity break may overlap.
        $this->actingAs($manager)->postJson(route('admin.price-lists.prices.store', $list), ['variant_id' => $variant->id, 'min_quantity' => 10, 'unit_price' => '800', 'tax_policy_id' => $tax->id, 'valid_from' => $from->toDateTimeString()])->assertOk();

        $future = Price::query()->where('unit_price', '900')->firstOrFail();
        $this->actingAs($manager)->putJson(route('admin.prices.update', $future), ['unit_price' => '880.50'])->assertOk();
        $this->assertSame('880.50', $future->fresh()->unit_price);
        $this->actingAs($manager)->deleteJson(route('admin.prices.destroy', $future))->assertOk();

        // Past start dates are refused for new prices.
        $this->actingAs($manager)->postJson(route('admin.price-lists.prices.store', $list), ['variant_id' => $variant->id, 'unit_price' => '1', 'tax_policy_id' => $tax->id, 'valid_from' => now()->subDays(3)->toDateTimeString()])
            ->assertStatus(422)->assertJsonValidationErrors(['valid_from']);
    }

    public function test_quantity_breaks_resolve_the_best_matching_price(): void
    {
        $list = PriceList::factory()->create(['organization_id' => $this->org->id]);
        $variant = ProductVariant::factory()->create(['organization_id' => $this->org->id]);
        $tax = $this->approvedPolicy($this->org);
        foreach ([[1, '100.00'], [10, '90.00'], [50, '80.00']] as [$qty, $price]) {
            Price::query()->create(['organization_id' => $this->org->id, 'price_list_id' => $list->id, 'variant_id' => $variant->id, 'min_quantity' => $qty, 'unit_price' => $price, 'tax_policy_id' => $tax->id, 'valid_from' => now()->subHour()]);
        }

        $service = app(PricingService::class);
        $this->assertSame('100.00', $service->currentPrice($list, $variant, 1)->unit_price);
        $this->assertSame('90.00', $service->currentPrice($list, $variant, 49)->unit_price);
        $this->assertSame('80.00', $service->currentPrice($list, $variant, 50)->unit_price);
    }

    public function test_price_list_rules_currency_lock_and_contract_scope(): void
    {
        $manager = $this->staffOf($this->org, ['pricing.view', 'pricing.manage']);
        $this->actingAs($manager)->postJson(route('admin.price-lists.store'), ['code' => 'retail', 'currency' => 'egp'])->assertOk();
        $list = PriceList::query()->where('code', 'RETAIL')->firstOrFail();

        $this->actingAs($manager)->postJson(route('admin.price-lists.store'), ['code' => 'RETAIL', 'currency' => 'EG'])->assertStatus(422)->assertJsonValidationErrors(['code', 'currency']);
        $this->actingAs($manager)->postJson(route('admin.price-lists.store'), ['code' => 'B2B', 'currency' => 'EGP', 'company_id' => Company::factory()->create(['organization_id' => $this->org->id])->id])
            ->assertStatus(422)->assertJsonValidationErrors(['company_id']);

        Price::query()->create(['organization_id' => $this->org->id, 'price_list_id' => $list->id, 'variant_id' => ProductVariant::factory()->create(['organization_id' => $this->org->id])->id, 'min_quantity' => 1, 'unit_price' => '1.00', 'tax_policy_id' => $this->approvedPolicy($this->org)->id, 'valid_from' => now()]);
        $this->actingAs($manager)->putJson(route('admin.price-lists.update', $list), ['code' => 'RETAIL', 'currency' => 'USD'])->assertStatus(422)->assertJsonPath('message', __('admin.errors.currency_locked'));
        $this->actingAs($manager)->deleteJson(route('admin.price-lists.destroy', $list))->assertStatus(422);

        $this->actingAs($manager)->get(route('admin.price-lists.show', $list))->assertOk()->assertSee('RETAIL');
        $this->actingAs($manager)->getJson(route('admin.price-lists.show', [$list, 'draw' => 1, 'start' => 0, 'length' => 10]))->assertOk()->assertJsonPath('recordsTotal', 1);
    }

    public function test_pricing_api_and_permissions(): void
    {
        $list = PriceList::factory()->create(['organization_id' => $this->org->id, 'currency' => 'EGP']);
        $variant = ProductVariant::factory()->create(['organization_id' => $this->org->id]);
        $tax = $this->approvedPolicy($this->org);

        $this->apiAs($this->staffOf($this->org, ['pricing.view']));
        $this->postJson("/api/v1/price-lists/{$list->id}/prices", ['variant_id' => $variant->id, 'unit_price' => '10', 'tax_policy_id' => $tax->id])->assertForbidden();

        $this->apiAs($this->staffOf($this->org, ['pricing.view', 'pricing.manage']));
        $this->postJson("/api/v1/price-lists/{$list->id}/prices", ['variant_id' => $variant->id, 'unit_price' => '1999.50', 'tax_policy_id' => $tax->id])
            ->assertCreated()
            ->assertJsonPath('data.unit_price.amount', '1999.50')
            ->assertJsonPath('data.unit_price.currency', 'EGP')
            ->assertJsonPath('data.state', 'current');
        $this->getJson("/api/v1/price-lists/{$list->id}/prices?filter[period]=current")->assertOk()->assertJsonPath('meta.total', 1);

        $foreignList = PriceList::factory()->create();
        $this->getJson("/api/v1/price-lists/{$foreignList->id}")->assertNotFound();
        $foreignVariant = ProductVariant::factory()->create();
        $this->postJson("/api/v1/price-lists/{$list->id}/prices", ['variant_id' => $foreignVariant->id, 'unit_price' => '1', 'tax_policy_id' => $tax->id])
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
    }
}
