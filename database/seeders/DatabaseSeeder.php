<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's foundational reference data. Every seeder is idempotent.
     *
     * No user accounts, organizations or catalog/commercial data are seeded: create the
     * first administrator with `php artisan app:create-admin`; organizations, the category
     * vocabulary, prices and stock wait for their approved business decisions
     * (OT-02, BD-03, BD-04, S1-D2-02).
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
