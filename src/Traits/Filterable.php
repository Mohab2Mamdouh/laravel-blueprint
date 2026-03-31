<?php

namespace Mohab\LaravelBlueprint\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Filterable
{
    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    /**
     * Columns that can be filtered by exact match.
     * Declare this array in your model.
     *
     * @var array<string>
     *
     * Example:
     *   protected array $filterable = ['status', 'user_id', 'category_id'];
     */
    // protected array $filterable = [];

    /**
     * Columns that should be searched with a LIKE query instead of exact match.
     * Declare this array in your model.
     *
     * @var array<string>
     *
     * Example:
     *   protected array $searchable = ['name', 'email'];
     */
    // protected array $searchable = [];

    /**
     * Map of input key → column for range filtering.
     * Declare this array in your model.
     *
     * @var array<string, string>
     *
     * Example:
     *   protected array $rangeFilters = [
     *       'price_from' => 'price',
     *       'price_to'   => 'price',
     *   ];
     */
    // protected array $rangeFilters = [];

    /**
     * Columns that can be sorted.
     * Declare this array in your model, or leave empty to allow any column.
     *
     * @var array<string>
     *
     * Example:
     *   protected array $sortable = ['created_at', 'price', 'name'];
     */
    // protected array $sortable = [];

    // -------------------------------------------------------------------------
    // Public scope
    // -------------------------------------------------------------------------

    /**
     * Apply all filters, search, range filters, and sorting from the given array.
     *
     * Usage:
     *   Product::filter($request->only(['status', 'user_id', 'q', 'sort_by', 'sort_dir']))->paginate();
     *
     * Reserved input keys:
     *   q        → full-text LIKE search across $searchable columns
     *   sort_by  → column to sort by (validated against $sortable when set)
     *   sort_dir → 'asc' or 'desc'  (defaults to 'asc')
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $this->applyExactFilters($query, $filters);
        $this->applySearch($query, $filters['q'] ?? null);
        $this->applyRangeFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function applyExactFilters(Builder $query, array $filters): void
    {
        $filterable = $this->filterable ?? [];

        foreach ($filterable as $column) {
            if (isset($filters[$column]) && $filters[$column] !== '') {
                $value = $filters[$column];

                // Support comma-separated values as a whereIn
                if (is_string($value) && str_contains($value, ',')) {
                    $query->whereIn($column, array_map('trim', explode(',', $value)));
                } else {
                    $query->where($column, $value);
                }
            }
        }
    }

    private function applySearch(Builder $query, ?string $term): void
    {
        $searchable = $this->searchable ?? [];

        if (empty($term) || empty($searchable)) {
            return;
        }

        $query->where(function (Builder $q) use ($term, $searchable): void {
            foreach ($searchable as $column) {
                $q->orWhere($column, 'like', "%{$term}%");
            }
        });
    }

    private function applyRangeFilters(Builder $query, array $filters): void
    {
        $rangeFilters = $this->rangeFilters ?? [];

        foreach ($rangeFilters as $inputKey => $column) {
            if (!isset($filters[$inputKey]) || $filters[$inputKey] === '') {
                continue;
            }

            $value = $filters[$inputKey];

            // Convention: keys ending in _from/_min → >=, keys ending in _to/_max → <=
            if (str_ends_with($inputKey, '_from') || str_ends_with($inputKey, '_min')) {
                $query->where($column, '>=', $value);
            } elseif (str_ends_with($inputKey, '_to') || str_ends_with($inputKey, '_max')) {
                $query->where($column, '<=', $value);
            }
        }
    }

    private function applySorting(Builder $query, array $filters): void
    {
        $sortBy  = $filters['sort_by'] ?? null;
        $sortDir = in_array(strtolower($filters['sort_dir'] ?? ''), ['asc', 'desc'])
            ? strtolower($filters['sort_dir'])
            : 'asc';

        if (!$sortBy) {
            return;
        }

        $sortable = $this->sortable ?? [];

        // When $sortable is declared, only allow listed columns to prevent SQL injection.
        if (!empty($sortable) && !in_array($sortBy, $sortable)) {
            return;
        }

        $query->orderBy($sortBy, $sortDir);
    }
}