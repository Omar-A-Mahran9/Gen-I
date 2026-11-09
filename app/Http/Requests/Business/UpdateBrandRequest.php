<?php

namespace App\Http\Requests\Business;

/**
 * Update an existing Brand record (route-bound). Shared rules live in BrandRequest.
 */
class UpdateBrandRequest extends BrandRequest
{
    public function authorize(): bool
    {
        $record = $this->record();

        return $record !== null && $this->user()->can('update', $record);
    }
}
