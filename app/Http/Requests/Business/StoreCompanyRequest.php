<?php

namespace App\Http\Requests\Business;

use App\Models\Company;

/**
 * Create a Company record. Shared rules live in CompanyRequest.
 */
class StoreCompanyRequest extends CompanyRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Company::class);
    }
}
