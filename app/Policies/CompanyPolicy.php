<?php

namespace App\Policies;

class CompanyPolicy extends OrganizationScopedPolicy
{
    protected function group(): string
    {
        return 'companies';
    }
}
