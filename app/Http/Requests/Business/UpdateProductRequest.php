<?php

namespace App\Http\Requests\Business;

/**
 * Update an existing Product record (route-bound). Shared rules live in ProductRequest.
 */
class UpdateProductRequest extends ProductRequest
{
    public function authorize(): bool
    {
        $record = $this->record();

        return $record !== null && $this->user()->can('update', $record);
    }
}
