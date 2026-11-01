<?php

namespace App\Policies;

/**
 * Price lists and prices: "pricing.view" to read, "pricing.manage" to change.
 */
class PriceListPolicy extends OrganizationScopedPolicy
{
    protected function group(): string
    {
        return 'pricing';
    }

    protected function writePermission(string $action): string
    {
        return 'pricing.manage';
    }
}
