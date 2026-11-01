<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run tests against any database other than an in-memory
     * SQLite database or a dedicated "*_testing" database, so that
     * RefreshDatabase can never wipe the development database.
     */
    protected function setUpTraits(): array
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($database !== ':memory:' && ! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "Tests must run against ':memory:' or a '*_testing' database; [{$connection}:{$database}] given."
            );
        }

        return parent::setUpTraits();
    }
}
