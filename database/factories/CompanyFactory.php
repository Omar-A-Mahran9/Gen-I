<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only factory (never used by seeders).
 *
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'legal_name' => fake()->unique()->company(),
            'tax_reference' => null,
            'status' => 'pending',
        ];
    }
}
