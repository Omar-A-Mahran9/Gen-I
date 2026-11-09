<?php

namespace App\Http\Controllers\Admin\Business;

use App\Enums\ActiveStatus;
use App\Http\Requests\Business\StoreAttributeDefinitionRequest;
use App\Http\Requests\Business\StoreCategoryRequest;
use App\Http\Requests\Business\UpdateAttributeDefinitionRequest;
use App\Http\Requests\Business\UpdateCategoryRequest;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Services\Business\CatalogService;
use App\Support\DataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends BusinessController
{
    public function __construct(protected CatalogService $service) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', Category::class);

        if (! $request->expectsJson()) {
            return view('admin.business.categories.index', $this->formData($request));
        }

        return DataTable::of($this->service->categoryQuery($request->user(), $this->tableFilters($request)), $request)
            ->sortable(['code' => 'code', 'name' => 'name_en', 'status' => 'status'])
            ->defaultOrder('code', 'asc')
            ->map(fn (Category $c) => [
                'code' => '<span class="badge badge-light fw-bold" dir="ltr">'.e($c->code).'</span>',
                'name' => '<a href="'.e(route('admin.categories.show', $c)).'" class="text-gray-800 text-hover-primary fw-bold">'.e($c->displayName()).'</a>',
                'parent' => e($c->parent?->displayName() ?? '—'),
                'counts' => e(__('admin.categories.counts', ['products' => $c->products_count, 'attributes' => $c->attribute_definitions_count])),
                'status' => view('admin.business.partials.status-badge', ['status' => $c->status])->render(),
                'actions' => view('admin.business.partials.row-actions', ['model' => $c, 'route' => 'categories', 'param' => $c,
                    'editModal' => '#geni_category_modal', 'record' => $c->only(['id', 'organization_id', 'code', 'parent_id', 'name_ar', 'name_en'])])->render(),
            ])
            ->toResponse();
    }

    public function store(StoreCategoryRequest $request): JsonResponse|RedirectResponse
    {
        $category = $this->service->saveCategory($request->payload(), $request->user());

        return $this->success($request, __('admin.messages.created'), route('admin.categories.show', $category));
    }

    public function show(Request $request, Category $category): View
    {
        Gate::authorize('view', $category);

        return view('admin.business.categories.show', [
            'category' => $category->load(['parent', 'children', 'attributeDefinitions', 'organization'])->loadCount('products'),
            'inherited' => $category->parent_id ? AttributeDefinition::query()->whereIn('category_id', array_slice($category->lineageIds(), 1))->with('category')->orderBy('comparison_order')->get() : collect(),
        ] + $this->formData($request));
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse|RedirectResponse
    {
        $this->service->saveCategory($request->payload(), $request->user(), $category);

        return $this->success($request, __('admin.messages.updated'), route('admin.categories.show', $category));
    }

    public function status(Request $request, Category $category): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $category);
        $data = $request->validate(['status' => ['required', Rule::enum(ActiveStatus::class)]]);
        $this->service->setCategoryStatus($category, ActiveStatus::from($data['status']), $request->user());

        return $this->success($request, __('admin.messages.status_changed'));
    }

    public function destroy(Request $request, Category $category): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $category);
        $this->service->deleteCategory($category, $request->user());

        return $this->success($request, __('admin.messages.deleted'), route('admin.categories.index'));
    }

    public function storeAttribute(StoreAttributeDefinitionRequest $request, Category $category): JsonResponse|RedirectResponse
    {
        $this->service->saveAttribute($category, $request->validated(), $request->user());

        return $this->success($request, __('admin.messages.created'), route('admin.categories.show', $category));
    }

    public function updateAttribute(UpdateAttributeDefinitionRequest $request, Category $category, AttributeDefinition $attribute): JsonResponse|RedirectResponse
    {
        abort_unless((int) $attribute->category_id === (int) $category->id, 404);
        $this->service->saveAttribute($category, $request->validated(), $request->user(), $attribute);

        return $this->success($request, __('admin.messages.updated'), route('admin.categories.show', $category));
    }

    public function destroyAttribute(Request $request, Category $category, AttributeDefinition $attribute): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $category);
        abort_unless((int) $attribute->category_id === (int) $category->id, 404);
        $this->service->deleteAttribute($attribute, $request->user());

        return $this->success($request, __('admin.messages.deleted'), route('admin.categories.show', $category));
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Options for the add/edit modal: organizations (super admins) and candidate parents, each tagged
     * with its organization; the modal hides other organizations' categories and the record itself.
     * Cycles are still rejected by CatalogService.
     *
     * @return array<string, mixed>
     */
    protected function formData(Request $request): array
    {
        return [
            'organizations' => $this->organizationOptions($request->user()),
            'parents' => Category::query()->forUser($request->user())->orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'organization_id']),
        ];
    }
}
