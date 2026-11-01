<?php

namespace App\Http\Controllers\Admin\Business;

use App\Enums\ActiveStatus;
use App\Enums\PolicyStatus;
use App\Http\Requests\Business\PriceListRequest;
use App\Http\Requests\Business\PriceRequest;
use App\Models\PolicyVersion;
use App\Models\Price;
use App\Models\PriceList;
use App\Models\ProductVariant;
use App\Services\Business\PricingService;
use App\Support\DataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PriceListController extends BusinessController
{
    public function __construct(protected PricingService $service) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', PriceList::class);

        if (! $request->expectsJson()) {
            return view('admin.business.price-lists.index', ['organizations' => $this->organizationOptions($request->user())]);
        }

        return DataTable::of($this->service->listQuery($request->user(), $this->tableFilters($request)), $request)
            ->sortable(['code' => 'code', 'currency' => 'currency', 'status' => 'status'])
            ->defaultOrder('code', 'asc')
            ->map(fn (PriceList $l) => [
                'code' => '<a href="'.e(route('admin.price-lists.show', $l)).'" class="text-gray-800 text-hover-primary fw-bold" dir="ltr">'.e($l->code).'</a><div class="text-muted fs-7">'.e($l->organization?->name).'</div>',
                'currency' => e($l->currency),
                'prices' => (int) $l->prices_count,
                'status' => view('admin.business.partials.status-badge', ['status' => $l->status])->render(),
                'actions' => view('admin.business.partials.row-actions', ['model' => $l, 'route' => 'price-lists', 'param' => $l])->render(),
            ])
            ->toResponse();
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', PriceList::class);

        return view('admin.business.price-lists.form', ['list' => new PriceList(['currency' => config('admin.organization_defaults.currency')]), 'organizations' => $this->organizationOptions($request->user())]);
    }

    public function store(PriceListRequest $request): JsonResponse|RedirectResponse
    {
        $list = $this->service->saveList($request->payload(), $request->user());

        return $this->success($request, __('admin.messages.created'), route('admin.price-lists.show', $list));
    }

    public function show(Request $request, PriceList $priceList): View|JsonResponse
    {
        Gate::authorize('view', $priceList);
        abort_if($priceList->company_id !== null, 404);

        if ($request->expectsJson()) {
            return DataTable::of($this->service->priceQuery($priceList, $this->tableFilters($request)), $request)
                ->sortable(['valid_from' => 'valid_from', 'unit_price' => 'unit_price', 'min_quantity' => 'min_quantity'])
                ->defaultOrder('valid_from')
                ->map(fn (Price $p) => [
                    'variant' => '<span class="fw-bold" dir="ltr">'.e($p->variant?->sku).'</span><div class="text-muted fs-7">'.e($p->variant?->product?->displayName()).' · '.e($p->variant?->variant_name).'</div>',
                    'min_quantity' => (int) $p->min_quantity,
                    'unit_price' => '<span class="fw-bold" dir="ltr">'.e(number_format((float) $p->unit_price, 2)).' '.e($priceList->currency).'</span>',
                    'tax_policy' => '<span dir="ltr">'.e($p->taxPolicy?->displayName()).'</span>',
                    'valid_from' => e(format_datetime($p->valid_from)),
                    'valid_to' => e($p->valid_to ? format_datetime($p->valid_to) : '—'),
                    'state' => view('admin.business.price-lists.partials.price-state', ['price' => $p])->render(),
                    'actions' => view('admin.business.price-lists.partials.price-actions', ['price' => $p, 'list' => $priceList])->render(),
                ])
                ->toResponse();
        }

        return view('admin.business.price-lists.show', [
            'list' => $priceList->load('organization')->loadCount('prices'),
            'variants' => ProductVariant::query()->where('organization_id', $priceList->organization_id)->with('product:id,name_en,name_ar')->orderBy('sku')->get(),
            'taxPolicies' => PolicyVersion::query()->where('organization_id', $priceList->organization_id)->where('status', PolicyStatus::Approved->value)->orderBy('policy_code')->get(),
        ]);
    }

    public function edit(Request $request, PriceList $priceList): View
    {
        Gate::authorize('update', $priceList);

        return view('admin.business.price-lists.form', ['list' => $priceList, 'organizations' => $this->organizationOptions($request->user())]);
    }

    public function update(PriceListRequest $request, PriceList $priceList): JsonResponse|RedirectResponse
    {
        $this->service->saveList($request->payload(), $request->user(), $priceList);

        return $this->success($request, __('admin.messages.updated'), route('admin.price-lists.show', $priceList));
    }

    public function status(Request $request, PriceList $priceList): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $priceList);
        $data = $request->validate(['status' => ['required', Rule::enum(ActiveStatus::class)]]);
        $this->service->setListStatus($priceList, ActiveStatus::from($data['status']), $request->user());

        return $this->success($request, __('admin.messages.status_changed'));
    }

    public function destroy(Request $request, PriceList $priceList): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $priceList);
        $this->service->deleteList($priceList, $request->user());

        return $this->success($request, __('admin.messages.deleted'), route('admin.price-lists.index'));
    }

    public function storePrice(PriceRequest $request, PriceList $priceList): JsonResponse|RedirectResponse
    {
        $this->service->createPrice($priceList, $request->validated(), $request->user());

        return $this->success($request, __('admin.pricing.messages.price_created'), route('admin.price-lists.show', $priceList));
    }

    public function updatePrice(PriceRequest $request, Price $price): JsonResponse|RedirectResponse
    {
        $this->service->updateFuturePrice($price, $request->validated(), $request->user());

        return $this->success($request, __('admin.messages.updated'), route('admin.price-lists.show', $price->price_list_id));
    }

    public function destroyPrice(Request $request, Price $price): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $price->priceList);
        $this->service->deleteFuturePrice($price, $request->user());

        return $this->success($request, __('admin.messages.deleted'));
    }
}
