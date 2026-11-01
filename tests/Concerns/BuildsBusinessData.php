<?php

namespace Tests\Concerns;

use App\Enums\PolicyStatus;
use App\Models\Organization;
use App\Models\PolicyVersion;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/**
 * Helpers for business-module tests: organization-bound staff with explicit permissions.
 */
trait BuildsBusinessData
{
    protected static int $roleCounter = 0;

    /**
     * An active user of $organization holding exactly the given permissions (plus admin access).
     *
     * @param  array<int, string>  $permissions
     */
    protected function staffOf(Organization $organization, array $permissions): User
    {
        $this->ensureRolesSeeded();

        $role = Role::create(['name' => 'test-role-'.(++static::$roleCounter), 'guard_name' => 'web']);
        $role->givePermissionTo(array_values(array_unique(array_merge(['dashboard.view'], $permissions))));

        $user = User::factory()->create();
        $user->forceFill(['organization_id' => $organization->id])->save();

        return $user->assignRole($role)->fresh();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function apiAs(User $user): User
    {
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    protected function ensureRolesSeeded(): void
    {
        if (Role::query()->where('name', 'super-admin')->doesntExist()) {
            $this->seedRoles();
        }
    }

    protected function approvedPolicy(Organization $organization, string $code = 'tax.vat'): PolicyVersion
    {
        $policy = new PolicyVersion([
            'organization_id' => $organization->id,
            'policy_code' => $code,
            'version' => (int) PolicyVersion::query()->where('organization_id', $organization->id)->where('policy_code', $code)->max('version') + 1,
            'rule_body' => ['rate' => '14.00'],
            'effective_from' => now()->subYear(),
        ]);
        $policy->status = PolicyStatus::Approved;
        $policy->approved_at = now();
        $policy->save();

        return $policy;
    }

    /**
     * Every business permission (for "full access within one organization" actors).
     *
     * @return array<int, string>
     */
    protected function businessPermissions(): array
    {
        return collect(config('admin.permissions'))
            ->only(['organizations', 'companies', 'branches', 'catalog', 'policies', 'pricing', 'inventory'])
            ->flatMap(fn (array $actions, string $group) => array_map(fn ($a) => "{$group}.{$a}", $actions))
            ->values()
            ->all();
    }
}
