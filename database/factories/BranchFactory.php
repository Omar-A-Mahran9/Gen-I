<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only factory (never used by seeders).
 *
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => strtoupper(fake()->unique()->bothify('BR-###??')),
            'name' => fake()->city().' Store',
            'address_text' => fake()->address(),
            'city' => fake()->city(),
            'opening_hours' => ['sat' => [['open' => '10:00', 'close' => '22:00']], 'sun' => [], 'mon' => [], 'tue' => [], 'wed' => [], 'thu' => [], 'fri' => []],
            'status' => 'active',
        ];
    }
}
