<?php

namespace Tests\Feature\Database;

use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_seeds_reference_data_only_and_is_repeatable(): void
    {
        $this->seed(DatabaseSeeder::class);

        $counts = fn (): array => [
            'permissions' => Permission::query()->count(),
            'roles' => Role::query()->count(),
            'role_permissions' => DB::table('role_has_permissions')->count(),
            'settings' => Setting::query()->count(),
        ];
        $first = $counts();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($first, $counts(), 'Running the seeders again creates no duplicates.');
        $this->assertSame(0, User::query()->count(), 'No user accounts or credentials are seeded.');
        $this->assertSame(collect(config('admin.permissions'))->flatten()->count(), $first['permissions']);
        $this->assertSame(SettingsSeeder::KEYS, Setting::query()->orderBy('id')->pluck('key')->all());
        $this->assertDatabaseCount('organizations', 0);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_catalog_import_permission_goes_to_super_admin_only(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Role::findByName('super-admin', 'web')->hasPermissionTo('catalog.import'));
        foreach (['admin', 'manager', 'staff'] as $role) {
            $this->assertFalse(Role::findByName($role, 'web')->hasPermissionTo('catalog.import'), "{$role} must not receive catalog.import (BD-14).");
        }
    }

    public function test_settings_seeder_writes_approved_defaults_without_overwriting_existing_values(): void
    {
        $settings = app(SettingsService::class);
        $settings->set(['default_theme' => 'dark', 'app_name' => 'Custom']);

        $this->seed(SettingsSeeder::class);
        $this->seed(SettingsSeeder::class);

        $settings->flush();
        $this->assertSame('dark', $settings->get('default_theme'), 'A value changed through the Settings page is kept.');
        $this->assertSame('Custom', $settings->get('app_name'));
        $this->assertSame('ar', $settings->get('default_locale'));
        $this->assertSame(['ar', 'en'], $settings->get('available_locales'));
        $this->assertSame(1, Setting::query()->where('key', 'default_theme')->count());
    }

    public function test_reseeding_keeps_role_changes_and_existing_users(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = Role::findByName('staff', 'web');
        $staff->givePermissionTo('catalog.view');
        $user = User::factory()->create()->assignRole('staff');

        $this->seed(DatabaseSeeder::class);

        $this->assertTrue($staff->fresh()->hasPermissionTo('catalog.view'));
        $this->assertTrue($user->fresh()->hasRole('staff'));
        $this->assertSame(1, User::query()->count());
    }
}
