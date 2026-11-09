<?php

namespace App\Http\Requests\Business;

use App\Models\PriceList;

/**
 * Create a PriceList record. Shared rules live in PriceListRequest.
 */
class StorePriceListRequest extends PriceListRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PriceList::class);
    }
}
