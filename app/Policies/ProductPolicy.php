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
}
