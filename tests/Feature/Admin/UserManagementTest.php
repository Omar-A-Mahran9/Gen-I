<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Notifications\AccountUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->superAdmin(['name' => 'Root Admin', 'email' => 'root@geni.test']);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function tableQuery(array $extra = []): array
    {
        return array_replace_recursive([
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => ''],
            'order' => [['column' => 0, 'dir' => 'asc']],
            'columns' => [['data' => 'name'], ['data' => 'roles'], ['data' => 'status'], ['data' => 'last_login_at'], ['data' => 'created_at'], ['data' => 'actions']],
        ], $extra);
    }

    public function test_users_index_page_renders(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('data-geni-datatable', false)
            ->assertSee('assets/admin/plugins/custom/datatables/datatables.bundle.js', false);
    }

    public function test_datatable_lists_users_with_pagination(): void
    {
        User::factory()->count(14)->create();

        $response = $this->actingAs($this->admin)->getJson(route('admin.users.index', $this->tableQuery(['length' => 10, 'start' => 10])));

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 15)
            ->assertJsonPath('recordsFiltered', 15)
            ->assertJsonCount(5, 'data');
    }

    public function test_datatable_search_filters_by_name_or_email(): void
    {
        User::factory()->create(['name' => 'Layla Hassan', 'email' => 'layla@geni.test']);
        User::factory()->create(['name' => 'Omar Said', 'email' => 'omar@geni.test']);

        $response = $this->actingAs($this->admin)->getJson(route('admin.users.index', $this->tableQuery(['search' => ['value' => 'layla']])));

        $response->assertOk()->assertJsonPath('recordsFiltered', 1);
        $this->assertStringContainsString('Layla Hassan', $response->json('data.0.name'));
    }

    public function test_datatable_filters_by_status_and_role(): void
    {
        User::factory()->inactive()->create(['name' => 'Inactive One']);
        $this->userWithRole('manager', ['name' => 'Manager One']);

        $inactive = $this->actingAs($this->admin)->getJson(route('admin.users.index', $this->tableQuery(['filters' => ['status' => 'inactive']])));
        $inactive->assertJsonPath('recordsFiltered', 1);
        $this->assertStringContainsString('Inactive One', $inactive->json('data.0.name'));

        $managers = $this->actingAs($this->admin)->getJson(route('admin.users.index', $this->tableQuery(['filters' => ['role' => 'manager']])));
        $managers->assertJsonPath('recordsFiltered', 1);
        $this->assertStringContainsString('Manager One', $managers->json('data.0.name'));
    }

    public function test_datatable_sorts_only_allow_listed_columns(): void
    {
        User::factory()->create(['name' => 'Aaron']);
        User::factory()->create(['name' => 'Zed']);

        $asc = $this->actingAs($this->admin)->getJson(route('admin.users.index', $this->tableQuery(['order' => [['column' => 0, 'dir' => 'asc']]])));
        $this->assertStringContainsString('Aaron', $asc->json('data.0.name'));

        $desc = $this->actingAs($this->admin)->getJson(route('admin.users.index', $this->tableQuery(['order' => [['column' => 0, 'dir' => 'desc']]])));
        $this->assertStringContainsString('Zed', $desc->json('data.0.name'));

        // Unknown column names are ignored rather than injected into SQL.
        $this->actingAs($this->admin)->getJson(route('admin.users.index', $this->tableQuery([
            'order' => [['column' => 0, 'dir' => 'asc']],
            'columns' => [['data' => 'password; drop table users']],
        ])))->assertOk();
    }

    public function test_datatable_escapes_user_supplied_values(): void
    {
        User::factory()->create(['name' => '<script>alert(1)</script>']);

        $response = $this->actingAs($this->admin)->getJson(route('admin.users.index', $this->tableQuery(['search' => ['value' => 'alert']])));

        $this->assertStringNotContainsString('<script>', $response->json('data.0.name'));
        $this->assertStringContainsString('&lt;script&gt;', $response->json('data.0.name'));
    }

    public function test_admin_can_create_a_user_with_roles(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'New Staff',
            'email' => 'NEW.Staff@Geni.test',
            'password' => 'Secret-Pass-123',
            'password_confirmation' => 'Secret-Pass-123',
            'is_active' => '1',
            'locale' => 'en',
            'roles' => ['manager'],
        ]);

        $user = User::query()->where('email', 'new.staff@geni.test')->firstOrFail();
        $response->assertRedirect(route('admin.users.show', $user))->assertSessionHas('success');

        $this->assertTrue(Hash::check('Secret-Pass-123', $user->password));
        $this->assertTrue($user->hasRole('manager'));
        $this->assertTrue($user->is_active);
        $this->assertSame('en', $user->locale);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.created', 'auditable_id' => $user->id, 'user_id' => $this->admin->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id, 'type' => AccountUpdated::class]);
    }

    public function test_create_user_validation(): void
    {
        User::factory()->create(['email' => 'taken@geni.test']);

        $this->actingAs($this->admin)->postJson(route('admin.users.store'), [
            'name' => '',
            'email' => 'taken@geni.test',
            'password' => 'short',
            'password_confirmation' => 'different',
            'roles' => ['does-not-exist'],
        ])->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'password', 'roles.0']);
    }

    public function test_admin_can_update_a_user_and_roles(): void
    {
        $user = $this->userWithRole('staff', ['name' => 'Before']);

        $this->actingAs($this->admin)->putJson(route('admin.users.update', $user), [
            'name' => 'After',
            'email' => 'after@geni.test',
            'roles_present' => 1,
            'roles' => ['manager'],
        ])->assertOk()->assertJsonPath('redirect', route('admin.users.show', $user));

        $user->refresh();
        $this->assertSame('After', $user->name);
        $this->assertSame('after@geni.test', $user->email);
        $this->assertSame(['manager'], $user->getRoleNames()->all());
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.roles_changed', 'auditable_id' => $user->id]);
    }

    public function test_update_without_role_fields_keeps_roles(): void
    {
        $user = $this->userWithRole('manager');

        $this->actingAs($this->admin)->putJson(route('admin.users.update', $user), [
            'name' => 'Renamed',
            'email' => $user->email,
        ])->assertOk();

        $this->assertTrue($user->fresh()->hasRole('manager'));
    }

    public function test_admin_can_deactivate_and_reactivate_a_user(): void
    {
        $user = $this->userWithRole('staff');
        $user->createToken('device');
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        config(['session.driver' => 'database']);

        $this->actingAs($this->admin)->patchJson(route('admin.users.status', $user))
            ->assertOk()->assertJsonPath('is_active', false);

        $this->assertFalse($user->fresh()->is_active);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);

        $this->actingAs($this->admin)->patchJson(route('admin.users.status', $user))
            ->assertOk()->assertJsonPath('is_active', true);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_admin_can_reset_a_users_password(): void
    {
        $user = $this->userWithRole('staff');
        $user->createToken('device');

        $this->actingAs($this->admin)->putJson(route('admin.users.password', $user), [
            'password' => 'Brand-New-Pass-9',
            'password_confirmation' => 'Brand-New-Pass-9',
        ])->assertOk();

        $this->assertTrue(Hash::check('Brand-New-Pass-9', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.password_reset', 'auditable_id' => $user->id]);
    }

    public function test_admin_can_delete_a_user(): void
    {
        $user = $this->userWithRole('staff');

        $this->actingAs($this->admin)->deleteJson(route('admin.users.destroy', $user))
            ->assertOk()->assertJsonPath('redirect', route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.deleted']);
    }

    public function test_show_and_edit_pages_render(): void
    {
        $user = $this->userWithRole('staff');

        $this->actingAs($this->admin)->get(route('admin.users.show', $user))->assertOk()->assertSee($user->email);
        $this->actingAs($this->admin)->get(route('admin.users.edit', $user))->assertOk()->assertSee('name="roles[]"', false);
        $this->actingAs($this->admin)->get(route('admin.users.create'))->assertOk()->assertSee('name="password_confirmation"', false);
    }

    public function test_user_search_endpoint_for_select2(): void
    {
        User::factory()->create(['name' => 'Searchable Person', 'email' => 'search@geni.test']);

        $this->actingAs($this->admin)->getJson(route('admin.users.search', ['q' => 'searchable']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.text', 'Searchable Person (search@geni.test)');
    }
}
