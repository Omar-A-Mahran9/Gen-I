<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must not depend on built front-end assets.
        $this->withoutVite();
    }

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

    protected function seedRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Create an active user holding the given roles (roles are seeded on demand).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function userWithRole(string|array $roles, array $attributes = []): User
    {
        if (Role::query()->doesntExist()) {
            $this->seedRoles();
        }

        return User::factory()->create($attributes)->assignRole($roles);
    }

    protected function superAdmin(array $attributes = []): User
    {
        return $this->userWithRole('super-admin', $attributes);
    }
}
