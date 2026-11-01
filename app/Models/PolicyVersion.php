<?php

namespace App\Models;

use App\Enums\PolicyStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Approved commercial/service rule version (S2 §9.4). Approved versions are immutable.
 */
#[Fillable(['organization_id', 'policy_code', 'version', 'rule_body', 'effective_from', 'effective_to'])]
class PolicyVersion extends Model
{
    use BelongsToOrganization;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PolicyStatus::class,
            'rule_body' => 'array',
            'version' => 'integer',
            'approved_at' => 'datetime',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function displayName(): string
    {
        return $this->policy_code.' v'.$this->version;
    }
}
