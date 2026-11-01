<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CompanyMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompanyMember
 */
class CompanyMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email]),
            'member_role' => $this->member_role->value,
            'approval_limit' => $this->approval_limit === null ? null : ['amount' => (string) $this->approval_limit, 'currency' => $this->currency],
            'currency' => $this->currency,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
