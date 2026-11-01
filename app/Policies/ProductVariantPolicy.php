<?php

namespace App\Policies;

class ProductVariantPolicy extends OrganizationScopedPolicy
{
    protected function group(): string
    {
        return 'catalog';
    }
}
