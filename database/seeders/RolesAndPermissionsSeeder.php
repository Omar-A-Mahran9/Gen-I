<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the foundation permissions and default roles from config/admin.php.
 *
 * Safe to run repeatedly:
 * - permissions are created only when missing;
 * - default roles get their permissions only when first created, so later
 *   changes made through the admin UI are preserved;
 * - the protected super-admin role always holds every permission.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = collect(config('admin.permissions'))
            ->flatMap(fn (array $actions, string $group): array => array_map(fn (string $action): string => "{$group}.{$action}", $actions))
            ->each(fn (string $name) => Permission::findOrCreate($name, 'web'))
            ->values();

        foreach (config('admin.default_roles') as $name => $permissions) {
            $role = Role::query()->where('name', $name)->where('guard_name', 'web')->first();
            $isNew = $role === null;
            $role ??= Role::create(['name' => $name, 'guard_name' => 'web']);

            if ($name === config('admin.super_admin_role')) {
                $role->syncPermissions($all);
            } elseif ($isNew) {
                $role->syncPermissions($permissions === ['*'] ? $all : $permissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
