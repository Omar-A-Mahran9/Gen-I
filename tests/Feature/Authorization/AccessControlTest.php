<?php

namespace Tests\Feature\Authorization;

use App\Exceptions\AdminActionException;
use App\Models\User;
use App\Services\Admin\PrivilegeGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_access_protected_administration_pages(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk();

        foreach (['admin.users.index', 'admin.users.create', 'admin.roles.index', 'admin.roles.create', 'admin.settings.edit', 'admin.audit-logs.index'] as $route) {
            $this->actingAs($staff)->get(route($route))->assertForbidden();
        }
    }

    public function test_sidebar_only_shows_permitted_items(): void
    {
        $this->actingAs($this->userWithRole('staff'))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.users.index'), false)
            ->assertDontSee(route('admin.settings.edit'), false)
            ->assertSee(route('admin.profile.edit'), false);

        $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.users.index'), false)
            ->assertSee(route('admin.roles.index'), false)
            ->assertSee(route('admin.settings.edit'), false)
            ->assertSee(route('admin.audit-logs.index'), false);
    }

    public function test_backend_enforces_permissions_even_without_the_ui(): void
    {
        $manager = $this->userWithRole('manager');
        $target = $this->userWithRole('staff');

        $this->actingAs($manager)->deleteJson(route('admin.users.destroy', $target))->assertForbidden();
        $this->actingAs($manager)->putJson(route('admin.settings.update'), ['app_name' => 'X'])->assertForbidden();
        $this->actingAs($manager)->postJson(route('admin.roles.store'), ['name' => 'x'])->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_assign_the_super_admin_role(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->postJson(route('admin.users.store'), [
            'name' => 'Escalation',
            'email' => 'escalate@geni.test',
            'password' => 'Secret-Pass-123',
            'password_confirmation' => 'Secret-Pass-123',
            'roles' => ['super-admin'],
        ])->assertStatus(422)->assertJson(['message' => __('admin.errors.role_not_assignable')]);

        $this->assertDatabaseMissing('users', ['email' => 'escalate@geni.test']);
    }

    public function test_users_cannot_change_their_own_roles(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->putJson(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles_present' => 1,
            'roles' => ['super-admin'],
        ])->assertOk();

        $this->assertFalse($admin->fresh()->isSuperAdmin());
    }

    public function test_admin_cannot_grant_permissions_they_do_not_hold(): void
    {
        $role = Role::findOrCreate('custom', 'web');
        $this->seedRoles();
        $admin = $this->userWithRole('admin');
        $admin->givePermissionTo('roles.update');

        // admin lacks roles.delete.
        $this->actingAs($admin)->putJson(route('admin.roles.update', $role), [
            'name' => 'custom',
            'permissions' => ['roles.delete'],
        ])->assertStatus(422)->assertJson(['message' => __('admin.errors.permission_not_grantable')]);

        $this->assertFalse($role->fresh()->hasPermissionTo('roles.delete'));
    }

    public function test_non_super_admin_cannot_manage_super_admins(): void
    {
        $admin = $this->userWithRole('admin');
        $root = $this->superAdmin();

        $this->actingAs($admin)->get(route('admin.users.edit', $root))->assertForbidden();
        $this->actingAs($admin)->patchJson(route('admin.users.status', $root))->assertForbidden();
        $this->actingAs($admin)->putJson(route('admin.users.password', $root), ['password' => 'Another-Pass-123', 'password_confirmation' => 'Another-Pass-123'])->assertForbidden();
        $this->assertTrue($root->fresh()->is_active);
    }

    public function test_protected_super_admin_role_cannot_be_edited_or_deleted(): void
    {
        $root = $this->superAdmin();
        $role = Role::findByName('super-admin', 'web');

        $this->actingAs($root)->get(route('admin.roles.edit', $role))->assertForbidden();
        $this->actingAs($root)->putJson(route('admin.roles.update', $role), ['name' => 'renamed', 'permissions' => []])->assertForbidden();
        $this->actingAs($root)->deleteJson(route('admin.roles.destroy', $role))->assertForbidden();
        $this->assertDatabaseHas('roles', ['name' => 'super-admin']);
    }

    public function test_the_last_active_super_admin_cannot_be_deactivated_deleted_or_demoted(): void
    {
        $root = $this->superAdmin();
        $guard = app(PrivilegeGuard::class);

        // Another super-admin account that is itself inactive is not counted as active.
        $inactiveRoot = $this->superAdmin(['is_active' => false]);
        $this->assertTrue($guard->isLastActiveSuperAdmin($root));

        foreach ([
            fn () => $guard->assertCanDeactivate($inactiveRoot, $root),
            fn () => $guard->assertCanDelete($inactiveRoot, $root),
            fn () => $guard->assertCanAssignRoles($inactiveRoot, $root, ['admin']),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected the last super administrator to be protected.');
            } catch (AdminActionException $e) {
                $this->assertSame(__('admin.errors.last_super_admin'), $e->getMessage());
            }
        }
    }

    public function test_super_admin_cannot_deactivate_or_delete_themselves(): void
    {
        $root = $this->superAdmin();
        $this->superAdmin(); // a second active super admin

        $this->actingAs($root)->patchJson(route('admin.users.status', $root))->assertForbidden();
        $this->actingAs($root)->deleteJson(route('admin.users.destroy', $root))->assertForbidden();
        $this->assertTrue($root->fresh()->is_active);
    }

    public function test_removing_a_role_requires_authority_to_assign_it(): void
    {
        $admin = $this->userWithRole('admin');
        $target = User::factory()->create();
        $target->assignRole(Role::findOrCreate('auditor-plus', 'web')->givePermissionTo('roles.delete'));

        // The admin cannot see "auditor-plus" in the form; saving keeps it instead of silently removing it.
        $this->actingAs($admin)->putJson(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'roles_present' => 1,
            'roles' => ['staff'],
        ])->assertOk();

        $this->assertEqualsCanonicalizing(['auditor-plus', 'staff'], $target->fresh()->getRoleNames()->all());
    }
}
