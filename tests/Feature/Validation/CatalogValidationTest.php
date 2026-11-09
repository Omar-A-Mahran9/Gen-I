<?php

namespace Tests\Feature\Validation;

use App\Models\AttributeDefinition;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBusinessData;
use Tests\TestCase;

/**
 * The shared rules behave the same through the admin/API Form Requests (validation_failed envelope).
 */
class CatalogValidationTest extends TestCase
{
    use BuildsBusinessData, RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->apiAs($this->staffOf($this->org, ['catalog.view', 'catalog.create', 'catalog.update']));
    }

    public function test_category_requests_validate_required_format_length_uniqueness_and_relations(): void
    {
        $this->postJson('/api/v1/categories', [])
            ->assertStatus(422)->assertJsonPath('code', 'validation_failed')->assertJsonStructure(['errors' => ['code', 'name_en']]);

        $this->postJson('/api/v1/categories', ['code' => 'bad code', 'name_en' => str_repeat('x', 121)])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['code', 'name_en']]);

        $this->postJson('/api/v1/categories', ['code' => ' Phones ', 'name_en' => 'Phones'])->assertCreated()->assertJsonPath('data.code', 'phones');
        $this->postJson('/api/v1/categories', ['code' => 'phones', 'name_en' => 'Again'])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['code']]);

        $foreign = Category::factory()->create();
        $this->postJson('/api/v1/categories', ['code' => 'child', 'name_en' => 'Child', 'parent_id' => $foreign->id])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['parent_id']]);
        $this->postJson('/api/v1/categories', ['code' => 'child', 'name_en' => 'Child', 'parent_id' => 'abc'])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['parent_id']]);
    }

    public function test_variant_requests_validate_sku_format_uniqueness_and_enums(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->org->id]);
        ProductVariant::factory()->create(['organization_id' => $this->org->id, 'sku' => 'USED-1']);

        $this->postJson("/api/v1/products/{$product->id}/variants", ['sku' => 'has space', 'variant_name' => 'Base', 'item_kind' => 'gadget', 'condition' => 'new'])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['sku', 'item_kind']]);

        $this->postJson("/api/v1/products/{$product->id}/variants", ['sku' => 'used-1', 'variant_name' => 'Base', 'item_kind' => 'device', 'condition' => 'new'])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['sku']]);

        $this->postJson("/api/v1/products/{$product->id}/variants", ['sku' => 'new-1', 'variant_name' => 'Base', 'item_kind' => 'device', 'condition' => 'new'])
            ->assertCreated()->assertJsonPath('data.sku', 'NEW-1');
    }

    public function test_product_requests_reject_duplicate_identity_and_foreign_relations(): void
    {
        $category = Category::factory()->create(['organization_id' => $this->org->id]);
        $brand = Brand::factory()->create(['organization_id' => $this->org->id]);
        Product::factory()->create(['organization_id' => $this->org->id, 'brand_id' => $brand->id, 'category_id' => $category->id, 'model_code' => 'M-1']);
        $foreignBrand = Brand::factory()->create();

        $this->postJson('/api/v1/products', ['category_id' => $category->id, 'brand_id' => $brand->id, 'model_code' => 'M-1', 'name_en' => 'Dup', 'source_reference' => 'Ref'])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['model_code']]);

        $this->postJson('/api/v1/products', ['category_id' => $category->id, 'brand_id' => $foreignBrand->id, 'model_code' => 'M-2', 'name_en' => 'X'])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['brand_id', 'source_reference']]);
    }

    public function test_specification_values_must_match_the_attribute_type_and_schema_precision(): void
    {
        $category = Category::factory()->create(['organization_id' => $this->org->id]);
        $product = Product::factory()->create(['organization_id' => $this->org->id, 'category_id' => $category->id]);
        $variant = ProductVariant::factory()->create(['organization_id' => $this->org->id, 'product_id' => $product->id]);
        $number = AttributeDefinition::query()->create(['organization_id' => $this->org->id, 'category_id' => $category->id, 'code' => 'weight_kg', 'label' => 'Weight', 'value_type' => 'number', 'unit' => 'kg', 'comparison_order' => 1]);
        $flag = AttributeDefinition::query()->create(['organization_id' => $this->org->id, 'category_id' => $category->id, 'code' => 'wifi', 'label' => 'Wi-Fi', 'value_type' => 'boolean', 'comparison_order' => 2]);
        $otherCategoryAttribute = AttributeDefinition::query()->create(['organization_id' => $this->org->id, 'category_id' => Category::factory()->create(['organization_id' => $this->org->id])->id, 'code' => 'x', 'label' => 'X', 'value_type' => 'text', 'comparison_order' => 1]);

        $this->putJson("/api/v1/variants/{$variant->id}/specifications", ['values' => [
            ['attribute_id' => $number->id, 'value' => '1.1234567', 'source_reference' => 'Sheet'],
            ['attribute_id' => $flag->id, 'value' => 'maybe', 'source_reference' => 'Sheet'],
            ['attribute_id' => $otherCategoryAttribute->id, 'value' => 'v', 'source_reference' => 'Sheet'],
        ]])->assertStatus(422)->assertJsonStructure(['errors' => ['values.0.value', 'values.1.value', 'values.2.attribute_id']]);

        $this->putJson("/api/v1/variants/{$variant->id}/specifications", ['values' => [
            ['attribute_id' => $number->id, 'value' => '1.25'],
        ]])->assertStatus(422)->assertJsonStructure(['errors' => ['values.0.source_reference']]);

        $this->putJson("/api/v1/variants/{$variant->id}/specifications", ['values' => [
            ['attribute_id' => $number->id, 'value' => '1.25', 'source_reference' => 'Sheet'],
            ['attribute_id' => $flag->id, 'value' => 'true', 'source_reference' => 'Sheet'],
        ]])->assertOk();

        $this->assertSame(2, $variant->attributeValues()->count());
    }
}
