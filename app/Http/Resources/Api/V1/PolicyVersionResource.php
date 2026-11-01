<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PolicyVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PolicyVersion
 */
class PolicyVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'policy_code' => $this->policy_code,
            'version' => $this->version,
            'rule_body' => $this->rule_body,
            'status' => $this->status->value,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601ZuluString(),
            'effective_from' => $this->effective_from?->toIso8601ZuluString(),
            'effective_to' => $this->effective_to?->toIso8601ZuluString(),
        ];
    }
}
