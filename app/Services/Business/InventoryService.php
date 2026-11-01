<?php

namespace App\Services\Business;

use App\Enums\ActiveStatus;
use App\Enums\MovementReason;
use App\Enums\VariantStatus;
use App\Exceptions\AdminActionException;
use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Branch stock balances and the immutable movement ledger (S2 §9.4, BR04, BR11).
 *
 * Every change: one DB transaction, row lock on the balance, validated
 * invariants (on_hand >= 0, reserved <= on_hand), an immutable movement with
 * a unique source_event_id (idempotency), version increment and an audit entry.
 */
class InventoryService
{
    use ScopedQuery;

    public function __construct(protected AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<InventoryBalance>
     */
    public function balanceQuery(User $user, array $filters = []): Builder
    {
        $query = InventoryBalance::query()->forUser($user)
            ->with(['branch:id,code,name', 'variant:id,product_id,sku,variant_name,status', 'variant.product:id,name_en,name_ar']);

        $term = trim((string) ($filters['search'] ?? ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->whereHas('variant', fn (Builder $v) => $v->where('sku', 'like', $like)->orWhere('variant_name', 'like', $like)
                ->orWhereHas('product', fn (Builder $p) => $p->where('name_en', 'like', $like)->orWhere('name_ar', 'like', $like)->orWhere('model_code', 'like', $like)));
        }

        $this->applyEquals($query, $filters, ['branch_id', 'variant_id', 'organization_id']);

        match ($filters['stock'] ?? null) {
            'out' => $query->where('on_hand', 0),
            'available' => $query->whereColumn('on_hand', '>', 'reserved'),
            default => null,
        };

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<InventoryMovement>
     */
    public function movementQuery(User $user, array $filters = []): Builder
    {
        $query = InventoryMovement::query()->forUser($user)
            ->with(['branch:id,code,name', 'variant:id,sku,variant_name', 'author:id,name']);

        $term = trim((string) ($filters['search'] ?? ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('source_event_id', 'like', $like)->orWhere('note', 'like', $like)
                ->orWhereHas('variant', fn (Builder $v) => $v->where('sku', 'like', $like)));
        }

        $this->applyEquals($query, $filters, ['branch_id', 'variant_id', 'reason', 'organization_id']);

        if (filled($filters['from'] ?? null)) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (filled($filters['to'] ?? null)) {
            $query->where('created_at', '<=', $filters['to'].' 23:59:59');
        }

        return $query;
    }

    /**
     * Record a manual stock change (receipt or adjustment) for one branch/variant.
     *
     * @param  string|null  $sourceEventId  idempotency key; replays with the same payload return the original movement
     */
    public function adjust(Branch $branch, ProductVariant $variant, int $delta, MovementReason $reason, ?string $note, User $actor, ?string $sourceEventId = null): InventoryMovement
    {
        $this->validateAdjustment($branch, $variant, $delta, $reason);

        $sourceEventId = $sourceEventId !== null && $sourceEventId !== ''
            ? 'manual:'.$sourceEventId
            : 'manual:'.Str::uuid()->toString();

        if ($replay = $this->findReplay($branch, $variant, $delta, $reason, $sourceEventId)) {
            return $replay;
        }

        try {
            return DB::transaction(fn (): InventoryMovement => $this->applyChange($branch, $variant, $delta, $reason, $note, $actor, $sourceEventId), 3);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request with the same idempotency key won the race.
            if ($replay = $this->findReplay($branch, $variant, $delta, $reason, $sourceEventId)) {
                return $replay;
            }

            throw AdminActionException::conflict(__('admin.errors.idempotency_conflict'));
        }
    }

    protected function applyChange(Branch $branch, ProductVariant $variant, int $delta, MovementReason $reason, ?string $note, User $actor, string $sourceEventId): InventoryMovement
    {
        $balance = $this->lockBalance($branch, $variant);
        $newOnHand = $balance->on_hand + $delta;

        if ($newOnHand < 0) {
            throw new AdminActionException(__('admin.errors.insufficient_stock', ['available' => $balance->on_hand]));
        }

        if ($newOnHand < $balance->reserved) {
            throw new AdminActionException(__('admin.errors.below_reserved', ['reserved' => $balance->reserved]));
        }

        $before = $balance->on_hand;
        $balance->forceFill([
            'on_hand' => $newOnHand,
            'source_updated_at' => now(),
            'version' => $balance->version + 1,
        ])->save();

        $movement = new InventoryMovement;
        $movement->forceFill([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'variant_id' => $variant->id,
            'quantity_delta' => $delta,
            'on_hand_after' => $newOnHand,
            'reason' => $reason,
            'source_event_id' => $sourceEventId,
            'note' => $note,
            'created_by' => $actor->id,
        ])->save();

        $this->audit->log('inventory.adjusted', $movement, [
            'branch' => $branch->code,
            'sku' => $variant->sku,
            'reason' => $reason->value,
            'delta' => $delta,
            'on_hand_before' => $before,
            'on_hand_after' => $newOnHand,
            'source_event_id' => $sourceEventId,
        ], $actor);

        return $movement;
    }

    /**
     * Lock (or create then lock) the balance row for this branch/variant.
     */
    protected function lockBalance(Branch $branch, ProductVariant $variant): InventoryBalance
    {
        $find = fn (): ?InventoryBalance => InventoryBalance::query()
            ->where('branch_id', $branch->id)
            ->where('variant_id', $variant->id)
            ->lockForUpdate()
            ->first();

        if ($balance = $find()) {
            return $balance;
        }

        try {
            DB::transaction(function () use ($branch, $variant): void {
                (new InventoryBalance)->forceFill([
                    'organization_id' => $branch->organization_id,
                    'branch_id' => $branch->id,
                    'variant_id' => $variant->id,
                    'on_hand' => 0,
                    'reserved' => 0,
                    'source_updated_at' => now(),
                    'version' => 0,
                ])->save();
            });
        } catch (UniqueConstraintViolationException) {
            // Created concurrently by another request; lock the existing row below.
        }

        return $find();
    }

    protected function validateAdjustment(Branch $branch, ProductVariant $variant, int $delta, MovementReason $reason): void
    {
        if ($branch->organization_id !== $variant->organization_id) {
            throw new AdminActionException(__('admin.errors.cross_organization'));
        }

        if (! in_array($reason, MovementReason::manual(), true)) {
            throw new AdminActionException(__('admin.errors.reason_not_manual'));
        }

        if ($delta === 0 || ($reason === MovementReason::Receipt && $delta < 0)) {
            throw new AdminActionException(__('admin.errors.invalid_quantity'));
        }

        if ($branch->status !== ActiveStatus::Active) {
            throw new AdminActionException(__('admin.errors.branch_inactive'));
        }

        if ($delta > 0 && $variant->status === VariantStatus::Retired) {
            throw new AdminActionException(__('admin.errors.variant_retired'));
        }
    }

    protected function findReplay(Branch $branch, ProductVariant $variant, int $delta, MovementReason $reason, string $sourceEventId): ?InventoryMovement
    {
        $existing = InventoryMovement::query()
            ->where('organization_id', $branch->organization_id)
            ->where('source_event_id', $sourceEventId)
            ->first();

        if ($existing === null) {
            return null;
        }

        $samePayload = (int) $existing->branch_id === (int) $branch->id && (int) $existing->variant_id === (int) $variant->id
            && $existing->quantity_delta === $delta && $existing->reason === $reason;

        if (! $samePayload) {
            throw AdminActionException::conflict(__('admin.errors.idempotency_conflict'));
        }

        return $existing;
    }
}
