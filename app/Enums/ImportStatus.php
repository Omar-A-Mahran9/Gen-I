<?php

namespace App\Enums;

/**
 * Outcome of a catalog import run. Follows S2 ingestion_runs statuses (committed, quarantined)
 * plus "validated" for dry runs and "partially_committed" for partial mode with rejected rows.
 */
enum ImportStatus: string
{
    use HasLabels;

    case Validated = 'validated';
    case Committed = 'committed';
    case PartiallyCommitted = 'partially_committed';
    case Quarantined = 'quarantined';

    public static function translationKey(): string
    {
        return 'import_status';
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Committed => 'success',
            self::PartiallyCommitted => 'warning',
            self::Quarantined => 'danger',
            self::Validated => 'info',
        };
    }
}
