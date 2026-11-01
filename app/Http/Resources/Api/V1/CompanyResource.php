<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'legal_name' => $this->legal_name,
            'tax_reference' => $this->tax_reference,
            'status' => $this->status->value,
            'account_owner' => $this->whenLoaded('accountOwner', fn () => $this->accountOwner ? ['id' => $this->accountOwner->id, 'name' => $this->accountOwner->name] : null),
            'members_count' => $this->whenCounted('members'),
            'addresses_count' => $this->whenCounted('addresses'),
            'members' => CompanyMemberResource::collection($this->whenLoaded('members')),
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
