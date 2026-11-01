<?php

namespace App\Services\Business;

use Illuminate\Database\Eloquent\Builder;

/**
 * Small helpers shared by business services for list filtering.
 */
trait ScopedQuery
{
    /**
     * @param  Builder<*>  $query
     * @param  array<int, string>  $columns
     */
    protected function applySearch(Builder $query, ?string $term, array $columns): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(function (Builder $q) use ($columns, $like): void {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', $like);
            }
        });
    }

    /**
     * @param  Builder<*>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyEquals(Builder $query, array $filters, array $columns): void
    {
        foreach ($columns as $key => $column) {
            $key = is_int($key) ? $column : $key;
            $value = $filters[$key] ?? null;

            if ($value !== null && $value !== '') {
                $query->where($column, $value);
            }
        }
    }
}
