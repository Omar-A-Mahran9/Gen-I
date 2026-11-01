<?php

namespace App\Policies;

class CategoryPolicy extends OrganizationScopedPolicy
{
    protected function group(): string
    {
        return 'catalog';
    }
}
