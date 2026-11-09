<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy extends OrganizationScopedPolicy
{
    protected function group(): string
    {
        return 'catalog';
    }

    /**
     * Approving, publishing and retiring is a domain/commercial approval step (UC19).
     */
    public function approve(User $user, Product $product): bool
    {
        return $user->can('catalog.approve') && $this->inScope($user, $product);
    }

    /**
     * Bulk catalog import (UC19): creates draft products and their exact variants, or updates them.
     * Publication still needs catalog.approve through the normal lifecycle.
     */
    public function import(User $user): bool
    {
        return $user->can('catalog.import') && ($user->isSuperAdmin() || $user->organization_id !== null);
    }
}
