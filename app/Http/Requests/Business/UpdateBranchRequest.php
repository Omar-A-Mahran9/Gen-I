<?php

namespace App\Http\Requests\Business;

/**
 * Update an existing Branch record (route-bound). Shared rules live in BranchRequest.
 */
class UpdateBranchRequest extends BranchRequest
{
    public function authorize(): bool
    {
        $record = $this->record();

        return $record !== null && $this->user()->can('update', $record);
    }
}
