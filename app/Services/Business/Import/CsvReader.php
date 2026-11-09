<?php

namespace App\Services\Business\Import;

use Illuminate\Validation\ValidationException;

/**
 * Reads an RFC 4180 CSV file (UTF-8, optional BOM, comma delimiter, double-quote enclosure)
 * into a header and numbered records. Problems with the file as a whole are reported as a
 * ValidationException on the "file" field; row-level problems are left to the caller.
 */
class CsvReader
{
    /**
     * Rows are keyed by record number (the header is record 1); blank lines are skipped.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<int, string|null>>}
     *
     * @throws ValidationException
     */
    public function read(string $path): array
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            $this->fail(__('admin.catalog_import.errors.file_unreadable'));
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (trim($content) === '') {
            $this->fail(__('admin.catalog_import.errors.file_empty'));
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $this->fail(__('admin.catalog_import.errors.file_encoding'));
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $headers = null;
        $rows = [];
        $record = 0;

        while (($cells = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $record++;

            if ($cells === [null] || array_filter($cells, fn (?string $cell): bool => $cell !== null && trim($cell) !== '') === []) {
                continue;
            }

            if ($headers === null) {
                $headers = $cells;

                continue;
            }

            $rows[$record] = $cells;
        }

        fclose($handle);

        return ['headers' => array_map(fn (?string $header): string => (string) $header, $headers ?? []), 'rows' => $rows];
    }

    /**
     * @throws ValidationException
     */
    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => [$message]]);
    }
}
