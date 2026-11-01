<?php

namespace Tests\Feature\Business;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsBusinessData;
use Tests\TestCase;

class ScopeAndRegressionTest extends TestCase
{
    use BuildsBusinessData, RefreshDatabase;

    public function test_business_permissions_are_seeded_for_super_admin_only(): void
    {
        $this->seedRoles();

        $this->assertTrue(Role::findByName('super-admin')->hasPermissionTo('inventory.adjust'));
        foreach (['admin', 'manager', 'staff'] as $role) {
            $this->assertFalse(Role::findByName($role)->hasPermissionTo('inventory.adjust'), "{$role} must not receive business permissions by default.");
            $this->assertFalse(Role::findByName($role)->hasPermissionTo('pricing.manage'));
        }
    }

    public function test_business_menu_is_permission_aware(): void
    {
        $org = Organization::factory()->create();
        $catalogOnly = $this->staffOf($org, ['catalog.view']);

        $this->actingAs($catalogOnly)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.products.index'), false)
            ->assertDontSee(route('admin.branches.index'), false)
            ->assertDontSee(route('admin.price-lists.index'), false);

        $this->actingAs($catalogOnly)->get(route('admin.branches.index'))->assertForbidden();
    }

    public function test_users_without_an_organization_see_no_business_records(): void
    {
        $this->seedRoles();
        $user = User::factory()->create();
        $role = Role::create(['name' => 'unscoped', 'guard_name' => 'web'])->givePermissionTo(['dashboard.view', 'branches.view', 'branches.create']);
        $user->assignRole($role);
        Branch::factory()->count(2)->create();

        $this->actingAs($user)->getJson(route('admin.branches.index', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertJsonPath('recordsTotal', 0);
        $this->actingAs($user)->postJson(route('admin.branches.store'), ['code' => 'X', 'name' => 'X', 'address_text' => 'X', 'opening_hours' => ['sat' => []]])->assertForbidden();
    }

    public function test_super_admin_assigns_organizations_and_org_admins_stay_in_scope(): void
    {
        $root = $this->superAdmin();
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $this->actingAs($root)->postJson(route('admin.users.store'), [
            'name' => 'Org A Admin', 'email' => 'a-admin@geni.test', 'password' => 'Strong-Pass-123', 'password_confirmation' => 'Strong-Pass-123',
            'organization_id' => $orgA->id, 'roles' => ['admin'],
        ])->assertOk();
        $orgAdmin = User::query()->where('email', 'a-admin@geni.test')->firstOrFail();
        $this->assertSame($orgA->id, $orgAdmin->organization_id);

        // Switching actors within one test needs a fresh session (auth.session pins the session to one user's password hash).
        $this->flushSession();

        // Org admin creates a user: it lands in their organization; they cannot choose another one.
        $this->actingAs($orgAdmin)->postJson(route('admin.users.store'), [
            'name' => 'Clerk', 'email' => 'clerk@geni.test', 'password' => 'Strong-Pass-123', 'password_confirmation' => 'Strong-Pass-123', 'organization_id' => $orgB->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['organization_id']);
        $this->actingAs($orgAdmin)->postJson(route('admin.users.store'), [
            'name' => 'Clerk', 'email' => 'clerk@geni.test', 'password' => 'Strong-Pass-123', 'password_confirmation' => 'Strong-Pass-123',
        ])->assertOk();
        $this->assertSame($orgA->id, User::query()->where('email', 'clerk@geni.test')->value('organization_id'));

        // Users of other organizations are invisible to the org admin.
        $outsider = User::factory()->create();
        $outsider->forceFill(['organization_id' => $orgB->id])->save();
        $this->actingAs($orgAdmin)->get(route('admin.users.show', $outsider))->assertForbidden();
        $this->actingAs($orgAdmin)->getJson(route('admin.users.index', ['draw' => 1, 'start' => 0, 'length' => 50]))
            ->assertOk()->assertJsonPath('recordsTotal', 2);
    }

    /**
     * Regression for "dashboard shows admin@example.com": the account card must always show
     * the authenticated user's own stored email (no hard-coded or stale value).
     */
    public function test_dashboard_account_card_shows_the_authenticated_users_email(): void
    {
        $first = $this->superAdmin(['email' => 'first.owner@geni.test', 'name' => 'First Owner']);
        $second = $this->superAdmin(['email' => 'second.owner@geni.test', 'name' => 'Second Owner']);

        $this->actingAs($first)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('first.owner@geni.test')->assertDontSee('second.owner@geni.test')->assertDontSee('admin@example.com');

        $this->flushSession();
        $this->actingAs($second)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('second.owner@geni.test')->assertDontSee('first.owner@geni.test');
    }
}
