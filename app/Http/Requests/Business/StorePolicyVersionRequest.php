<?php

namespace App\Http\Requests\Business;

use App\Models\PolicyVersion;

/**
 * Create a PolicyVersion record. Shared rules live in PolicyVersionRequest.
 */
class StorePolicyVersionRequest extends PolicyVersionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PolicyVersion::class);
    }
}
