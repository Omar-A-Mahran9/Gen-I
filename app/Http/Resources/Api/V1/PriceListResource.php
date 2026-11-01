<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PriceList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PriceList
 */
class PriceListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'code' => $this->code,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'prices_count' => $this->whenCounted('prices'),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
