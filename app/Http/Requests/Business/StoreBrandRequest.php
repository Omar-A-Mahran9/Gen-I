<?php

namespace App\Http\Requests\Business;

use App\Models\Brand;

/**
 * Create a Brand record. Shared rules live in BrandRequest.
 */
class StoreBrandRequest extends BrandRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Brand::class);
    }
}
