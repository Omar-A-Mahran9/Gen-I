<?php

namespace App\Services\Business\Import;

use App\Enums\ImportMode;
use App\Enums\ImportStatus;
use Illuminate\Support\Str;

/**
 * Result of a catalog import run: summary counters, per-row outcomes and row-level errors
 * (docs/catalog-import-contract.md §7). Returned as data on success and as error.details
 * when the batch is quarantined, so clients parse a single structure.
 */
class ImportReport
{
    /** Maximum number of error entries returned; the counters always cover every row. */
    public const MAX_ERRORS = 1000;

    public const OUTCOME_CREATED = 'created';

    public const OUTCOME_UPDATED = 'updated';

    public const OUTCOME_UNCHANGED = 'unchanged';

    public const OUTCOME_VALID = 'valid';

    public const OUTCOME_FAILED = 'failed';

    public ImportStatus $status = ImportStatus::Validated;

    public int $totalRows = 0;

    public int $productsCreated = 0;

    public int $productsUpdated = 0;

    public int $specificationsWritten = 0;

    /** @var array<int, string> record number => outcome */
    protected array $outcomes = [];

    /** @var array<int, true> record numbers rejected as in-file duplicates */
    protected array $duplicates = [];

    /** @var array<int, array{row: int|null, column: string|null, code: string, message: string, value?: string}> */
    protected array $errors = [];

    protected int $errorCount = 0;

    public function __construct(
        public readonly ImportMode $mode,
        public readonly bool $dryRun,
        public readonly string $fileName,
        public readonly string $sha256,
    ) {}

    public function addError(?int $row, ?string $column, string $code, string $message, mixed $value = null): void
    {
        $this->errorCount++;

        if ($row !== null) {
            $this->outcomes[$row] = self::OUTCOME_FAILED;
        }

        if (count($this->errors) >= self::MAX_ERRORS) {
            return;
        }

        $entry = ['row' => $row, 'column' => $column, 'code' => $code, 'message' => $message];

        if ($value !== null && $value !== '') {
            $entry['value'] = Str::limit((string) $value, 100);
        }

        $this->errors[] = $entry;
    }

    public function markDuplicate(int $row): void
    {
        $this->duplicates[$row] = true;
    }

    public function setOutcome(int $row, string $outcome): void
    {
        $this->outcomes[$row] = $outcome;
    }

    public function hasFailed(int $row): bool
    {
        return ($this->outcomes[$row] ?? null) === self::OUTCOME_FAILED;
    }

    public function hasErrors(): bool
    {
        return $this->errorCount > 0;
    }

    public function count(string $outcome): int
    {
        return count(array_keys($this->outcomes, $outcome, true));
    }

    /**
     * Rows that went through without error (committed, unchanged or — in a dry run — valid).
     */
    public function successfulRows(): int
    {
        return $this->totalRows - $this->count(self::OUTCOME_FAILED);
    }

    /**
     * @return array<int, array{row: int|null, column: string|null, code: string, message: string, value?: string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'mode' => $this->mode->value,
            'dry_run' => $this->dryRun,
            'contract_version' => CatalogImportContract::VERSION,
            'file' => ['name' => $this->fileName, 'sha256' => $this->sha256],
            'summary' => [
                'total_rows' => $this->totalRows,
                'valid_rows' => $this->successfulRows(),
                'created_rows' => $this->count(self::OUTCOME_CREATED),
                'updated_rows' => $this->count(self::OUTCOME_UPDATED),
                'unchanged_rows' => $this->count(self::OUTCOME_UNCHANGED),
                'failed_rows' => $this->count(self::OUTCOME_FAILED),
                'duplicate_rows' => count($this->duplicates),
                'products_created' => $this->productsCreated,
                'products_updated' => $this->productsUpdated,
                'specifications_written' => $this->specificationsWritten,
            ],
            'errors' => $this->errors,
            'error_count' => $this->errorCount,
            'errors_truncated' => $this->errorCount > count($this->errors),
        ];
    }
}
