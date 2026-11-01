<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

/**
 * Organizations are the tenant boundary. Creating or deleting one is a
 * platform-level action reserved to super administrators holding the permission.
 */
class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('organizations.view');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->can('organizations.view') && $user->canAccessOrganization($organization->id);
    }

    public function create(User $user): bool
    {
        return $user->can('organizations.create') && $user->isSuperAdmin();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->can('organizations.update') && $user->canAccessOrganization($organization->id);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->can('organizations.delete') && $user->isSuperAdmin();
    }
}
