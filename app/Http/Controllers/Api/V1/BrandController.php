<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActiveStatus;
use App\Http\Requests\Business\StoreBrandRequest;
use App\Http\Requests\Business\UpdateBrandRequest;
use App\Http\Resources\Api\V1\BrandResource;
use App\Models\Brand;
use App\Services\Business\CatalogService;
use App\Support\ApiListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BrandController extends ApiController
{
    public function __construct(protected CatalogService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Brand::class);
        $query = $this->service->brandQuery($request->user(), ApiListing::filters($request, ['status', 'organization_id']));

        return $this->successWithPagination(__('api.messages.retrieved'), BrandResource::collection(ApiListing::paginate($query, $request, ['name' => 'name'], 'name')));
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        return $this->success(__('api.messages.created'), new BrandResource($this->service->saveBrand($request->payload(), $request->user())), Response::HTTP_CREATED);
    }

    public function show(Brand $brand): JsonResponse
    {
        Gate::authorize('view', $brand);

        return $this->success(__('api.messages.retrieved'), new BrandResource($brand->loadCount('products')));
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        return $this->success(__('api.messages.updated'), new BrandResource($this->service->saveBrand($request->payload(), $request->user(), $brand)));
    }

    public function status(Request $request, Brand $brand): JsonResponse
    {
        Gate::authorize('update', $brand);
        $data = $request->validate(['status' => ['required', Rule::enum(ActiveStatus::class)]]);

        return $this->success(__('api.messages.status_changed'), new BrandResource($this->service->setBrandStatus($brand, ActiveStatus::from($data['status']), $request->user())));
    }

    public function destroy(Request $request, Brand $brand): JsonResponse
    {
        Gate::authorize('delete', $brand);
        $this->service->deleteBrand($brand, $request->user());

        return $this->success(__('api.messages.deleted'));
    }
}
