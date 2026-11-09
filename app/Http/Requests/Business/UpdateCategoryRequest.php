<?php

namespace App\Http\Requests\Business;

/**
 * Update an existing Category record (route-bound). Shared rules live in CategoryRequest.
 */
class UpdateCategoryRequest extends CategoryRequest
{
    public function authorize(): bool
    {
        $record = $this->record();

        return $record !== null && $this->user()->can('update', $record);
    }
}
