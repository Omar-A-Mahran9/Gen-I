<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only factory (never used by seeders).
 *
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'product_id' => fn (array $a) => Product::factory()->create(['organization_id' => $a['organization_id']])->id,
            'sku' => strtoupper(fake()->unique()->bothify('SKU-#####')),
            'variant_name' => fake()->words(2, true),
            'item_kind' => 'device',
            'condition' => 'new',
            'status' => 'active',
        ];
    }
}
