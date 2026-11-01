<?php

namespace App\Services\Business;

use App\Enums\ActiveStatus;
use App\Exceptions\AdminActionException;
use App\Models\Branch;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Branches = stores and service branches (S2). Code is unique per organization.
 */
class BranchService
{
    use ScopedQuery;

    public function __construct(protected AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Branch>
     */
    public function query(User $user, array $filters = []): Builder
    {
        $query = Branch::query()->forUser($user)
            ->with('organization:id,name')
            ->withCount('inventoryBalances');

        $this->applySearch($query, $filters['search'] ?? null, ['code', 'name', 'city', 'address_text']);
        $this->applyEquals($query, $filters, ['status', 'organization_id', 'city']);

        return $query;
    }

    /**
     * Normalise opening hours to the seven-day structure:
     * {"sat": [{"open": "10:00", "close": "22:00"}], ..., "fri": []} (empty = closed).
     *
     * @param  array<string, mixed>|null  $hours
     * @return array<string, array<int, array{open: string, close: string}>>
     */
    public static function normalizeOpeningHours(?array $hours): array
    {
        $normalized = [];

        foreach (Branch::DAYS as $day) {
            $normalized[$day] = collect($hours[$day] ?? [])
                ->filter(fn ($interval) => is_array($interval) && filled($interval['open'] ?? null) && filled($interval['close'] ?? null))
                ->map(fn (array $interval) => ['open' => (string) $interval['open'], 'close' => (string) $interval['close']])
                ->values()
                ->all();
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Branch
    {
        return DB::transaction(function () use ($data, $actor): Branch {
            $branch = new Branch($data);
            $branch->opening_hours = self::normalizeOpeningHours($data['opening_hours'] ?? []);
            $branch->status = ActiveStatus::Active;
            $branch->save();

            $this->audit->log('branch.created', $branch, ['code' => $branch->code, 'name' => $branch->name], $actor);

            return $branch;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Branch $branch, array $data, User $actor): Branch
    {
        return DB::transaction(function () use ($branch, $data, $actor): Branch {
            $branch->fill($data);
            if (array_key_exists('opening_hours', $data)) {
                $branch->opening_hours = self::normalizeOpeningHours($data['opening_hours']);
            }
            $changed = array_keys($branch->getDirty());
            $branch->save();

            if ($changed !== []) {
                $this->audit->log('branch.updated', $branch, ['changed' => $changed], $actor);
            }

            return $branch;
        });
    }

    public function setStatus(Branch $branch, ActiveStatus $status, User $actor): Branch
    {
        if ($branch->status === $status) {
            return $branch;
        }

        $from = $branch->status;
        $branch->forceFill(['status' => $status])->save();
        $this->audit->log('branch.status_changed', $branch, ['from' => $from->value, 'to' => $status->value], $actor);

        return $branch;
    }

    public function delete(Branch $branch, User $actor): void
    {
        if ($branch->inventoryBalances()->exists() || $branch->inventoryMovements()->exists()) {
            throw new AdminActionException(__('admin.errors.branch_has_inventory'));
        }

        DB::transaction(function () use ($branch, $actor): void {
            $this->audit->log('branch.deleted', $branch, ['code' => $branch->code, 'name' => $branch->name], $actor);
            $branch->delete();
        });
    }
}
