<?php

namespace App\Policies;

class BrandPolicy extends OrganizationScopedPolicy
{
    protected function group(): string
    {
        return 'catalog';
    }
}
