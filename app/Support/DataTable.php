<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Minimal server-side processor for the jQuery DataTables protocol.
 *
 * Only allow-listed columns can be searched or sorted; page size is capped.
 *
 * @template TModel of Model
 */
class DataTable
{
    public const MAX_PAGE_SIZE = 100;

    /** @var array<int, string> */
    protected array $searchable = [];

    /** @var array<string, string> column key => SQL column */
    protected array $sortable = [];

    /** @var array<int, Closure> */
    protected array $filters = [];

    protected ?Closure $mapper = null;

    /** @var array{0: string, 1: string}|null */
    protected ?array $defaultOrder = null;

    /**
     * @param  Builder<TModel>  $query
     */
    public function __construct(protected Builder $query, protected Request $request) {}

    /**
     * @param  Builder<TModel>  $query
     * @return static<TModel>
     */
    public static function of(Builder $query, Request $request): static
    {
        return new static($query, $request);
    }

    /**
     * @param  array<int, string>  $columns
     */
    public function searchable(array $columns): static
    {
        $this->searchable = $columns;

        return $this;
    }

    /**
     * @param  array<string, string>  $columns
     */
    public function sortable(array $columns): static
    {
        $this->sortable = $columns;

        return $this;
    }

    public function defaultOrder(string $column, string $direction = 'desc'): static
    {
        $this->defaultOrder = [$column, $direction === 'asc' ? 'asc' : 'desc'];

        return $this;
    }

    /**
     * Add a filter: fn (Builder $query, Request $request): void.
     */
    public function filter(Closure $filter): static
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * Map each model to the row array sent to the browser.
     */
    public function map(Closure $mapper): static
    {
        $this->mapper = $mapper;

        return $this;
    }

    /**
     * @return array{draw: int, recordsTotal: int, recordsFiltered: int, data: array<int, mixed>}
     */
    public function toArray(): array
    {
        $total = (clone $this->query)->count();

        foreach ($this->filters as $filter) {
            $filter($this->query, $this->request);
        }

        $this->applySearch();
        $filtered = (clone $this->query)->count();

        $this->applyOrder();

        $length = (int) $this->request->input('length', 10);
        $length = $length < 1 ? 10 : min($length, self::MAX_PAGE_SIZE);
        $start = max(0, (int) $this->request->input('start', 0));

        $rows = $this->query->skip($start)->take($length)->get();
        $mapper = $this->mapper ?? fn (Model $model): array => $model->toArray();

        return [
            'draw' => (int) $this->request->input('draw', 0),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map($mapper)->values()->all(),
        ];
    }

    public function toResponse(): JsonResponse
    {
        return response()->json($this->toArray());
    }

    protected function applySearch(): void
    {
        $term = trim((string) $this->request->input('search.value', ''));

        if ($term === '' || $this->searchable === []) {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $this->query->where(function (Builder $query) use ($like): void {
            foreach ($this->searchable as $column) {
                $query->orWhere($column, 'like', $like);
            }
        });
    }

    protected function applyOrder(): void
    {
        $index = $this->request->input('order.0.column');
        $direction = $this->request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $key = $index !== null ? $this->request->input("columns.{$index}.data") : null;

        if (is_string($key) && isset($this->sortable[$key])) {
            $this->query->orderBy($this->sortable[$key], $direction);

            return;
        }

        if ($this->defaultOrder !== null) {
            $this->query->orderBy(...$this->defaultOrder);
        }
    }
}
