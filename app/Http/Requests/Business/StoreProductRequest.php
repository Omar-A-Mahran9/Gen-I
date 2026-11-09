<?php

namespace App\Http\Requests\Business;

use App\Models\Product;

/**
 * Create a Product record. Shared rules live in ProductRequest.
 */
class StoreProductRequest extends ProductRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }
}
