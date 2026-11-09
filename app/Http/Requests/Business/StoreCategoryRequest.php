<?php

namespace App\Http\Requests\Business;

use App\Models\Category;

/**
 * Create a Category record. Shared rules live in CategoryRequest.
 */
class StoreCategoryRequest extends CategoryRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Category::class);
    }
}
