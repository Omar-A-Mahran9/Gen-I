<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActiveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\BrandRequest;
use App\Http\Resources\Api\V1\BrandResource;
use App\Models\Brand;
use App\Services\Business\CatalogService;
use App\Support\ApiListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function __construct(protected CatalogService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Brand::class);
        $query = $this->service->brandQuery($request->user(), ApiListing::filters($request, ['status', 'organization_id']));

        return BrandResource::collection(ApiListing::paginate($query, $request, ['name' => 'name'], 'name'));
    }

    public function store(BrandRequest $request): BrandResource
    {
        return new BrandResource($this->service->saveBrand($request->payload(), $request->user()));
    }

    public function show(Brand $brand): BrandResource
    {
        Gate::authorize('view', $brand);

        return new BrandResource($brand->loadCount('products'));
    }

    public function update(BrandRequest $request, Brand $brand): BrandResource
    {
        return new BrandResource($this->service->saveBrand($request->payload(), $request->user(), $brand));
    }

    public function status(Request $request, Brand $brand): BrandResource
    {
        Gate::authorize('update', $brand);
        $data = $request->validate(['status' => ['required', Rule::enum(ActiveStatus::class)]]);

        return new BrandResource($this->service->setBrandStatus($brand, ActiveStatus::from($data['status']), $request->user()));
    }

    public function destroy(Request $request, Brand $brand): Response
    {
        Gate::authorize('delete', $brand);
        $this->service->deleteBrand($brand, $request->user());

        return response()->noContent();
    }
}
