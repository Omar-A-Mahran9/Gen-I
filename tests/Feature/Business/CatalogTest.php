<?php

namespace Tests\Feature\Business;

use App\Models\AttributeDefinition;
use App\Models\Brand;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsBusinessData;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use BuildsBusinessData, RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
    }

    protected function editor(array $extra = []): User
    {
        return $this->staffOf($this->org, array_merge(['catalog.view', 'catalog.create', 'catalog.update', 'catalog.delete'], $extra));
    }

    public function test_category_hierarchy_and_cycle_prevention(): void
    {
        $user = $this->editor();

        $this->actingAs($user)->postJson(route('admin.categories.store'), ['code' => 'Phones', 'name_en' => 'Phones', 'name_ar' => 'هواتف'])->assertOk();
        $phones = Category::query()->where('code', 'phones')->firstOrFail();
        $this->actingAs($user)->postJson(route('admin.categories.store'), ['code' => 'smartphones', 'name_en' => 'Smartphones', 'parent_id' => $phones->id])->assertOk();
        $smart = Category::query()->where('code', 'smartphones')->firstOrFail();

        // A category cannot move under its own descendant.
        $this->actingAs($user)->putJson(route('admin.categories.update', $phones), ['code' => 'phones', 'name_en' => 'Phones', 'parent_id' => $smart->id])
            ->assertStatus(422)->assertJsonPath('message', __('admin.errors.category_cycle'));

        // Parent must be in the same organization; codes are unique per organization.
        $foreign = Category::factory()->create();
        $this->actingAs($user)->postJson(route('admin.categories.store'), ['code' => 'phones', 'name_en' => 'Dup', 'parent_id' => $foreign->id])
            ->assertStatus(422)->assertJsonValidationErrors(['code', 'parent_id']);

        $this->actingAs($user)->get(route('admin.categories.show', $phones))->assertOk()->assertSee('Smartphones');
        $this->actingAs($user)->deleteJson(route('admin.categories.destroy', $phones))->assertStatus(422);
    }

    public function test_brand_names_are_unique_after_normalisation(): void
    {
        $user = $this->editor();
        $this->actingAs($user)->postJson(route('admin.brands.store'), ['name' => 'Apple'])->assertOk();

        $this->actingAs($user)->postJson(route('admin.brands.store'), ['name' => '  aPPLE '])->assertStatus(422)->assertJsonValidationErrors(['name']);
        $this->assertSame('apple', Brand::query()->value('name_normalized'));

        $other = $this->staffOf(Organization::factory()->create(), ['catalog.view', 'catalog.create']);
        $this->actingAs($other)->postJson(route('admin.brands.store'), ['name' => 'Apple'])->assertOk();
    }

    public function test_product_with_variants_specifications_and_lifecycle(): void
    {
        $user = $this->editor(['catalog.approve']);
        $parent = Category::factory()->create(['organization_id' => $this->org->id, 'code' => 'phones']);
        $category = Category::factory()->create(['organization_id' => $this->org->id, 'code' => 'iphone', 'parent_id' => $parent->id]);
        $brand = Brand::factory()->create(['organization_id' => $this->org->id, 'name' => 'Apple']);
        $battery = AttributeDefinition::query()->create(['organization_id' => $this->org->id, 'category_id' => $parent->id, 'code' => 'battery_mah', 'label' => 'Battery', 'value_type' => 'number', 'unit' => 'mAh', 'comparison_order' => 1]);
        $esim = AttributeDefinition::query()->create(['organization_id' => $this->org->id, 'category_id' => $category->id, 'code' => 'esim', 'label' => 'eSIM', 'value_type' => 'boolean', 'comparison_order' => 2]);

        $this->actingAs($user)->postJson(route('admin.products.store'), [
            'category_id' => $category->id, 'brand_id' => $brand->id, 'model_code' => 'A3090',
            'name_en' => 'iPhone 16', 'name_ar' => 'آيفون 16', 'source_reference' => 'Apple spec sheet 2024',
        ])->assertOk();
        $product = Product::query()->where('model_code', 'A3090')->firstOrFail();
        $this->assertSame('draft', $product->status->value);

        // Publishing requires approval first and an active variant.
        $this->actingAs($user)->patchJson(route('admin.products.status', $product).'?status=published')->assertStatus(422);
        $this->actingAs($user)->patchJson(route('admin.products.status', $product).'?status=approved')->assertOk();
        $this->actingAs($user)->patchJson(route('admin.products.status', $product).'?status=published')
            ->assertStatus(422)->assertJsonPath('message', __('admin.errors.publish_requires_variant'));

        $this->actingAs($user)->postJson(route('admin.products.variants.store', $product), ['sku' => 'ip16-128-blk', 'variant_name' => '128GB Black', 'item_kind' => 'device', 'condition' => 'new'])->assertOk();
        $variant = ProductVariant::query()->where('sku', 'IP16-128-BLK')->firstOrFail();
        $this->actingAs($user)->postJson(route('admin.products.variants.store', $product), ['sku' => 'IP16-128-BLK', 'variant_name' => 'Dup', 'item_kind' => 'gadget', 'condition' => 'new'])
            ->assertStatus(422)->assertJsonValidationErrors(['sku', 'item_kind']);

        // Typed specifications: inherited (parent) and own attributes; type is enforced.
        $this->actingAs($user)->putJson(route('admin.variants.specifications', $variant), ['values' => [
            ['attribute_id' => $battery->id, 'value' => 'big', 'source_reference' => 'x'],
        ]])->assertStatus(422)->assertJsonValidationErrors(['values.0.value']);

        $this->actingAs($user)->putJson(route('admin.variants.specifications', $variant), ['values' => [
            ['attribute_id' => $battery->id, 'value' => '3561', 'source_reference' => 'Apple spec sheet'],
            ['attribute_id' => $esim->id, 'value' => '1', 'source_reference' => 'Apple spec sheet'],
        ]])->assertOk();
        $value = VariantAttribute::query()->where('attribute_id', $battery->id)->firstOrFail();
        $this->assertEquals(3561.0, $value->typedValue());
        $this->assertNull($value->text_value);
        $this->assertNull($value->boolean_value);
        $this->assertTrue(VariantAttribute::query()->where('attribute_id', $esim->id)->value('boolean_value'));

        // Attributes from an unrelated category are rejected.
        $alien = AttributeDefinition::query()->create(['organization_id' => $this->org->id, 'category_id' => Category::factory()->create(['organization_id' => $this->org->id])->id, 'code' => 'x', 'label' => 'X', 'value_type' => 'text']);
        $this->actingAs($user)->putJson(route('admin.variants.specifications', $variant), ['values' => [['attribute_id' => $alien->id, 'value' => 'v', 'source_reference' => 's']]])
            ->assertStatus(422)->assertJsonValidationErrors(['values.0.attribute_id']);

        $this->actingAs($user)->patchJson(route('admin.products.status', $product).'?status=published')->assertOk();
        $this->assertSame('published', $product->fresh()->status->value);
        $this->actingAs($user)->deleteJson(route('admin.products.destroy', $product))->assertStatus(422);

        $this->actingAs($user)->get(route('admin.products.show', $product))->assertOk()->assertSee('IP16-128-BLK')->assertSee('3561');
        $this->assertDatabaseHas('audit_logs', ['event' => 'product.status_changed', 'auditable_id' => $product->id]);
    }

    public function test_product_relations_must_be_in_the_same_organization_and_unique(): void
    {
        $user = $this->editor();
        $category = Category::factory()->create(['organization_id' => $this->org->id]);
        $brand = Brand::factory()->create(['organization_id' => $this->org->id]);
        Product::factory()->create(['organization_id' => $this->org->id, 'category_id' => $category->id, 'brand_id' => $brand->id, 'model_code' => 'M-1']);

        $this->actingAs($user)->postJson(route('admin.products.store'), ['category_id' => Category::factory()->create()->id, 'brand_id' => Brand::factory()->create()->id, 'model_code' => 'X', 'name_en' => 'X', 'source_reference' => 'S'])
            ->assertStatus(422)->assertJsonValidationErrors(['category_id', 'brand_id']);
        $this->actingAs($user)->postJson(route('admin.products.store'), ['category_id' => $category->id, 'brand_id' => $brand->id, 'model_code' => 'M-1', 'name_en' => 'Y', 'source_reference' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['model_code', 'source_reference']);
    }

    public function test_status_changes_require_the_approve_permission(): void
    {
        $user = $this->editor();
        $product = Product::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($user)->patchJson(route('admin.products.status', $product).'?status=approved')->assertForbidden();
        $this->assertSame('draft', $product->fresh()->status->value);
    }

    public function test_product_search_and_filters(): void
    {
        $user = $this->editor();
        $product = Product::factory()->create(['organization_id' => $this->org->id, 'name_en' => 'Galaxy S25']);
        ProductVariant::factory()->create(['organization_id' => $this->org->id, 'product_id' => $product->id, 'sku' => 'GS25-UNIQ']);
        Product::factory()->count(3)->create(['organization_id' => $this->org->id]);

        $base = ['draw' => 1, 'start' => 0, 'length' => 10];
        $this->actingAs($user)->getJson(route('admin.products.index', $base + ['search' => ['value' => 'GS25-UNIQ']]))->assertJsonPath('recordsFiltered', 1);
        $this->actingAs($user)->getJson(route('admin.products.index', $base + ['filters' => ['brand_id' => $product->brand_id]]))->assertJsonPath('recordsFiltered', 1);
        $this->actingAs($user)->getJson(route('admin.products.index', $base + ['filters' => ['status' => 'published']]))->assertJsonPath('recordsFiltered', 0);
    }

    public function test_product_images_are_private_validated_and_reviewed(): void
    {
        Storage::fake('local');
        $editor = $this->editor();
        $approver = $this->editor(['catalog.approve']);
        $product = Product::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($editor)->postJson(route('admin.products.media.store', $product), ['image' => UploadedFile::fake()->image('a.png', 400, 400), 'alt_text' => 'Front view'])->assertOk();
        $asset = MediaAsset::query()->firstOrFail();
        Storage::disk('local')->assertExists($asset->object_key);
        $this->assertSame('pending', $asset->scan_status->value);
        $this->assertSame(64, strlen($asset->sha256));
        $this->assertFalse($asset->isPubliclyServable());

        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->actingAs($editor)->postJson(route('admin.products.media.store', $product), ['image' => $svg, 'alt_text' => 'x'])->assertStatus(422)->assertJsonValidationErrors(['image']);
        $this->actingAs($editor)->postJson(route('admin.products.media.store', $product), ['image' => UploadedFile::fake()->image('b.png', 100, 100)])->assertStatus(422)->assertJsonValidationErrors(['alt_text']);

        $placement = $product->media()->firstOrFail();
        $this->actingAs($editor)->get(route('admin.products.media.preview', [$product, $placement]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        // Marking an image clean is a review action that needs catalog.approve.
        $this->actingAs($editor)->putJson(route('admin.products.media.update', [$product, $placement]).'?scan_status=clean')->assertForbidden();
        $this->actingAs($approver)->putJson(route('admin.products.media.update', [$product, $placement]).'?scan_status=clean')->assertOk();
        $this->assertTrue($asset->fresh()->isPubliclyServable());

        $this->actingAs($editor)->deleteJson(route('admin.products.media.destroy', [$product, $placement]))->assertOk();
        Storage::disk('local')->assertMissing($asset->object_key);
        $this->assertDatabaseHas('audit_logs', ['event' => 'product.media_reviewed']);
    }

    public function test_catalog_api(): void
    {
        $this->apiAs($this->editor(['catalog.approve']));
        $category = Category::factory()->create(['organization_id' => $this->org->id]);
        $brand = Brand::factory()->create(['organization_id' => $this->org->id]);
        $foreign = Product::factory()->create();

        $id = $this->postJson('/api/v1/products', ['category_id' => $category->id, 'brand_id' => $brand->id, 'model_code' => 'API-1', 'name_en' => 'API Phone', 'source_reference' => 'Ref'])
            ->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.brand.id', $brand->id)->json('data.id');
        $variantId = $this->postJson("/api/v1/products/{$id}/variants", ['sku' => 'api-sku', 'variant_name' => 'Base', 'item_kind' => 'accessory', 'condition' => 'refurbished'])
            ->assertCreated()->assertJsonPath('data.sku', 'API-SKU')->json('data.id');
        $this->patchJson("/api/v1/variants/{$variantId}/status", ['status' => 'unavailable'])->assertOk()->assertJsonPath('data.status', 'unavailable');
        $this->patchJson("/api/v1/variants/{$variantId}/status", ['status' => 'retired'])->assertOk();
        $this->patchJson("/api/v1/variants/{$variantId}/status", ['status' => 'active'])->assertStatus(422)->assertJsonPath('error.code', 'business_rule_violation');

        $this->getJson("/api/v1/products/{$id}")->assertOk()->assertJsonPath('data.variants.0.sku', 'API-SKU');
        $this->getJson('/api/v1/products?filter[status]=draft&search=API')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/products/{$foreign->id}")->assertNotFound();
        $this->getJson('/api/v1/categories')->assertOk();
        $this->getJson('/api/v1/brands')->assertOk()->assertJsonPath('meta.total', 1);
    }
}
