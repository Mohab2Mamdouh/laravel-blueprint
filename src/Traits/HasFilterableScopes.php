<?php

declare(strict_types=1);

namespace BlueprintPhp\Laravel\Traits;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reusable filter-scope primitives for models using AcceptedScopes.
 */
trait HasFilterableScopes
{
    #[Scope]
    public function byIds(Builder $query, ...$ids): Builder
    {
        $allIds = (new static)->newQuery()->pluck('id')->all();
        $totalCount = count($allIds);
        $idsCount = count($ids);

        return $query->when($idsCount >= $totalCount, function ($query) {
            return $query;
        }, function ($query) use ($ids, $allIds, $idsCount) {
            $excludedIds = array_values(array_diff($allIds, $ids));
            $query->when(count($excludedIds) < $idsCount, function ($query) use ($excludedIds) {
                $query->whereNotIn('id', $excludedIds);
            }, function ($query) use ($ids) {
                $query->whereIn('id', $ids);
            });
        });
    }

    /** Match a column against a scalar or list of values. */
    protected function matchColumn(Builder $query, string $column, $values): Builder
    {
        return $query->when(
            is_array($values),
            fn (Builder $query) => $query->whereIn($column, $values),
            fn (Builder $query) => $query->where($column, $values),
        );
    }

    /** Constrain a date column to an optional ['from' => …, 'to' => …] range. */
    protected function applyDateRange(Builder $query, string $column, $dates): Builder
    {
        $from = $dates['from'] ?? null;
        $to = $dates['to'] ?? null;

        return $query
            ->when($from, fn (Builder $query, $from) => $query->whereDate($column, '>=', $from))
            ->when($to, fn (Builder $query, $to) => $query->whereDate($column, '<=', $to));
    }
}
