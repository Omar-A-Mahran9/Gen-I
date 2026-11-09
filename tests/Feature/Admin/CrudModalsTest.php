<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Organization;
use App\Models\PriceList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsBusinessData;
use Tests\TestCase;

/**
 * Simple records are added/edited in modals on the list page (LMS crud_modal pattern);
 * complex records (branches, products, policies) keep dedicated pages.
 */
class CrudModalsTest extends TestCase
{
    use BuildsBusinessData, RefreshDatabase;

    protected User $admin;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->superAdmin();
        $this->org = Organization::factory()->create();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function modalModules(): array
    {
        return [
            'organizations' => ['organizations', 'geni_organization_modal'],
            'companies' => ['companies', 'geni_company_modal'],
            'categories' => ['categories', 'geni_category_modal'],
            'brands' => ['brands', 'geni_brand_modal'],
            'price lists' => ['price-lists', 'geni_price_list_modal'],
        ];
    }

    #[DataProvider('modalModules')]
    public function test_simple_modules_use_a_modal_instead_of_create_and_edit_pages(string $module, string $modal): void
    {
        $this->actingAs($this->admin)->get(route("admin.{$module}.index"))
            ->assertOk()
            ->assertSee('id="'.$modal.'"', false)
            ->assertSee('data-geni-crud-create="#'.$modal.'"', false)
            ->assertSee('data-geni-ajax-form', false);

        $this->assertFalse(app('router')->has("admin.{$module}.create"));
        $this->assertFalse(app('router')->has("admin.{$module}.edit"));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pageModules(): array
    {
        return ['branches' => ['branches'], 'products' => ['products'], 'policies' => ['policies']];
    }

    #[DataProvider('pageModules')]
    public function test_complex_modules_keep_dedicated_create_pages(string $module): void
    {
        $this->actingAs($this->admin)->get(route("admin.{$module}.create"))->assertOk()->assertSee('data-geni-ajax-form', false);
    }

    public function test_modal_create_and_update_go_through_the_same_validated_endpoints(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.brands.store'), ['organization_id' => $this->org->id, 'name' => 'Example'])
            ->assertOk()->assertJsonStructure(['message', 'redirect']);
        $brand = Brand::query()->where('name', 'Example')->sole();

        $this->actingAs($this->admin)->postJson(route('admin.brands.store'), ['organization_id' => $this->org->id, 'name' => ' example '])
            ->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['name']]);

        $this->actingAs($this->admin)->putJson(route('admin.brands.update', $brand), ['name' => 'Example Renamed'])->assertOk();
        $this->assertSame('Example Renamed', $brand->fresh()->name);

        $this->actingAs($this->admin)->postJson(route('admin.categories.store'), ['organization_id' => $this->org->id, 'code' => 'Root', 'name_en' => 'Root'])->assertOk();
        $root = Category::query()->where('code', 'root')->sole();
        $this->actingAs($this->admin)->putJson(route('admin.categories.update', $root), ['code' => 'root', 'name_en' => 'Root', 'parent_id' => $root->id])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['parent_id']]);
    }

    public function test_rows_and_show_pages_open_the_edit_modal_with_the_record(): void
    {
        $brand = Brand::factory()->create(['organization_id' => $this->org->id, 'name' => 'Row Brand']);
        $rows = $this->actingAs($this->admin)->getJson(route('admin.brands.index', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertOk()->json('data');
        $actions = $rows[0]['actions'];
        $this->assertStringContainsString('data-geni-crud-edit="#geni_brand_modal"', $actions);
        $this->assertStringContainsString(e(route('admin.brands.update', $brand)), $actions);
        $this->assertStringContainsString('&quot;name&quot;:&quot;Row Brand&quot;', $actions);
        $this->assertStringContainsString(e(route('admin.brands.status', $brand)), $actions, 'Brands have no show page, so the status toggle is a row action.');

        $company = Company::factory()->create(['organization_id' => $this->org->id]);
        $category = Category::factory()->create(['organization_id' => $this->org->id]);
        $list = PriceList::factory()->create(['organization_id' => $this->org->id]);

        foreach ([
            [route('admin.organizations.show', $this->org), 'geni_organization_modal'],
            [route('admin.companies.show', $company), 'geni_company_modal'],
            [route('admin.categories.show', $category), 'geni_category_modal'],
            [route('admin.price-lists.show', $list), 'geni_price_list_modal'],
        ] as [$url, $modal]) {
            $this->actingAs($this->admin)->get($url)->assertOk()->assertSee('data-geni-crud-edit="#'.$modal.'"', false)->assertSee('id="'.$modal.'"', false);
        }
    }

    public function test_dependent_options_carry_their_organization_for_modal_filtering(): void
    {
        $other = Organization::factory()->create();
        Category::factory()->create(['organization_id' => $other->id, 'name_en' => 'Other Org Category']);

        $this->actingAs($this->admin)->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('data-geni-filter-organization', false)
            ->assertSee('data-organization="'.$other->id.'"', false);
    }

    public function test_import_page_submits_by_ajax_with_inline_errors_and_a_flashed_report(): void
    {
        $user = $this->staffOf($this->org, ['catalog.view', 'catalog.import']);
        $this->actingAs($user)->get(route('admin.catalog.import.create'))->assertOk()->assertSee('data-geni-ajax-form', false)->assertSee('data-geni-error-for="file"', false);

        $this->actingAs($user)->postJson(route('admin.catalog.import.store'), [])
            ->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['file']]);

        $csv = "category_code,brand_name,model_code,product_name_en,product_source_reference,sku,variant_name,item_kind,condition\nmissing,Nobody,M1,Name,Ref,S1,Base,device,new\n";
        $this->actingAs($user)->post(route('admin.catalog.import.store'), ['file' => UploadedFile::fake()->createWithContent('c.csv', $csv)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('level', 'error')
            ->assertJsonPath('redirect', route('admin.catalog.import.create'))
            ->assertSessionHas('import_report', fn (array $report) => $report['status'] === 'quarantined' && $report['summary']['failed_rows'] === 1);
    }
}
