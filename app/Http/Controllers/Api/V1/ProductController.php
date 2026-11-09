<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Http\Requests\Business\StoreProductMediaRequest;
use App\Http\Requests\Business\StoreProductRequest;
use App\Http\Requests\Business\StoreVariantRequest;
use App\Http\Requests\Business\UpdateProductMediaRequest;
use App\Http\Requests\Business\UpdateProductRequest;
use App\Http\Requests\Business\UpdateVariantRequest;
use App\Http\Requests\Business\VariantAttributesRequest;
use App\Http\Resources\Api\V1\ProductMediaResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Http\Resources\Api\V1\VariantResource;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Services\Business\ProductService;
use App\Support\ApiListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends ApiController
{
    public function __construct(protected ProductService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);
        $query = $this->service->query($request->user(), ApiListing::filters($request, ['status', 'organization_id', 'category_id', 'brand_id', 'item_kind']));

        return $this->successWithPagination(__('api.messages.retrieved'), ProductResource::collection(ApiListing::paginate($query, $request, ['name_en' => 'name_en', 'model_code' => 'model_code', 'created_at' => 'created_at', 'updated_at' => 'updated_at'], '-updated_at')));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        return $this->success(__('api.messages.created'), new ProductResource($this->service->create($request->payload(), $request->user())->load(['category', 'brand'])), Response::HTTP_CREATED);
    }

    public function show(Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        return $this->success(__('api.messages.retrieved'), new ProductResource($product->load(['category', 'brand', 'variants.attributeValues.attributeDefinition', 'media.asset'])));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        return $this->success(__('api.messages.updated'), new ProductResource($this->service->update($product, $request->payload(), $request->user())->load(['category', 'brand'])));
    }

    public function status(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('approve', $product);
        $data = $request->validate(['status' => ['required', Rule::enum(ProductStatus::class)]]);

        return $this->success(__('api.messages.status_changed'), new ProductResource($this->service->transition($product, ProductStatus::from($data['status']), $request->user())));
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('delete', $product);
        $this->service->delete($product, $request->user());

        return $this->success(__('api.messages.deleted'));
    }

    public function variants(Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        return $this->success(__('api.messages.retrieved'), VariantResource::collection($product->variants()->with('attributeValues.attributeDefinition')->orderBy('sku')->get()));
    }

    public function storeVariant(StoreVariantRequest $request, Product $product): JsonResponse
    {
        return $this->success(__('api.messages.created'), new VariantResource($this->service->saveVariant($product, $request->validated(), $request->user())), Response::HTTP_CREATED);
    }

    public function updateVariant(UpdateVariantRequest $request, ProductVariant $variant): JsonResponse
    {
        return $this->success(__('api.messages.updated'), new VariantResource($this->service->saveVariant($variant->product, $request->validated(), $request->user(), $variant)));
    }

    public function variantStatus(Request $request, ProductVariant $variant): JsonResponse
    {
        Gate::authorize('update', $variant->product);
        $data = $request->validate(['status' => ['required', Rule::enum(VariantStatus::class)]]);

        return $this->success(__('api.messages.status_changed'), new VariantResource($this->service->setVariantStatus($variant, VariantStatus::from($data['status']), $request->user())));
    }

    public function destroyVariant(Request $request, ProductVariant $variant): JsonResponse
    {
        Gate::authorize('delete', $variant->product);
        $this->service->deleteVariant($variant, $request->user());

        return $this->success(__('api.messages.deleted'));
    }

    public function syncSpecifications(VariantAttributesRequest $request, ProductVariant $variant): JsonResponse
    {
        $this->service->syncVariantAttributes($variant, $request->validated('values'), $request->user());

        return $this->success(__('api.messages.updated'), new VariantResource($variant->load('attributeValues.attributeDefinition')));
    }

    public function storeMedia(StoreProductMediaRequest $request, Product $product): JsonResponse
    {
        return $this->success(__('api.messages.created'), new ProductMediaResource($this->service->addMedia($product, $request->file('image'), $request->validated(), $request->user())->load('asset')), Response::HTTP_CREATED);
    }

    public function updateMedia(UpdateProductMediaRequest $request, Product $product, ProductMedia $media): JsonResponse
    {
        abort_unless((int) $media->product_id === (int) $product->id, 404);

        return $this->success(__('api.messages.updated'), new ProductMediaResource($this->service->updateMedia($media, $request->validated(), $request->user())->load('asset')));
    }

    public function destroyMedia(Request $request, Product $product, ProductMedia $media): JsonResponse
    {
        Gate::authorize('update', $product);
        abort_unless((int) $media->product_id === (int) $product->id, 404);
        $this->service->removeMedia($media, $request->user());

        return $this->success(__('api.messages.deleted'));
    }

    /**
     * Authenticated download of a stored product image (private disk).
     */
    public function downloadMedia(Product $product, ProductMedia $media): StreamedResponse
    {
        Gate::authorize('view', $product);
        abort_unless((int) $media->product_id === (int) $product->id, 404);
        abort_if($media->asset->scan_status->value === 'blocked', 404);

        return Storage::disk(MediaAsset::DISK)->response($media->asset->object_key, null, [
            'Content-Type' => $media->asset->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}
