<?php

namespace App\Http\Controllers\Admin\Business;

use App\Enums\PolicyStatus;
use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Http\Requests\Business\StoreProductMediaRequest;
use App\Http\Requests\Business\StoreProductRequest;
use App\Http\Requests\Business\StoreVariantRequest;
use App\Http\Requests\Business\UpdateProductMediaRequest;
use App\Http\Requests\Business\UpdateProductRequest;
use App\Http\Requests\Business\UpdateVariantRequest;
use App\Http\Requests\Business\VariantAttributesRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\PolicyVersion;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Services\Business\ProductService;
use App\Support\DataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends BusinessController
{
    public function __construct(protected ProductService $service) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', Product::class);

        if (! $request->expectsJson()) {
            return view('admin.business.products.index', [
                'organizations' => $this->organizationOptions($request->user()),
                'categories' => Category::query()->forUser($request->user())->orderBy('name_en')->get(),
                'brands' => Brand::query()->forUser($request->user())->orderBy('name')->pluck('name', 'id'),
            ]);
        }

        return DataTable::of($this->service->query($request->user(), $this->tableFilters($request)), $request)
            ->sortable(['name' => 'name_en', 'model_code' => 'model_code', 'status' => 'status', 'updated_at' => 'updated_at'])
            ->defaultOrder('updated_at')
            ->map(fn (Product $p) => [
                'name' => '<a href="'.e(route('admin.products.show', $p)).'" class="text-gray-800 text-hover-primary fw-bold">'.e($p->displayName()).'</a><div class="text-muted fs-7" dir="ltr">'.e($p->model_code).'</div>',
                'model_code' => e($p->model_code),
                'category' => e($p->category?->displayName()),
                'brand' => e($p->brand?->name),
                'variants' => (int) $p->variants_count,
                'status' => view('admin.business.partials.status-badge', ['status' => $p->status])->render(),
                'updated_at' => e(format_date($p->updated_at)),
                'actions' => view('admin.business.partials.row-actions', ['model' => $p, 'route' => 'products', 'param' => $p])->render(),
            ])
            ->toResponse();
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Product::class);

        return view('admin.business.products.form', $this->formData($request, new Product));
    }

    public function store(StoreProductRequest $request): JsonResponse|RedirectResponse
    {
        $product = $this->service->create($request->payload(), $request->user());

        return $this->success($request, __('admin.messages.created'), route('admin.products.show', $product));
    }

    public function show(Request $request, Product $product): View
    {
        Gate::authorize('view', $product);
        $product->load(['category', 'brand', 'organization', 'variants.attributeValues.attributeDefinition', 'variants.warrantyPolicy', 'media.asset', 'media.variant']);

        return view('admin.business.products.show', [
            'product' => $product,
            'attributes' => $this->service->applicableAttributes($product),
            'transitions' => ProductService::PRODUCT_TRANSITIONS[$product->status->value] ?? [],
            'variantTransitions' => ProductService::VARIANT_TRANSITIONS,
            'warrantyPolicies' => $this->approvedPolicies($product->organization_id),
        ]);
    }

    public function edit(Request $request, Product $product): View
    {
        Gate::authorize('update', $product);

        return view('admin.business.products.form', $this->formData($request, $product));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse|RedirectResponse
    {
        $this->service->update($product, $request->payload(), $request->user());

        return $this->success($request, __('admin.messages.updated'), route('admin.products.show', $product));
    }

    public function status(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        Gate::authorize('approve', $product);
        $data = $request->validate(['status' => ['required', Rule::enum(ProductStatus::class)]]);
        $this->service->transition($product, ProductStatus::from($data['status']), $request->user());

        return $this->success($request, __('admin.messages.status_changed'));
    }

    public function destroy(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $product);
        $this->service->delete($product, $request->user());

        return $this->success($request, __('admin.messages.deleted'), route('admin.products.index'));
    }

    public function storeVariant(StoreVariantRequest $request, Product $product): JsonResponse|RedirectResponse
    {
        $this->service->saveVariant($product, $request->validated(), $request->user());

        return $this->success($request, __('admin.messages.created'), route('admin.products.show', $product));
    }

    public function updateVariant(UpdateVariantRequest $request, ProductVariant $variant): JsonResponse|RedirectResponse
    {
        $this->service->saveVariant($variant->product, $request->validated(), $request->user(), $variant);

        return $this->success($request, __('admin.messages.updated'), route('admin.products.show', $variant->product_id));
    }

    public function variantStatus(Request $request, ProductVariant $variant): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $variant->product);
        $data = $request->validate(['status' => ['required', Rule::enum(VariantStatus::class)]]);
        $this->service->setVariantStatus($variant, VariantStatus::from($data['status']), $request->user());

        return $this->success($request, __('admin.messages.status_changed'));
    }

    public function destroyVariant(Request $request, ProductVariant $variant): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $variant->product);
        $productId = $variant->product_id;
        $this->service->deleteVariant($variant, $request->user());

        return $this->success($request, __('admin.messages.deleted'), route('admin.products.show', $productId));
    }

    public function syncSpecifications(VariantAttributesRequest $request, ProductVariant $variant): JsonResponse|RedirectResponse
    {
        $this->service->syncVariantAttributes($variant, $request->validated('values'), $request->user());

        return $this->success($request, __('admin.messages.updated'), route('admin.products.show', $variant->product_id));
    }

    public function storeMedia(StoreProductMediaRequest $request, Product $product): JsonResponse|RedirectResponse
    {
        $this->service->addMedia($product, $request->file('image'), $request->validated(), $request->user());

        return $this->success($request, __('admin.products.messages.media_uploaded'), route('admin.products.show', $product));
    }

    public function updateMedia(UpdateProductMediaRequest $request, Product $product, ProductMedia $media): JsonResponse|RedirectResponse
    {
        abort_unless((int) $media->product_id === (int) $product->id, 404);
        $this->service->updateMedia($media, $request->validated(), $request->user());

        return $this->success($request, __('admin.messages.updated'), route('admin.products.show', $product));
    }

    public function destroyMedia(Request $request, Product $product, ProductMedia $media): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $product);
        abort_unless((int) $media->product_id === (int) $product->id, 404);
        $this->service->removeMedia($media, $request->user());

        return $this->success($request, __('admin.messages.deleted'), route('admin.products.show', $product));
    }

    /**
     * Staff preview of a stored image (private disk; never a public URL).
     */
    public function previewMedia(Product $product, ProductMedia $media): StreamedResponse
    {
        Gate::authorize('view', $product);
        abort_unless((int) $media->product_id === (int) $product->id, 404);
        abort_if($media->asset->scan_status->value === 'blocked', 404);

        return Storage::disk(MediaAsset::DISK)->response($media->asset->object_key, null, [
            'Content-Type' => $media->asset->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(Request $request, Product $product): array
    {
        $scope = fn ($q) => $product->exists ? $q->where('organization_id', $product->organization_id) : $q;

        return [
            'product' => $product,
            'organizations' => $this->organizationOptions($request->user()),
            'categories' => Category::query()->forUser($request->user())->tap($scope)->orderBy('name_en')->get(),
            'brands' => Brand::query()->forUser($request->user())->tap($scope)->orderBy('name')->get(),
        ];
    }

    /**
     * @return Collection<int, string>
     */
    protected function approvedPolicies(int $organizationId): Collection
    {
        return PolicyVersion::query()->where('organization_id', $organizationId)->where('status', PolicyStatus::Approved->value)
            ->orderBy('policy_code')->get()->mapWithKeys(fn (PolicyVersion $p) => [$p->id => $p->displayName()]);
    }
}
