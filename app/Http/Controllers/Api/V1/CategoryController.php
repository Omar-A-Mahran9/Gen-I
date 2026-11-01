<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActiveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\AttributeDefinitionRequest;
use App\Http\Requests\Business\CategoryRequest;
use App\Http\Resources\Api\V1\AttributeDefinitionResource;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Services\Business\CatalogService;
use App\Support\ApiListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct(protected CatalogService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Category::class);
        $query = $this->service->categoryQuery($request->user(), ApiListing::filters($request, ['status', 'organization_id', 'parent_id', 'root']));

        return CategoryResource::collection(ApiListing::paginate($query, $request, ['code' => 'code', 'name_en' => 'name_en'], 'code'));
    }

    public function store(CategoryRequest $request): CategoryResource
    {
        return new CategoryResource($this->service->saveCategory($request->payload(), $request->user()));
    }

    public function show(Category $category): CategoryResource
    {
        Gate::authorize('view', $category);

        return new CategoryResource($category->load('attributeDefinitions')->loadCount(['children', 'products']));
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        return new CategoryResource($this->service->saveCategory($request->payload(), $request->user(), $category));
    }

    public function status(Request $request, Category $category): CategoryResource
    {
        Gate::authorize('update', $category);
        $data = $request->validate(['status' => ['required', Rule::enum(ActiveStatus::class)]]);

        return new CategoryResource($this->service->setCategoryStatus($category, ActiveStatus::from($data['status']), $request->user()));
    }

    public function destroy(Request $request, Category $category): Response
    {
        Gate::authorize('delete', $category);
        $this->service->deleteCategory($category, $request->user());

        return response()->noContent();
    }

    public function storeAttribute(AttributeDefinitionRequest $request, Category $category): AttributeDefinitionResource
    {
        return new AttributeDefinitionResource($this->service->saveAttribute($category, $request->validated(), $request->user()));
    }

    public function updateAttribute(AttributeDefinitionRequest $request, Category $category, AttributeDefinition $attribute): AttributeDefinitionResource
    {
        abort_unless((int) $attribute->category_id === (int) $category->id, 404);

        return new AttributeDefinitionResource($this->service->saveAttribute($category, $request->validated(), $request->user(), $attribute));
    }

    public function destroyAttribute(Request $request, Category $category, AttributeDefinition $attribute): Response
    {
        Gate::authorize('update', $category);
        abort_unless((int) $attribute->category_id === (int) $category->id, 404);
        $this->service->deleteAttribute($attribute, $request->user());

        return response()->noContent();
    }
}
