<?php

namespace App\Enums;

/**
 * How a catalog import treats invalid rows (docs/catalog-import-contract.md §6).
 *
 * - atomic: all rows or nothing. Any invalid row quarantines the whole batch (UC19 "invalid batch quarantined").
 * - partial: valid rows are committed; invalid rows are reported and skipped.
 */
enum ImportMode: string
{
    use HasLabels;

    case Atomic = 'atomic';
    case Partial = 'partial';

    public static function translationKey(): string
    {
        return 'import_mode';
    }
}
