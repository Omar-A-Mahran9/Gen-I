<?php

namespace App\Http\Requests\Business;

use App\Models\Organization;

/**
 * Update an organization. Shared rules live in OrganizationRequest.
 */
class UpdateOrganizationRequest extends OrganizationRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization && $this->user()->can('update', $organization);
    }
}
