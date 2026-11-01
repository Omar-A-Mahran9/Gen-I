<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActiveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\PriceListRequest;
use App\Http\Requests\Business\PriceRequest;
use App\Http\Resources\Api\V1\PriceListResource;
use App\Http\Resources\Api\V1\PriceResource;
use App\Models\Price;
use App\Models\PriceList;
use App\Services\Business\PricingService;
use App\Support\ApiListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PriceListController extends Controller
{
    public function __construct(protected PricingService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PriceList::class);
        $query = $this->service->listQuery($request->user(), ApiListing::filters($request, ['status', 'organization_id', 'currency']));

        return PriceListResource::collection(ApiListing::paginate($query, $request, ['code' => 'code'], 'code'));
    }

    public function store(PriceListRequest $request): PriceListResource
    {
        return new PriceListResource($this->service->saveList($request->payload(), $request->user()));
    }

    public function show(PriceList $priceList): PriceListResource
    {
        Gate::authorize('view', $priceList);
        abort_if($priceList->company_id !== null, 404);

        return new PriceListResource($priceList->loadCount('prices'));
    }

    public function update(PriceListRequest $request, PriceList $priceList): PriceListResource
    {
        return new PriceListResource($this->service->saveList($request->payload(), $request->user(), $priceList));
    }

    public function status(Request $request, PriceList $priceList): PriceListResource
    {
        Gate::authorize('update', $priceList);
        $data = $request->validate(['status' => ['required', Rule::enum(ActiveStatus::class)]]);

        return new PriceListResource($this->service->setListStatus($priceList, ActiveStatus::from($data['status']), $request->user()));
    }

    public function destroy(Request $request, PriceList $priceList): Response
    {
        Gate::authorize('delete', $priceList);
        $this->service->deleteList($priceList, $request->user());

        return response()->noContent();
    }

    public function prices(Request $request, PriceList $priceList): AnonymousResourceCollection
    {
        Gate::authorize('view', $priceList);
        $query = $this->service->priceQuery($priceList, ApiListing::filters($request, ['variant_id', 'period']));

        return PriceResource::collection(ApiListing::paginate($query, $request, ['valid_from' => 'valid_from', 'unit_price' => 'unit_price'], '-valid_from'));
    }

    public function storePrice(PriceRequest $request, PriceList $priceList): PriceResource
    {
        return new PriceResource($this->service->createPrice($priceList, $request->validated(), $request->user())->load(['variant', 'priceList']));
    }

    public function updatePrice(PriceRequest $request, Price $price): PriceResource
    {
        return new PriceResource($this->service->updateFuturePrice($price, $request->validated(), $request->user())->load(['variant', 'priceList']));
    }

    public function destroyPrice(Request $request, Price $price): Response
    {
        Gate::authorize('update', $price->priceList);
        $this->service->deleteFuturePrice($price, $request->user());

        return response()->noContent();
    }
}
