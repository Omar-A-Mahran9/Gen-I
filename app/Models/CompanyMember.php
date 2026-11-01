<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Enums\MemberRole;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Company membership and spending authority (S2 §9.2).
 * approval_limit is stored but not enforced: corporate purchasing/credit is conditional scope.
 */
#[Fillable(['organization_id', 'company_id', 'user_id', 'member_role', 'approval_limit', 'currency', 'status'])]
class CompanyMember extends Model
{
    use BelongsToOrganization;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'member_role' => MemberRole::class,
            'status' => ActiveStatus::class,
            'approval_limit' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
