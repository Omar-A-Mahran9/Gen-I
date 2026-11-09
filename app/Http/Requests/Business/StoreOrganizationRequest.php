<?php

namespace App\Http\Requests\Business;

use App\Models\Organization;

/**
 * Create an organization (platform level). Shared rules live in OrganizationRequest.
 */
class StoreOrganizationRequest extends OrganizationRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Organization::class);
    }
}
