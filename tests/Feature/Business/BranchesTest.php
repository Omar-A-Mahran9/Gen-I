<?php

namespace Tests\Feature\Business;

use App\Enums\MovementReason;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\ProductVariant;
use App\Services\Business\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBusinessData;
use Tests\TestCase;

class BranchesTest extends TestCase
{
    use BuildsBusinessData, RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'code' => 'cai-01',
            'name' => 'Cairo Festival City',
            'address_text' => 'Ring Road, New Cairo',
            'city' => 'Cairo',
            'phone' => '+20 2 0000 0000',
            'latitude' => '30.0287',
            'longitude' => '31.4086',
            'opening_hours' => [
                'sat' => [['open' => '10:00', 'close' => '22:00']],
                'fri' => [['open' => '13:00', 'close' => '16:00'], ['open' => '17:00', 'close' => '23:00']],
            ],
        ], $overrides);
    }

    public function test_branch_can_be_created_with_hours_and_location(): void
    {
        $org = Organization::factory()->create();
        $staff = $this->staffOf($org, ['branches.view', 'branches.create', 'branches.update']);

        $this->actingAs($staff)->postJson(route('admin.branches.store'), $this->payload())->assertOk();

        $branch = Branch::query()->firstOrFail();
        $this->assertSame('CAI-01', $branch->code);
        $this->assertSame($org->id, $branch->organization_id);
        $this->assertSame([['open' => '13:00', 'close' => '16:00'], ['open' => '17:00', 'close' => '23:00']], $branch->opening_hours['fri']);
        $this->assertSame([], $branch->opening_hours['sun']);
        $this->assertStringContainsString('30.0287', (string) $branch->directionsUrl());
        $this->assertDatabaseHas('audit_logs', ['event' => 'branch.created', 'auditable_id' => $branch->id]);

        $this->actingAs($staff)->get(route('admin.branches.show', $branch))->assertOk()->assertSee('CAI-01')->assertSee('13:00');
        $this->actingAs($staff)->get(route('admin.branches.edit', $branch))->assertOk();
    }

    public function test_branch_validation(): void
    {
        $org = Organization::factory()->create();
        Branch::factory()->create(['organization_id' => $org->id, 'code' => 'DUP']);
        $staff = $this->staffOf($org, ['branches.view', 'branches.create']);

        $this->actingAs($staff)->postJson(route('admin.branches.store'), $this->payload([
            'code' => 'dup',
            'latitude' => '120',
            'opening_hours' => ['sat' => [['open' => '22:00', 'close' => '10:00']], 'xyz' => [['open' => '10:00', 'close' => '11:00']]],
        ]))->assertStatus(422)->assertJsonValidationErrors(['code', 'latitude', 'opening_hours.sat.0.close', 'opening_hours']);

        $this->actingAs($staff)->postJson(route('admin.branches.store'), $this->payload(['code' => 'bad code!', 'address_text' => '']))
            ->assertStatus(422)->assertJsonValidationErrors(['code', 'address_text']);
    }

    public function test_branch_codes_are_unique_per_organization_only(): void
    {
        Branch::factory()->create(['code' => 'CAI-01']);
        $staff = $this->staffOf(Organization::factory()->create(), ['branches.view', 'branches.create']);

        $this->actingAs($staff)->postJson(route('admin.branches.store'), $this->payload())->assertOk();
    }

    public function test_status_toggle_and_delete_protection(): void
    {
        $org = Organization::factory()->create();
        $staff = $this->staffOf($org, ['branches.view', 'branches.update', 'branches.delete']);
        $branch = Branch::factory()->create(['organization_id' => $org->id]);
        $withStock = Branch::factory()->create(['organization_id' => $org->id]);
        $variant = ProductVariant::factory()->create(['organization_id' => $org->id]);
        app(InventoryService::class)->adjust($withStock, $variant, 3, MovementReason::Receipt, null, $staff);

        $this->actingAs($staff)->patchJson(route('admin.branches.status', $branch).'?status=inactive')->assertOk();
        $this->assertSame('inactive', $branch->fresh()->status->value);

        $this->actingAs($staff)->deleteJson(route('admin.branches.destroy', $withStock))
            ->assertStatus(422)->assertJsonPath('message', __('admin.errors.branch_has_inventory'));
        $this->actingAs($staff)->deleteJson(route('admin.branches.destroy', $branch))->assertOk();
        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    public function test_branch_datatable_search_filter_and_pagination(): void
    {
        $org = Organization::factory()->create();
        $staff = $this->staffOf($org, ['branches.view']);
        Branch::factory()->count(12)->create(['organization_id' => $org->id, 'city' => 'Giza']);
        Branch::factory()->create(['organization_id' => $org->id, 'city' => 'Alexandria', 'name' => 'Qzx Unique Store']);
        Branch::factory()->count(3)->create(); // other organizations

        $base = ['draw' => 1, 'start' => 0, 'length' => 5, 'columns' => [['data' => 'code']], 'order' => [['column' => 0, 'dir' => 'asc']]];

        $this->actingAs($staff)->getJson(route('admin.branches.index', $base))
            ->assertOk()->assertJsonPath('recordsTotal', 13)->assertJsonCount(5, 'data');
        $this->actingAs($staff)->getJson(route('admin.branches.index', $base + ['search' => ['value' => 'Qzx Unique']]))
            ->assertJsonPath('recordsFiltered', 1);
        $this->actingAs($staff)->getJson(route('admin.branches.index', $base + ['filters' => ['city' => 'Giza']]))
            ->assertJsonPath('recordsFiltered', 12);
    }

    public function test_branch_api_and_isolation(): void
    {
        $org = Organization::factory()->create();
        $foreign = Branch::factory()->create();
        $this->apiAs($this->staffOf($org, ['branches.view', 'branches.create', 'branches.update', 'inventory.view']));

        $id = $this->postJson('/api/v1/branches', $this->payload())->assertCreated()
            ->assertJsonPath('data.code', 'CAI-01')
            ->assertJsonPath('data.location.latitude', 30.0287)
            ->assertJsonPath('data.opening_hours.sun', [])
            ->json('data.id');

        $this->getJson('/api/v1/branches?filter[status]=active&sort=-code')->assertOk()->assertJsonPath('meta.total', 1);
        $this->patchJson("/api/v1/branches/{$id}/status", ['status' => 'closed'])->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->getJson("/api/v1/branches/{$id}/inventory")->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson("/api/v1/branches/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/branches/{$foreign->id}", $this->payload())->assertNotFound();
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/v1/branches')->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
    }
}
