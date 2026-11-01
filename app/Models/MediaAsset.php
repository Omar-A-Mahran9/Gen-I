<?php

namespace App\Models;

use App\Enums\MediaClassification;
use App\Enums\ScanStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Stored file object (S2 §9.3). Files live on the private "local" disk; only
 * public + clean assets may be exposed outside authenticated staff tools.
 */
#[Fillable(['organization_id', 'object_key', 'mime_type', 'sha256', 'byte_size', 'classification', 'scan_status', 'rights_reference'])]
class MediaAsset extends Model
{
    use BelongsToOrganization;

    public const DISK = 'local';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'classification' => MediaClassification::class,
            'scan_status' => ScanStatus::class,
            'byte_size' => 'integer',
        ];
    }

    public function isPubliclyServable(): bool
    {
        return $this->classification === MediaClassification::Public && $this->scan_status === ScanStatus::Clean;
    }
}
