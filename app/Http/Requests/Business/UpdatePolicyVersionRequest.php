<?php

namespace App\Http\Requests\Business;

/**
 * Update an existing PolicyVersion record (route-bound). Shared rules live in PolicyVersionRequest.
 */
class UpdatePolicyVersionRequest extends PolicyVersionRequest
{
    public function authorize(): bool
    {
        $record = $this->record();

        return $record !== null && $this->user()->can('update', $record);
    }
}
