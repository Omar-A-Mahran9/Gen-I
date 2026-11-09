<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActiveStatus;
use App\Http\Requests\Business\StoreAttributeDefinitionRequest;
use App\Http\Requests\Business\StoreCategoryRequest;
use App\Http\Requests\Business\UpdateAttributeDefinitionRequest;
use App\Http\Requests\Business\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\AttributeDefinitionResource;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Services\Business\CatalogService;
use App\Support\ApiListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CategoryController extends ApiController
{
    public function __construct(protected CatalogService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Category::class);
        $query = $this->service->categoryQuery($request->user(), ApiListing::filters($request, ['status', 'organization_id', 'parent_id', 'root']));

        return $this->successWithPagination(__('api.messages.retrieved'), CategoryResource::collection(ApiListing::paginate($query, $request, ['code' => 'code', 'name_en' => 'name_en'], 'code')));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        return $this->success(__('api.messages.created'), new CategoryResource($this->service->saveCategory($request->payload(), $request->user())), Response::HTTP_CREATED);
    }

    public function show(Category $category): JsonResponse
    {
        Gate::authorize('view', $category);

        return $this->success(__('api.messages.retrieved'), new CategoryResource($category->load('attributeDefinitions')->loadCount(['children', 'products'])));
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        return $this->success(__('api.messages.updated'), new CategoryResource($this->service->saveCategory($request->payload(), $request->user(), $category)));
    }

    public function status(Request $request, Category $category): JsonResponse
    {
        Gate::authorize('update', $category);
        $data = $request->validate(['status' => ['required', Rule::enum(ActiveStatus::class)]]);

        return $this->success(__('api.messages.status_changed'), new CategoryResource($this->service->setCategoryStatus($category, ActiveStatus::from($data['status']), $request->user())));
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        Gate::authorize('delete', $category);
        $this->service->deleteCategory($category, $request->user());

        return $this->success(__('api.messages.deleted'));
    }

    public function storeAttribute(StoreAttributeDefinitionRequest $request, Category $category): JsonResponse
    {
        return $this->success(__('api.messages.created'), new AttributeDefinitionResource($this->service->saveAttribute($category, $request->validated(), $request->user())), Response::HTTP_CREATED);
    }

    public function updateAttribute(UpdateAttributeDefinitionRequest $request, Category $category, AttributeDefinition $attribute): JsonResponse
    {
        abort_unless((int) $attribute->category_id === (int) $category->id, 404);

        return $this->success(__('api.messages.updated'), new AttributeDefinitionResource($this->service->saveAttribute($category, $request->validated(), $request->user(), $attribute)));
    }

    public function destroyAttribute(Request $request, Category $category, AttributeDefinition $attribute): JsonResponse
    {
        Gate::authorize('update', $category);
        abort_unless((int) $attribute->category_id === (int) $category->id, 404);
        $this->service->deleteAttribute($attribute, $request->user());

        return $this->success(__('api.messages.deleted'));
    }
}
