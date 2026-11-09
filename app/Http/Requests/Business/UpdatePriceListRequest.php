<?php

namespace App\Http\Requests\Business;

/**
 * Update an existing PriceList record (route-bound). Shared rules live in PriceListRequest.
 */
class UpdatePriceListRequest extends PriceListRequest
{
    public function authorize(): bool
    {
        $record = $this->record();

        return $record !== null && $this->user()->can('update', $record);
    }
}
