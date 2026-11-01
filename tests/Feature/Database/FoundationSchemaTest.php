<?php

namespace Tests\Feature\Database;

use App\Models\User;
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
            'personal_access_tokens',
            'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table [{$table}].");
        }
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

    public function test_role_names_are_unique_per_guard(): void
    {
        DB::table('roles')->insert(['name' => 'dup', 'guard_name' => 'web']);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('roles')->insert(['name' => 'dup', 'guard_name' => 'web']);
    }

    public function test_sanctum_token_authenticates_the_api(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('foundation')->plainTextToken;

        $this->assertSame($user->id, PersonalAccessToken::findToken($token)?->tokenable_id);

        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_api_rejects_requests_without_a_token(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }
}
