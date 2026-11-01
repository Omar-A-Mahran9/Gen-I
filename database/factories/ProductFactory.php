<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only factory (never used by seeders).
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'category_id' => fn (array $a) => Category::factory()->create(['organization_id' => $a['organization_id']])->id,
            'brand_id' => fn (array $a) => Brand::factory()->create(['organization_id' => $a['organization_id']])->id,
            'model_code' => strtoupper(fake()->unique()->bothify('MDL-####')),
            'name_en' => fake()->words(3, true),
            'status' => 'draft',
            'source_reference' => 'Test fixture',
        ];
    }
}
