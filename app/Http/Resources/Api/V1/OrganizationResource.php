<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'companies_count' => $this->whenCounted('companies'),
            'branches_count' => $this->whenCounted('branches'),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
