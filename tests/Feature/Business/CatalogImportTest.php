<?php

namespace Tests\Feature\Business;

use App\Models\AttributeDefinition;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsBusinessData;
use Tests\TestCase;

/**
 * Catalog import contract v1 (docs/catalog-import-contract.md). All data is synthetic.
 */
class CatalogImportTest extends TestCase
{
    use BuildsBusinessData, RefreshDatabase;

    protected const HEADER = 'category_code,brand_name,model_code,product_name_en,product_name_ar,product_source_reference,sku,variant_name,item_kind,condition,spec:storage_gb,spec:has_5g,spec_source_reference';

    protected Organization $org;

    protected Category $category;

    protected Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->category = Category::factory()->create(['organization_id' => $this->org->id, 'code' => 'test-phones']);
        $this->brand = Brand::factory()->create(['organization_id' => $this->org->id, 'name' => 'Acme Devices']);

        foreach ([['storage_gb', 'number', 'GB'], ['has_5g', 'boolean', null], ['finish', 'text', null]] as $i => [$code, $type, $unit]) {
            AttributeDefinition::query()->create([
                'organization_id' => $this->org->id, 'category_id' => $this->category->id,
                'code' => $code, 'label' => $code, 'value_type' => $type, 'unit' => $unit, 'comparison_order' => $i,
            ]);
        }
    }

    protected function importer(): User
    {
        return $this->apiAs($this->staffOf($this->org, ['catalog.view', 'catalog.import']));
    }

    /**
     * @param  array<int, string>  $rows
     */
    protected function csv(array $rows, string $header = self::HEADER): string
    {
        return implode("\n", array_merge([$header], $rows))."\n";
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function upload(string $content, array $extra = [], string $name = 'catalog.csv'): TestResponse
    {
        return $this->post('/api/v1/catalog/imports', array_merge(['file' => UploadedFile::fake()->createWithContent($name, $content)], $extra), ['Accept' => 'application/json', 'Accept-Language' => 'en']);
    }

    /**
     * @return array<int, string>
     */
    protected function validRows(): array
    {
        return [
            'test-phones,Acme Devices,AX-1,Acme X1,أكمي إكس 1,Supplier sheet 2026-11,ax1-128-blk,128GB Black,device,new,128,true,Spec sheet A',
            'test-phones,acme  devices,AX-1,Acme X1,,Supplier sheet 2026-11,AX1-256-BLK,256GB Black,device,new,256,true,Spec sheet A',
            'test-phones,Acme Devices,AX-CASE,Acme X1 Case,,Supplier sheet 2026-11,AXC-01,Clear case,accessory,new,,,',
        ];
    }

    public function test_valid_file_creates_draft_products_and_distinct_variants_with_their_own_specifications(): void
    {
        $user = $this->importer();

        $this->upload($this->csv($this->validRows()))
            ->assertOk()
            ->assertJsonPath('data.status', 'committed')
            ->assertJsonPath('data.mode', 'atomic')
            ->assertJsonPath('data.summary.total_rows', 3)
            ->assertJsonPath('data.summary.created_rows', 3)
            ->assertJsonPath('data.summary.failed_rows', 0)
            ->assertJsonPath('data.summary.products_created', 2)
            ->assertJsonPath('data.summary.specifications_written', 4)
            ->assertJsonPath('data.errors', [])
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'The catalog import was saved.')
            ->assertJsonStructure(['success', 'data' => ['file' => ['name', 'sha256'], 'summary', 'errors', 'contract_version'], 'message']);

        $product = Product::query()->where('model_code', 'AX-1')->sole();
        $this->assertSame('draft', $product->status->value);
        $this->assertSame('أكمي إكس 1', $product->name_ar);
        $this->assertSame(['AX1-128-BLK', 'AX1-256-BLK'], $product->variants()->orderBy('sku')->pluck('sku')->all());

        $storage = fn (string $sku): float => (float) VariantAttribute::query()
            ->whereHas('variant', fn ($q) => $q->where('sku', $sku))
            ->whereHas('attributeDefinition', fn ($q) => $q->where('code', 'storage_gb'))
            ->value('number_value');
        $this->assertSame(128.0, $storage('AX1-128-BLK'));
        $this->assertSame(256.0, $storage('AX1-256-BLK'));
        $this->assertSame(0, ProductVariant::query()->where('sku', 'AXC-01')->sole()->attributeValues()->count());

        $this->assertSame(0, Price::query()->count(), 'An import never creates prices.');
        $this->assertDatabaseHas('audit_logs', ['event' => 'catalog.imported', 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'product.created']);
    }

    public function test_reimporting_the_same_file_is_idempotent_and_changes_are_reported_as_updates(): void
    {
        $this->importer();
        $this->upload($this->csv($this->validRows()))->assertOk();
        $verifiedAt = VariantAttribute::query()->value('verified_at');
        $this->travel(1)->hours();

        $this->upload($this->csv($this->validRows()))
            ->assertOk()
            ->assertJsonPath('data.summary.unchanged_rows', 3)
            ->assertJsonPath('data.summary.created_rows', 0)
            ->assertJsonPath('data.summary.specifications_written', 0);
        $this->assertEquals($verifiedAt, VariantAttribute::query()->value('verified_at'), 'Unchanged specifications keep their verification time.');

        $rows = $this->validRows();
        $rows[1] = str_replace('256GB Black', '256GB Midnight', $rows[1]);
        $this->upload($this->csv($rows))
            ->assertOk()
            ->assertJsonPath('data.summary.updated_rows', 1)
            ->assertJsonPath('data.summary.unchanged_rows', 2);

        $this->assertSame('256GB Midnight', ProductVariant::query()->where('sku', 'AX1-256-BLK')->value('variant_name'));
        $this->assertSame(3, ProductVariant::query()->count());
        $this->assertSame(2, Product::query()->count());
    }

    public function test_empty_optional_cells_never_clear_existing_values(): void
    {
        $this->importer();
        $this->upload($this->csv($this->validRows()))->assertOk();

        $rows = array_map(fn (string $row) => str_replace('أكمي إكس 1', '', $row), $this->validRows());
        $this->upload($this->csv($rows))->assertOk();

        $this->assertSame('أكمي إكس 1', Product::query()->where('model_code', 'AX-1')->value('name_ar'));
        $this->assertSame(4, VariantAttribute::query()->count());
    }

    public function test_file_level_problems_return_the_validation_envelope(): void
    {
        $this->importer();

        $this->upload('%PDF-1.4 binary', [], 'catalog.pdf')
            ->assertStatus(422)->assertJsonPath('code', 'validation_failed')->assertJsonStructure(['errors' => ['file']]);

        $this->post('/api/v1/catalog/imports', [], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['file']]);

        $this->upload("category_code,brand_name\n\xC3\x28,x\n")
            ->assertStatus(422)->assertJsonPath('errors.file.0', 'The file must be UTF-8 encoded.');

        $this->upload("category_code;brand_name;sku\ntest-phones;Acme;X\n")
            ->assertStatus(422)->assertJsonPath('errors.file.0', 'Columns must be separated by commas, not semicolons.');

        $this->upload($this->csv([]))
            ->assertStatus(422)->assertJsonPath('errors.file.0', 'The file contains no data rows.');

        $this->upload('', [], 'empty.csv')->assertStatus(422);

        $this->upload($this->csv(['x'], 'mode,dry_run'), ['mode' => 'everything'])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['mode']]);
    }

    public function test_header_must_contain_required_columns_and_only_known_columns(): void
    {
        $this->importer();

        $response = $this->upload($this->csv(['a,b,c'], 'category_code,brand_name,price'))->assertStatus(422);
        $messages = implode(' ', $response->json('errors.file'));
        $this->assertStringContainsString('Missing required columns: model_code, product_name_en, product_source_reference, sku, variant_name, item_kind, condition', $messages);
        $this->assertStringContainsString('Unknown columns: price', $messages);

        $this->upload($this->csv(['x'], self::HEADER.',spec:battery_mah'))
            ->assertStatus(422)->assertJsonPath('errors.file.0', 'No specification attribute exists with code: battery_mah.');

        $this->upload($this->csv(['x'], self::HEADER.',sku'))
            ->assertStatus(422)->assertJsonPath('errors.file.0', 'Repeated columns: sku.');

        $this->assertSame(0, Product::query()->count());
    }

    public function test_row_errors_quarantine_an_atomic_batch_with_row_numbers_columns_and_codes(): void
    {
        $this->importer();
        $rows = $this->validRows();
        $rows[] = 'test-phones,Acme Devices,AX-2,,,Ref,BAD SKU!,Base,gadget,new,lots,maybe,Spec';

        $response = $this->upload($this->csv($rows))
            ->assertStatus(422)
            ->assertJsonPath('code', 'import_rejected')
            ->assertJsonPath('data.status', 'quarantined')
            ->assertJsonPath('data.summary.total_rows', 4)
            ->assertJsonPath('data.summary.failed_rows', 1)
            ->assertJsonPath('data.summary.created_rows', 0)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'code', 'message', 'data' => ['summary', 'errors' => [['row', 'column', 'code', 'message']]], 'request_id']);

        $errors = collect($response->json('data.errors'));
        $this->assertTrue($errors->every(fn (array $error) => $error['row'] === 5), 'The header is row 1, so the fourth data row is row 5.');
        $byColumn = $errors->keyBy('column');
        $this->assertSame('required', $byColumn['product_name_en']['code']);
        $this->assertSame('invalid_format', $byColumn['sku']['code']);
        $this->assertSame('invalid_value', $byColumn['item_kind']['code']);
        $this->assertSame('invalid_type', $byColumn['spec:storage_gb']['code']);
        $this->assertSame('invalid_type', $byColumn['spec:has_5g']['code']);

        $this->assertSame(0, Product::query()->count(), 'Nothing is saved when an atomic batch is quarantined.');
        $this->assertSame(0, ProductVariant::query()->count());
    }

    public function test_partial_mode_commits_valid_rows_and_reports_the_rejected_ones(): void
    {
        $this->importer();
        $rows = $this->validRows();
        $rows[] = 'test-phones,Acme Devices,AX-2,Acme X2,,Ref,AX2-1,Base,device,broken,,,';

        $this->upload($this->csv($rows), ['mode' => 'partial'])
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_committed')
            ->assertJsonPath('data.summary.created_rows', 3)
            ->assertJsonPath('data.summary.failed_rows', 1)
            ->assertJsonPath('data.errors.0.row', 5)
            ->assertJsonPath('data.errors.0.column', 'condition');

        $this->assertSame(3, ProductVariant::query()->count());
        $this->assertNull(Product::query()->where('model_code', 'AX-2')->first());
    }

    public function test_partial_mode_with_only_invalid_rows_is_quarantined(): void
    {
        $this->importer();

        $this->upload($this->csv(['test-phones,Nobody,AX-9,Name,,Ref,AX9,Base,device,new,,,']), ['mode' => 'partial'])
            ->assertStatus(422)->assertJsonPath('data.status', 'quarantined');
    }

    public function test_every_occurrence_of_a_repeated_sku_is_rejected(): void
    {
        $this->importer();
        $rows = $this->validRows();
        $rows[] = 'test-phones,Acme Devices,AX-1,Acme X1,,Supplier sheet 2026-11,AX1-128-blk,128GB Black copy,device,new,,,';

        $response = $this->upload($this->csv($rows), ['mode' => 'partial'])
            ->assertOk()
            ->assertJsonPath('data.summary.duplicate_rows', 2)
            ->assertJsonPath('data.summary.failed_rows', 2)
            ->assertJsonPath('data.summary.created_rows', 2);

        $duplicates = collect($response->json('data.errors'))->where('code', 'duplicate_in_file');
        $this->assertSame([2, 5], $duplicates->pluck('row')->sort()->values()->all());
        $this->assertNull(ProductVariant::query()->where('sku', 'AX1-128-BLK')->first());
    }

    public function test_unknown_or_foreign_category_and_brand_references_are_rejected(): void
    {
        $this->importer();
        $foreignCategory = Category::factory()->create(['code' => 'foreign-cat']);
        $foreignBrand = Brand::factory()->create(['name' => 'Foreign Brand']);

        $response = $this->upload($this->csv([
            'missing-cat,Acme Devices,AX-1,Acme X1,,Ref,S1,Base,device,new,,,',
            "{$foreignCategory->code},Acme Devices,AX-2,Acme X2,,Ref,S2,Base,device,new,,,",
            "test-phones,{$foreignBrand->name},AX-3,Acme X3,,Ref,S3,Base,device,new,,,",
        ]))->assertStatus(422);

        $codes = collect($response->json('data.errors'))->map(fn ($e) => $e['row'].':'.$e['column'].':'.$e['code'])->all();
        $this->assertSame(['2:category_code:unknown_category', '3:category_code:unknown_category', '4:brand_name:unknown_brand'], $codes);
    }

    public function test_specifications_must_apply_to_the_row_category_and_cite_a_source(): void
    {
        $this->importer();
        $other = Category::factory()->create(['organization_id' => $this->org->id, 'code' => 'test-tvs']);
        $child = Category::factory()->create(['organization_id' => $this->org->id, 'code' => 'test-phones-rugged', 'parent_id' => $this->category->id]);

        $response = $this->upload($this->csv([
            "{$other->code},Acme Devices,TV-1,Acme TV,,Ref,TV1,55in,device,new,64,,Spec",
            'test-phones,Acme Devices,AX-1,Acme X1,,Ref,AX1,Base,device,new,128,,',
            "{$child->code},Acme Devices,AX-R,Acme Rugged,,Ref,AXR,Base,device,new,64,false,Spec",
        ]), ['mode' => 'partial'])->assertOk();

        $errors = collect($response->json('data.errors'))->keyBy('row');
        $this->assertSame('attribute_not_applicable', $errors[2]['code']);
        $this->assertSame('spec:storage_gb', $errors[2]['column']);
        $this->assertSame('spec_source_reference', $errors[3]['column']);
        $this->assertArrayNotHasKey(4, $errors->all(), 'Attributes of an ancestor category apply to its children.');
        $this->assertSame(2, ProductVariant::query()->where('sku', 'AXR')->sole()->attributeValues()->count());
    }

    public function test_rows_of_the_same_product_must_agree_on_product_details(): void
    {
        $this->importer();
        $rows = $this->validRows();
        $rows[1] = str_replace('Acme X1,', 'Acme X1 Pro,', $rows[1]);

        $response = $this->upload($this->csv($rows), ['mode' => 'partial'])->assertOk()->assertJsonPath('data.summary.created_rows', 1);

        $conflicts = collect($response->json('data.errors'))->where('code', 'conflicting_product_data');
        $this->assertSame([2, 3], $conflicts->pluck('row')->values()->all());
        $this->assertSame('product_name_en', $conflicts->first()['column']);
        $this->assertNull(Product::query()->where('model_code', 'AX-1')->first());
    }

    public function test_an_existing_sku_cannot_be_moved_to_another_product(): void
    {
        $this->importer();
        $existing = ProductVariant::factory()->create(['organization_id' => $this->org->id, 'sku' => 'TAKEN-1']);

        $this->upload($this->csv(['test-phones,Acme Devices,AX-1,Acme X1,,Ref,taken-1,Base,device,new,,,']))
            ->assertStatus(422)
            ->assertJsonPath('data.errors.0.code', 'sku_belongs_to_other_product')
            ->assertJsonPath('data.errors.0.column', 'sku');

        $this->assertSame($existing->product_id, $existing->fresh()->product_id);
    }

    public function test_a_failure_while_writing_rolls_back_the_whole_atomic_batch(): void
    {
        $this->importer();
        $this->upload($this->csv([$this->validRows()[0]]))->assertOk();
        $tvs = Category::factory()->create(['organization_id' => $this->org->id, 'code' => 'test-tvs']);

        // Rows 2–3 are new and valid; row 4 tries to move AX-1 (which has specifications) to another
        // category — a business rule only ProductService can check while writing.
        $rows = [
            'test-phones,Acme Devices,NEW-1,New One,,Ref,NEW1,Base,device,new,,,',
            'test-phones,Acme Devices,NEW-2,New Two,,Ref,NEW2,Base,device,new,,,',
            "{$tvs->code},Acme Devices,AX-1,Acme X1,,Supplier sheet 2026-11,AX1-128-BLK,128GB Black,device,new,,,",
        ];

        $this->upload($this->csv($rows))
            ->assertStatus(422)
            ->assertJsonPath('data.status', 'quarantined')
            ->assertJsonPath('data.summary.created_rows', 0)
            ->assertJsonPath('data.errors.0.row', 4)
            ->assertJsonPath('data.errors.0.code', 'business_rule_violation');

        $this->assertSame(0, Product::query()->whereIn('model_code', ['NEW-1', 'NEW-2'])->count(), 'Rows written before the failure are rolled back.');
        $this->assertSame($this->category->id, Product::query()->where('model_code', 'AX-1')->value('category_id'));
        $this->assertSame(0, AuditLog::query()->where('event', 'product.created')->whereJsonContains('properties->model_code', 'NEW-1')->count());

        $this->upload($this->csv($rows), ['mode' => 'partial'])
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_committed')
            ->assertJsonPath('data.summary.created_rows', 2)
            ->assertJsonPath('data.summary.failed_rows', 1);
    }

    public function test_dry_run_reports_without_saving_anything(): void
    {
        $this->importer();
        $rows = $this->validRows();
        $rows[] = 'test-phones,Acme Devices,AX-2,Acme X2,,Ref,AX2,Base,device,new,abc,,Spec';

        $this->upload($this->csv($rows), ['dry_run' => '1'])
            ->assertOk()
            ->assertJsonPath('data.status', 'validated')
            ->assertJsonPath('data.dry_run', true)
            ->assertJsonPath('data.summary.valid_rows', 3)
            ->assertJsonPath('data.summary.failed_rows', 1);

        $this->assertSame(0, Product::query()->count());
        $this->assertDatabaseMissing('audit_logs', ['event' => 'catalog.imported']);
    }

    public function test_malformed_rows_and_the_row_limit_are_reported(): void
    {
        $this->importer();

        $this->upload($this->csv(['test-phones,Acme Devices,only-three']), ['mode' => 'partial'])
            ->assertStatus(422)
            ->assertJsonPath('data.errors.0.code', 'malformed_row')
            ->assertJsonPath('data.errors.0.row', 2);

        config(['admin.catalog_import.max_rows' => 2]);
        $this->upload($this->csv($this->validRows()))
            ->assertStatus(422)->assertJsonPath('errors.file.0', 'The file has 3 rows; the maximum is 2.');
    }

    public function test_quoted_cells_bom_and_blank_lines_are_handled(): void
    {
        $this->importer();
        $content = "\xEF\xBB\xBF".self::HEADER."\r\n\r\n".'test-phones,Acme Devices,AX-Q,"Acme ""Quote"", Edition",,Ref,AXQ,Base,device,new,,,'."\r\n";

        $this->upload($content)->assertOk()->assertJsonPath('data.summary.total_rows', 1);
        $this->assertSame('Acme "Quote", Edition', Product::query()->where('model_code', 'AX-Q')->value('name_en'));
    }

    public function test_import_requires_the_import_permission_and_respects_organization_scope(): void
    {
        $this->apiAs($this->staffOf($this->org, ['catalog.view', 'catalog.create', 'catalog.update']));
        $this->upload($this->csv($this->validRows()))->assertForbidden()->assertJsonPath('code', 'forbidden');

        $this->apiAs($this->superAdmin());
        $this->upload($this->csv($this->validRows()))
            ->assertStatus(422)->assertJsonStructure(['errors' => ['organization_id']]);
        $this->upload($this->csv($this->validRows()), ['organization_id' => $this->org->id])->assertOk();
        $this->assertSame($this->org->id, Product::query()->value('organization_id'));

        $this->assertTrue($this->superAdmin()->can('catalog.import'));
    }

    public function test_template_lists_contract_columns_and_category_specifications(): void
    {
        $this->importer();

        $blank = $this->get('/api/v1/catalog/imports/template', ['Accept' => 'application/json'])
            ->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        $this->assertSame("\xEF\xBB\xBFcategory_code,brand_name,model_code,product_name_en,product_source_reference,sku,variant_name,item_kind,condition,product_name_ar,product_description,spec_source_reference,spec_verified_at\r\n", $blank);

        $forCategory = $this->get('/api/v1/catalog/imports/template?category_code=TEST-PHONES', ['Accept' => 'application/json'])->assertOk()->getContent();
        $this->assertStringEndsWith(',spec:storage_gb,spec:has_5g,spec:finish'."\r\n", $forCategory);

        $this->getJson('/api/v1/catalog/imports/template?category_code=unknown')->assertStatus(422)->assertJsonStructure(['errors' => ['category_code']]);
    }

    public function test_admin_import_page_runs_the_same_import_and_shows_the_report(): void
    {
        $user = $this->staffOf($this->org, ['catalog.view', 'catalog.import']);
        $this->actingAs($user)->get(route('admin.catalog.import.create'))->assertOk()->assertSee('spec:&lt;attribute_code&gt;', false);

        $this->actingAs($user)->post(route('admin.catalog.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('catalog.csv', $this->csv($this->validRows())),
            'mode' => 'atomic',
        ])->assertRedirect(route('admin.catalog.import.create'))->assertSessionHas('success');

        $this->assertSame(3, ProductVariant::query()->count());
        $this->actingAs($user)->get(route('admin.catalog.import.create'))->assertOk();

        $this->actingAs($this->staffOf($this->org, ['catalog.view']))->get(route('admin.catalog.import.create'))->assertForbidden();
    }
}
