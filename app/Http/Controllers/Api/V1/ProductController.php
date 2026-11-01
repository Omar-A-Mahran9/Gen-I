<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\ProductMediaRequest;
use App\Http\Requests\Business\ProductRequest;
use App\Http\Requests\Business\VariantAttributesRequest;
use App\Http\Requests\Business\VariantRequest;
use App\Http\Resources\Api\V1\ProductMediaResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Http\Resources\Api\V1\VariantResource;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Services\Business\ProductService;
use App\Support\ApiListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function __construct(protected ProductService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);
        $query = $this->service->query($request->user(), ApiListing::filters($request, ['status', 'organization_id', 'category_id', 'brand_id', 'item_kind']));

        return ProductResource::collection(ApiListing::paginate($query, $request, ['name_en' => 'name_en', 'model_code' => 'model_code', 'created_at' => 'created_at', 'updated_at' => 'updated_at'], '-updated_at'));
    }

    public function store(ProductRequest $request): ProductResource
    {
        return new ProductResource($this->service->create($request->payload(), $request->user())->load(['category', 'brand']));
    }

    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return new ProductResource($product->load(['category', 'brand', 'variants.attributeValues.attributeDefinition', 'media.asset']));
    }

    public function update(ProductRequest $request, Product $product): ProductResource
    {
        return new ProductResource($this->service->update($product, $request->payload(), $request->user())->load(['category', 'brand']));
    }

    public function status(Request $request, Product $product): ProductResource
    {
        Gate::authorize('approve', $product);
        $data = $request->validate(['status' => ['required', Rule::enum(ProductStatus::class)]]);

        return new ProductResource($this->service->transition($product, ProductStatus::from($data['status']), $request->user()));
    }

    public function destroy(Request $request, Product $product): Response
    {
        Gate::authorize('delete', $product);
        $this->service->delete($product, $request->user());

        return response()->noContent();
    }

    public function variants(Product $product): AnonymousResourceCollection
    {
        Gate::authorize('view', $product);

        return VariantResource::collection($product->variants()->with('attributeValues.attributeDefinition')->orderBy('sku')->get());
    }

    public function storeVariant(VariantRequest $request, Product $product): VariantResource
    {
        return new VariantResource($this->service->saveVariant($product, $request->validated(), $request->user()));
    }

    public function updateVariant(VariantRequest $request, ProductVariant $variant): VariantResource
    {
        return new VariantResource($this->service->saveVariant($variant->product, $request->validated(), $request->user(), $variant));
    }

    public function variantStatus(Request $request, ProductVariant $variant): VariantResource
    {
        Gate::authorize('update', $variant->product);
        $data = $request->validate(['status' => ['required', Rule::enum(VariantStatus::class)]]);

        return new VariantResource($this->service->setVariantStatus($variant, VariantStatus::from($data['status']), $request->user()));
    }

    public function destroyVariant(Request $request, ProductVariant $variant): Response
    {
        Gate::authorize('delete', $variant->product);
        $this->service->deleteVariant($variant, $request->user());

        return response()->noContent();
    }

    public function syncSpecifications(VariantAttributesRequest $request, ProductVariant $variant): VariantResource
    {
        $this->service->syncVariantAttributes($variant, $request->validated('values'), $request->user());

        return new VariantResource($variant->load('attributeValues.attributeDefinition'));
    }

    public function storeMedia(ProductMediaRequest $request, Product $product): ProductMediaResource
    {
        return new ProductMediaResource($this->service->addMedia($product, $request->file('image'), $request->validated(), $request->user())->load('asset'));
    }

    public function updateMedia(ProductMediaRequest $request, Product $product, ProductMedia $media): ProductMediaResource
    {
        abort_unless((int) $media->product_id === (int) $product->id, 404);

        return new ProductMediaResource($this->service->updateMedia($media, $request->validated(), $request->user())->load('asset'));
    }

    public function destroyMedia(Request $request, Product $product, ProductMedia $media): Response
    {
        Gate::authorize('update', $product);
        abort_unless((int) $media->product_id === (int) $product->id, 404);
        $this->service->removeMedia($media, $request->user());

        return response()->noContent();
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
