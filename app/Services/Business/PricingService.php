<?php

namespace App\Services\Business;

use App\Enums\ActiveStatus;
use App\Enums\PolicyStatus;
use App\Exceptions\AdminActionException;
use App\Models\PolicyVersion;
use App\Models\Price;
use App\Models\PriceList;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Price lists and effective-dated prices (S2 §9.4, BR03).
 *
 * - Prices are never overwritten: a new open-ended price closes the current
 *   open-ended one at its own valid_from; any other overlap is rejected.
 * - Prices that are already effective (current or past) are immutable.
 * - Amounts are decimals stored with 2 places; tax policy must be approved.
 */
class PricingService
{
    use ScopedQuery;

    public function __construct(protected AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PriceList>
     */
    public function listQuery(User $user, array $filters = []): Builder
    {
        $query = PriceList::query()->forUser($user)->whereNull('company_id')
            ->with('organization:id,name')->withCount('prices');

        $this->applySearch($query, $filters['search'] ?? null, ['code']);
        $this->applyEquals($query, $filters, ['status', 'organization_id', 'currency']);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveList(array $data, User $actor, ?PriceList $list = null): PriceList
    {
        $list ??= new PriceList;
        $isNew = ! $list->exists;

        if (! $isNew && isset($data['currency']) && $data['currency'] !== $list->currency && $list->prices()->exists()) {
            throw new AdminActionException(__('admin.errors.currency_locked'));
        }

        $list->fill($data);
        if ($isNew) {
            $list->status = ActiveStatus::Active;
        }
        $changed = array_keys($list->getDirty());
        $list->save();

        $this->audit->log($isNew ? 'price_list.created' : 'price_list.updated', $list, $isNew ? ['code' => $list->code, 'currency' => $list->currency] : ['changed' => $changed], $actor);

        return $list;
    }

    public function setListStatus(PriceList $list, ActiveStatus $status, User $actor): PriceList
    {
        if ($list->status !== $status) {
            $from = $list->status;
            $list->forceFill(['status' => $status])->save();
            $this->audit->log('price_list.status_changed', $list, ['from' => $from->value, 'to' => $status->value], $actor);
        }

        return $list;
    }

    public function deleteList(PriceList $list, User $actor): void
    {
        if ($list->prices()->exists()) {
            throw new AdminActionException(__('admin.errors.has_dependents'));
        }

        $this->audit->log('price_list.deleted', $list, ['code' => $list->code], $actor);
        $list->delete();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Price>
     */
    public function priceQuery(PriceList $list, array $filters = []): Builder
    {
        $query = $list->prices()->getQuery()
            ->with(['variant:id,product_id,sku,variant_name', 'variant.product:id,name_en,name_ar', 'taxPolicy:id,policy_code,version', 'author:id,name']);

        $term = trim((string) ($filters['search'] ?? ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->whereHas('variant', fn (Builder $v) => $v->where('sku', 'like', $like)->orWhere('variant_name', 'like', $like));
        }

        if (filled($filters['variant_id'] ?? null)) {
            $query->where('variant_id', $filters['variant_id']);
        }

        match ($filters['period'] ?? null) {
            'current' => $query->effectiveAt(now()),
            'future' => $query->where('valid_from', '>', now()),
            'past' => $query->whereNotNull('valid_to')->where('valid_to', '<=', now()),
            default => null,
        };

        return $query;
    }

    /**
     * @param  array{variant_id: int, min_quantity: int, unit_price: string, tax_policy_id: int, valid_from?: string|null, valid_to?: string|null}  $data
     */
    public function createPrice(PriceList $list, array $data, User $actor): Price
    {
        $variant = ProductVariant::query()->whereKey($data['variant_id'])->where('organization_id', $list->organization_id)->firstOrFail();
        $this->assertTaxPolicy($list, (int) $data['tax_policy_id']);

        $from = Carbon::parse($data['valid_from'] ?? now());
        $to = filled($data['valid_to'] ?? null) ? Carbon::parse($data['valid_to']) : null;

        return DB::transaction(function () use ($list, $variant, $data, $from, $to, $actor): Price {
            // Serialise price changes for this list (prevents concurrent overlapping inserts).
            PriceList::query()->whereKey($list->id)->lockForUpdate()->first();

            $overlapping = $this->overlapping($list, $variant->id, (int) $data['min_quantity'], $from, $to)->get();
            $superseded = null;

            foreach ($overlapping as $existing) {
                // Only an open-ended price that started earlier may be closed automatically.
                if ($existing->valid_to === null && $existing->valid_from->lt($from) && $to === null) {
                    $superseded = $existing;

                    continue;
                }

                throw new AdminActionException(__('admin.errors.price_overlap'));
            }

            if ($superseded !== null) {
                $superseded->forceFill(['valid_to' => $from])->save();
            }

            $price = Price::query()->create([
                'organization_id' => $list->organization_id,
                'price_list_id' => $list->id,
                'variant_id' => $variant->id,
                'min_quantity' => (int) $data['min_quantity'],
                'unit_price' => $data['unit_price'],
                'tax_policy_id' => (int) $data['tax_policy_id'],
                'valid_from' => $from,
                'valid_to' => $to,
                'created_by' => $actor->id,
            ]);

            $this->audit->log('price.created', $price, array_filter([
                'price_list' => $list->code,
                'sku' => $variant->sku,
                'min_quantity' => $price->min_quantity,
                'unit_price' => $price->unit_price,
                'currency' => $list->currency,
                'valid_from' => $from->toIso8601String(),
                'valid_to' => $to?->toIso8601String(),
                'superseded_price_id' => $superseded?->id,
                'superseded_unit_price' => $superseded?->unit_price,
            ], fn ($v) => $v !== null), $actor);

            return $price;
        });
    }

    /**
     * Only future (not yet effective) prices can be edited.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateFuturePrice(Price $price, array $data, User $actor): Price
    {
        $this->assertFuture($price);
        $list = $price->priceList;
        $this->assertTaxPolicy($list, (int) ($data['tax_policy_id'] ?? $price->tax_policy_id));

        return DB::transaction(function () use ($price, $list, $data, $actor): Price {
            PriceList::query()->whereKey($list->id)->lockForUpdate()->first();

            $from = Carbon::parse($data['valid_from'] ?? $price->valid_from);
            $to = array_key_exists('valid_to', $data) ? (filled($data['valid_to']) ? Carbon::parse($data['valid_to']) : null) : $price->valid_to;
            $minQuantity = (int) ($data['min_quantity'] ?? $price->min_quantity);

            if ($from->isPast()) {
                throw new AdminActionException(__('admin.errors.price_must_be_future'));
            }

            if ($this->overlapping($list, $price->variant_id, $minQuantity, $from, $to)->whereKeyNot($price->id)->exists()) {
                throw new AdminActionException(__('admin.errors.price_overlap'));
            }

            $before = ['unit_price' => $price->unit_price, 'valid_from' => $price->valid_from->toIso8601String()];
            $price->forceFill([
                'unit_price' => $data['unit_price'] ?? $price->unit_price,
                'min_quantity' => $minQuantity,
                'tax_policy_id' => (int) ($data['tax_policy_id'] ?? $price->tax_policy_id),
                'valid_from' => $from,
                'valid_to' => $to,
            ])->save();

            $this->audit->log('price.updated', $price, ['before' => $before, 'after' => ['unit_price' => $price->unit_price, 'valid_from' => $from->toIso8601String()]], $actor);

            return $price;
        });
    }

    public function deleteFuturePrice(Price $price, User $actor): void
    {
        $this->assertFuture($price);

        $this->audit->log('price.deleted', $price, ['unit_price' => $price->unit_price, 'valid_from' => $price->valid_from->toIso8601String()], $actor);
        $price->delete();
    }

    /**
     * Effective unit price for a variant in a list at a time, honouring quantity breaks.
     */
    public function currentPrice(PriceList $list, ProductVariant $variant, int $quantity = 1, ?Carbon $at = null): ?Price
    {
        return $list->prices()->getQuery()
            ->where('variant_id', $variant->id)
            ->where('min_quantity', '<=', max(1, $quantity))
            ->effectiveAt($at)
            ->orderByDesc('min_quantity')
            ->first();
    }

    /**
     * @return Builder<Price>
     */
    protected function overlapping(PriceList $list, int $variantId, int $minQuantity, Carbon $from, ?Carbon $to): Builder
    {
        return Price::query()
            ->where('price_list_id', $list->id)
            ->where('variant_id', $variantId)
            ->where('min_quantity', $minQuantity)
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $from))
            ->when($to !== null, fn (Builder $q) => $q->where('valid_from', '<', $to));
    }

    protected function assertTaxPolicy(PriceList $list, int $policyId): void
    {
        $valid = PolicyVersion::query()
            ->whereKey($policyId)
            ->where('organization_id', $list->organization_id)
            ->where('status', PolicyStatus::Approved->value)
            ->exists();

        if (! $valid) {
            throw new AdminActionException(__('admin.errors.tax_policy_not_approved'));
        }
    }

    protected function assertFuture(Price $price): void
    {
        if (! $price->isFuture()) {
            throw new AdminActionException(__('admin.errors.price_history_immutable'));
        }
    }
}
