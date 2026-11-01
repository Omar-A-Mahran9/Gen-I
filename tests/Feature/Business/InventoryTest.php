<?php

namespace Tests\Feature\Business;

use App\Enums\MovementReason;
use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Organization;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Business\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\BuildsBusinessData;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use BuildsBusinessData, RefreshDatabase;

    protected Organization $org;

    protected Branch $branch;

    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->branch = Branch::factory()->create(['organization_id' => $this->org->id]);
        $this->variant = ProductVariant::factory()->create(['organization_id' => $this->org->id]);
    }

    protected function adjuster(): User
    {
        return $this->staffOf($this->org, ['inventory.view', 'inventory.adjust']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(int $quantity, string $reason = 'receipt', ?string $note = null): array
    {
        return ['branch_id' => $this->branch->id, 'variant_id' => $this->variant->id, 'quantity' => $quantity, 'reason' => $reason, 'note' => $note];
    }

    public function test_receipts_and_adjustments_update_balance_and_ledger(): void
    {
        $user = $this->adjuster();

        $this->actingAs($user)->postJson(route('admin.inventory.adjust'), $this->payload(10))->assertOk()->assertJsonPath('on_hand', 10);
        $this->actingAs($user)->postJson(route('admin.inventory.adjust'), $this->payload(-3, 'adjustment', 'Damaged in display'))->assertOk()->assertJsonPath('on_hand', 7);

        $balance = InventoryBalance::query()->firstOrFail();
        $this->assertSame(7, $balance->on_hand);
        $this->assertSame(2, $balance->version);
        $this->assertSame([10, -3], InventoryMovement::query()->orderBy('id')->pluck('quantity_delta')->all());
        $this->assertSame([10, 7], InventoryMovement::query()->orderBy('id')->pluck('on_hand_after')->all());
        $this->assertSame($user->id, InventoryMovement::query()->latest('id')->value('created_by'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'inventory.adjusted', 'user_id' => $user->id]);
    }

    public function test_invalid_stock_changes_are_rejected(): void
    {
        $user = $this->adjuster();
        $this->actingAs($user)->postJson(route('admin.inventory.adjust'), $this->payload(2))->assertOk();

        $this->actingAs($user)->postJson(route('admin.inventory.adjust'), $this->payload(-5, 'adjustment', 'count'))
            ->assertStatus(422)->assertJsonPath('message', __('admin.errors.insufficient_stock', ['available' => 2]));
        $this->actingAs($user)->postJson(route('admin.inventory.adjust'), $this->payload(-1, 'receipt'))->assertStatus(422);
        $this->actingAs($user)->postJson(route('admin.inventory.adjust'), $this->payload(0))->assertStatus(422)->assertJsonValidationErrors(['quantity']);
        $this->actingAs($user)->postJson(route('admin.inventory.adjust'), $this->payload(1, 'sale'))->assertStatus(422)->assertJsonValidationErrors(['reason']);
        $this->actingAs($user)->postJson(route('admin.inventory.adjust'), $this->payload(-1, 'adjustment'))->assertStatus(422)->assertJsonValidationErrors(['note']);

        $this->assertSame(2, InventoryBalance::query()->value('on_hand'));
        $this->assertSame(1, InventoryMovement::query()->count());
    }

    public function test_reserved_stock_cannot_be_adjusted_away(): void
    {
        $user = $this->adjuster();
        app(InventoryService::class)->adjust($this->branch, $this->variant, 5, MovementReason::Receipt, null, $user);
        InventoryBalance::query()->update(['reserved' => 4]);

        $this->actingAs($user)->postJson(route('admin.inventory.adjust'), $this->payload(-2, 'adjustment', 'count'))
            ->assertStatus(422)->assertJsonPath('message', __('admin.errors.below_reserved', ['reserved' => 4]));
    }

    public function test_inactive_branch_retired_variant_and_cross_organization_are_rejected(): void
    {
        $user = $this->adjuster();

        $this->branch->forceFill(['status' => 'inactive'])->save();
        $this->actingAs($user)->postJson(route('admin.inventory.adjust'), $this->payload(1))->assertStatus(422)->assertJsonPath('message', __('admin.errors.branch_inactive'));
        $this->branch->forceFill(['status' => 'active'])->save();

        $this->variant->forceFill(['status' => 'retired'])->save();
        $this->actingAs($user)->postJson(route('admin.inventory.adjust'), $this->payload(1))->assertStatus(422)->assertJsonPath('message', __('admin.errors.variant_retired'));

        $foreignBranch = Branch::factory()->create();
        $foreignVariant = ProductVariant::factory()->create();
        $this->actingAs($user)->postJson(route('admin.inventory.adjust'), ['branch_id' => $foreignBranch->id, 'variant_id' => $this->variant->id, 'quantity' => 1, 'reason' => 'receipt'])->assertNotFound();
        $this->actingAs($user)->postJson(route('admin.inventory.adjust'), ['branch_id' => $this->branch->id, 'variant_id' => $foreignVariant->id, 'quantity' => 1, 'reason' => 'receipt'])->assertNotFound();
    }

    public function test_adjusting_requires_permission(): void
    {
        $viewer = $this->staffOf($this->org, ['inventory.view']);

        $this->actingAs($viewer)->get(route('admin.inventory.index'))->assertOk()->assertDontSee('id="geni_adjust_modal"', false);
        $this->actingAs($viewer)->postJson(route('admin.inventory.adjust'), $this->payload(5))->assertForbidden();
        $this->assertSame(0, InventoryBalance::query()->count());
    }

    public function test_idempotent_api_adjustments(): void
    {
        $this->apiAs($this->adjuster());
        $headers = ['Idempotency-Key' => 'stock-req-0001'];

        $first = $this->postJson('/api/v1/inventory/adjustments', $this->payload(5), $headers)->assertCreated()->assertJsonPath('data.on_hand_after', 5);
        $replay = $this->postJson('/api/v1/inventory/adjustments', $this->payload(5), $headers)->assertOk();
        $this->assertSame($first->json('data.id'), $replay->json('data.id'));
        $this->assertSame(5, InventoryBalance::query()->value('on_hand'), 'A replay must not change stock twice.');

        $this->postJson('/api/v1/inventory/adjustments', $this->payload(7), $headers)->assertStatus(409)->assertJsonPath('error.code', 'conflict');
        $this->postJson('/api/v1/inventory/adjustments', $this->payload(1), ['Idempotency-Key' => 'bad key!'])->assertStatus(400);

        $this->getJson('/api/v1/inventory?filter[branch_id]='.$this->branch->id)->assertOk()->assertJsonPath('data.0.available', 5);
        $this->getJson('/api/v1/inventory/movements?filter[reason]=receipt')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/branches/{$this->branch->id}/inventory")->assertForbidden();
    }

    public function test_movements_are_immutable(): void
    {
        $movement = app(InventoryService::class)->adjust($this->branch, $this->variant, 3, MovementReason::Receipt, null, $this->adjuster());

        $this->expectException(LogicException::class);
        $movement->forceFill(['quantity_delta' => 300])->save();
    }

    public function test_sequential_changes_keep_balance_consistent_with_the_ledger(): void
    {
        $user = $this->adjuster();
        $service = app(InventoryService::class);

        foreach ([20, -5, 7, -12, 3] as $delta) {
            $service->adjust($this->branch, $this->variant, $delta, $delta > 0 ? MovementReason::Receipt : MovementReason::Adjustment, 'batch', $user);
        }

        $balance = InventoryBalance::query()->firstOrFail();
        $this->assertSame((int) InventoryMovement::query()->sum('quantity_delta'), $balance->on_hand);
        $this->assertSame(13, $balance->on_hand);
        $this->assertSame(5, $balance->version);
    }

    public function test_database_rejects_negative_stock(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('CHECK constraints are added on MySQL only (SQLite cannot ALTER TABLE ADD CONSTRAINT); run composer test:mysql.');
        }

        app(InventoryService::class)->adjust($this->branch, $this->variant, 1, MovementReason::Receipt, null, $this->adjuster());

        $this->expectException(QueryException::class);
        DB::table('inventory_balances')->update(['on_hand' => -1]);
    }

    public function test_inventory_pages_render(): void
    {
        $user = $this->adjuster();
        app(InventoryService::class)->adjust($this->branch, $this->variant, 4, MovementReason::Receipt, 'opening count', $user);

        $this->actingAs($user)->get(route('admin.inventory.index'))->assertOk()->assertSee('id="geni_adjust_modal"', false);
        $this->actingAs($user)->getJson(route('admin.inventory.index', ['draw' => 1, 'start' => 0, 'length' => 10, 'filters' => ['stock' => 'available']]))->assertJsonPath('recordsFiltered', 1);
        $this->actingAs($user)->get(route('admin.inventory.movements'))->assertOk();
        $this->actingAs($user)->getJson(route('admin.inventory.movements', ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => 'opening']]))->assertJsonPath('recordsFiltered', 1);
    }
}
