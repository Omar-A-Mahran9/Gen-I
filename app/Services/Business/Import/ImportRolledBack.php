<?php

namespace App\Services\Business\Import;

use RuntimeException;

/**
 * Internal signal used to roll back an atomic catalog import after a row failed to persist.
 * It never leaves CatalogImportService.
 */
final class ImportRolledBack extends RuntimeException {}
