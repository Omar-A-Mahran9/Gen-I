<?php

namespace Tests\Feature\Database;

use App\Models\Setting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FoundationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_runs_against_an_isolated_test_database(): void
    {
        $database = DB::connection()->getDatabaseName();

        $this->assertTrue(
            $database === ':memory:' || str_ends_with($database, '_testing'),
            "Unexpected test database [{$database}]."
        );
    }

    public function test_foundation_tables_exist(): void
    {
        foreach ([
            'users', 'password_reset_tokens', 'sessions', 'cache', 'jobs', 'failed_jobs',
            'personal_access_tokens', 'notifications', 'settings', 'audit_logs',
            'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table [{$table}].");
        }

        $this->assertTrue(Schema::hasColumns('users', ['is_active', 'locale', 'last_login_at', 'last_login_ip']));
    }

    public function test_required_indexes_exist(): void
    {
        $indexes = fn (string $table): array => collect(Schema::getIndexes($table))
            ->map(fn (array $index): string => implode(',', $index['columns']).($index['unique'] ? ':unique' : ''))
            ->all();

        $this->assertContains('email:unique', $indexes('users'));
        $this->assertContains('is_active', $indexes('users'));
        $this->assertContains('key:unique', $indexes('settings'));
        $this->assertContains('event,created_at', $indexes('audit_logs'));
        $this->assertContains('auditable_type,auditable_id', $indexes('audit_logs'));
        $this->assertContains('name,guard_name:unique', $indexes('roles'));
        $this->assertContains('name,guard_name:unique', $indexes('permissions'));
        $this->assertContains('token:unique', $indexes('personal_access_tokens'));
    }

    public function test_seeder_is_repeatable_and_preserves_customised_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Role::findByName('staff')->givePermissionTo('audit.view');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(12, Permission::count());
        $this->assertSame(4, Role::count());
        $this->assertTrue(Role::findByName('staff')->hasPermissionTo('audit.view'));
        $this->assertSame(12, Role::findByName('super-admin')->permissions()->count());
        $this->assertSame(0, User::count());
    }

    public function test_user_receives_permissions_through_a_role(): void
    {
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'foundation.probe.view']);
        Role::create(['name' => 'foundation-probe'])->givePermissionTo($permission);

        $user->assignRole('foundation-probe');

        $this->assertTrue($user->fresh()->hasRole('foundation-probe'));
        $this->assertTrue($user->fresh()->can('foundation.probe.view'));
    }

    public function test_deleting_a_role_cascades_to_its_assignments(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'foundation-probe']);
        $user->assignRole($role);

        $role->delete();

        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $user->id]);
    }

    public function test_deleting_a_user_keeps_audit_entries_with_null_actor(): void
    {
        $user = User::factory()->create();
        $log = app(AuditLogger::class)->log('probe.event', null, [], $user);

        $user->delete();

        $this->assertNull($log->fresh()->user_id);
    }

    public function test_unique_constraints_are_enforced(): void
    {
        DB::table('roles')->insert(['name' => 'dup', 'guard_name' => 'web']);
        Setting::query()->create(['key' => 'dup_key', 'value' => '1']);

        try {
            DB::table('roles')->insert(['name' => 'dup', 'guard_name' => 'web']);
            $this->fail('Duplicate role name was accepted.');
        } catch (UniqueConstraintViolationException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(UniqueConstraintViolationException::class);
        Setting::query()->create(['key' => 'dup_key', 'value' => '2']);
    }

    public function test_sanctum_token_authenticates_the_api(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('foundation')->plainTextToken;

        $this->assertSame($user->id, PersonalAccessToken::findToken($token)?->tokenable_id);

        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_api_rejects_requests_without_a_token(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }
}
