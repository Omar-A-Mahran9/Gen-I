<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->superAdmin();
    }

    public function test_roles_pages_render(): void
    {
        $this->actingAs($this->admin)->get(route('admin.roles.index'))->assertOk()->assertSee(__('admin.roles.names.manager'));
        $this->actingAs($this->admin)->get(route('admin.roles.create'))->assertOk()->assertSee('data-geni-check-group', false);
        $this->actingAs($this->admin)->get(route('admin.roles.show', Role::findByName('manager')))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.roles.edit', Role::findByName('manager')))->assertOk()->assertSee('users.view');
    }

    public function test_admin_can_create_a_role_with_permissions(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.roles.store'), [
            'name' => 'Support Lead',
            'permissions' => ['dashboard.view', 'users.view'],
        ])->assertOk();

        $role = Role::findByName('Support Lead');
        $this->assertEqualsCanonicalizing(['dashboard.view', 'users.view'], $role->permissions->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', ['event' => 'role.created', 'auditable_id' => $role->id]);
    }

    public function test_role_validation(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.roles.store'), [
            'name' => 'manager',
            'permissions' => ['not.a.permission'],
        ])->assertStatus(422)->assertJsonValidationErrors(['name', 'permissions.0']);

        $this->actingAs($this->admin)->postJson(route('admin.roles.store'), ['name' => '<b>bad</b>'])
            ->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $role = Role::findByName('staff');

        $this->actingAs($this->admin)->putJson(route('admin.roles.update', $role), [
            'name' => 'staff',
            'permissions' => ['dashboard.view', 'audit.view'],
        ])->assertOk();

        $this->assertTrue($role->fresh()->hasPermissionTo('audit.view'));
        $log = AuditLog::query()->where('event', 'role.updated')->latest('id')->first();
        $this->assertSame(['audit.view'], $log->properties['permissions_added']);
    }

    public function test_role_in_use_cannot_be_deleted_but_unused_role_can(): void
    {
        $used = Role::findByName('manager');
        User::factory()->create()->assignRole($used);

        $this->actingAs($this->admin)->deleteJson(route('admin.roles.destroy', $used))
            ->assertStatus(422)->assertJson(['message' => __('admin.errors.role_in_use')]);
        $this->assertDatabaseHas('roles', ['name' => 'manager']);

        $unused = Role::create(['name' => 'temporary', 'guard_name' => 'web']);
        $this->actingAs($this->admin)->deleteJson(route('admin.roles.destroy', $unused))->assertOk();
        $this->assertDatabaseMissing('roles', ['name' => 'temporary']);
    }

    public function test_users_cannot_strip_permissions_from_their_own_role(): void
    {
        $role = Role::create(['name' => 'editors', 'guard_name' => 'web'])->givePermissionTo(['dashboard.view', 'roles.view', 'roles.update']);
        $editor = User::factory()->create()->assignRole($role);

        $this->actingAs($editor)->putJson(route('admin.roles.update', $role), [
            'name' => 'editors',
            'permissions' => ['dashboard.view', 'roles.view'],
        ])->assertStatus(422)->assertJson(['message' => __('admin.errors.cannot_reduce_own_role')]);
    }
}
