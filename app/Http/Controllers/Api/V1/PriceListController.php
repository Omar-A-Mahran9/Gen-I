<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActiveStatus;
use App\Http\Requests\Business\StorePriceListRequest;
use App\Http\Requests\Business\StorePriceRequest;
use App\Http\Requests\Business\UpdatePriceListRequest;
use App\Http\Requests\Business\UpdatePriceRequest;
use App\Http\Resources\Api\V1\PriceListResource;
use App\Http\Resources\Api\V1\PriceResource;
use App\Models\Price;
use App\Models\PriceList;
use App\Services\Business\PricingService;
use App\Support\ApiListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PriceListController extends ApiController
{
    public function __construct(protected PricingService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PriceList::class);
        $query = $this->service->listQuery($request->user(), ApiListing::filters($request, ['status', 'organization_id', 'currency']));

        return $this->successWithPagination(__('api.messages.retrieved'), PriceListResource::collection(ApiListing::paginate($query, $request, ['code' => 'code'], 'code')));
    }

    public function store(StorePriceListRequest $request): JsonResponse
    {
        return $this->success(__('api.messages.created'), new PriceListResource($this->service->saveList($request->payload(), $request->user())), Response::HTTP_CREATED);
    }

    public function show(PriceList $priceList): JsonResponse
    {
        Gate::authorize('view', $priceList);
        abort_if($priceList->company_id !== null, 404);

        return $this->success(__('api.messages.retrieved'), new PriceListResource($priceList->loadCount('prices')));
    }

    public function update(UpdatePriceListRequest $request, PriceList $priceList): JsonResponse
    {
        return $this->success(__('api.messages.updated'), new PriceListResource($this->service->saveList($request->payload(), $request->user(), $priceList)));
    }

    public function status(Request $request, PriceList $priceList): JsonResponse
    {
        Gate::authorize('update', $priceList);
        $data = $request->validate(['status' => ['required', Rule::enum(ActiveStatus::class)]]);

        return $this->success(__('api.messages.status_changed'), new PriceListResource($this->service->setListStatus($priceList, ActiveStatus::from($data['status']), $request->user())));
    }

    public function destroy(Request $request, PriceList $priceList): JsonResponse
    {
        Gate::authorize('delete', $priceList);
        $this->service->deleteList($priceList, $request->user());

        return $this->success(__('api.messages.deleted'));
    }

    public function prices(Request $request, PriceList $priceList): JsonResponse
    {
        Gate::authorize('view', $priceList);
        $query = $this->service->priceQuery($priceList, ApiListing::filters($request, ['variant_id', 'period']));

        return $this->successWithPagination(__('api.messages.retrieved'), PriceResource::collection(ApiListing::paginate($query, $request, ['valid_from' => 'valid_from', 'unit_price' => 'unit_price'], '-valid_from')));
    }

    public function storePrice(StorePriceRequest $request, PriceList $priceList): JsonResponse
    {
        return $this->success(__('api.messages.created'), new PriceResource($this->service->createPrice($priceList, $request->validated(), $request->user())->load(['variant', 'priceList'])), Response::HTTP_CREATED);
    }

    public function updatePrice(UpdatePriceRequest $request, Price $price): JsonResponse
    {
        return $this->success(__('api.messages.updated'), new PriceResource($this->service->updateFuturePrice($price, $request->validated(), $request->user())->load(['variant', 'priceList'])));
    }

    public function destroyPrice(Request $request, Price $price): JsonResponse
    {
        Gate::authorize('update', $price->priceList);
        $this->service->deleteFuturePrice($price, $request->user());

        return $this->success(__('api.messages.deleted'));
    }
}
