<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only factory (never used by seeders).
 *
 * @extends Factory<PriceList>
 */
class PriceListFactory extends Factory
{
    protected $model = PriceList::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => strtoupper(fake()->unique()->bothify('LIST-###')),
            'currency' => 'EGP',
            'status' => 'active',
        ];
    }
}
