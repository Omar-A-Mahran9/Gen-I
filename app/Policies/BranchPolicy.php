<?php

namespace App\Policies;

class BranchPolicy extends OrganizationScopedPolicy
{
    protected function group(): string
    {
        return 'branches';
    }
}
