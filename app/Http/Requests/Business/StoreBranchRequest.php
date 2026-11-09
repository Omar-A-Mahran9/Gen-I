<?php

namespace App\Http\Requests\Business;

use App\Models\Branch;

/**
 * Create a Branch record. Shared rules live in BranchRequest.
 */
class StoreBranchRequest extends BranchRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Branch::class);
    }
}
