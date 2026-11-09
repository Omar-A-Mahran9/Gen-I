<?php

namespace App\Http\Requests\Business;

/**
 * Update an existing Company record (route-bound). Shared rules live in CompanyRequest.
 */
class UpdateCompanyRequest extends CompanyRequest
{
    public function authorize(): bool
    {
        $record = $this->record();

        return $record !== null && $this->user()->can('update', $record);
    }
}
