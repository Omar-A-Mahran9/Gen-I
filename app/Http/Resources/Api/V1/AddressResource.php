<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Address
 */
class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'label' => $this->label,
            'address_text' => $this->address_text,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'country_code' => $this->country_code,
            'contact_phone' => $this->contact_phone,
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
