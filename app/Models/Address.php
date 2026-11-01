<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Company delivery address (S2 §9.2). Customer-owned addresses arrive with the customers table.
 */
#[Fillable(['organization_id', 'company_id', 'label', 'address_text', 'city', 'postal_code', 'country_code', 'contact_phone'])]
class Address extends Model
{
    use BelongsToOrganization;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
