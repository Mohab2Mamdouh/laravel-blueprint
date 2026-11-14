<?php

declare(strict_types=1);

namespace BlueprintPhp\Laravel\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Orders a model by its `sort` column via a global scope. Opt-in: the model must
 * have a `sort` column.
 */
trait Sortable
{
    protected static function bootSortable(): void
    {
        static::addGlobalScope('sortable', function (Builder $builder) {
            $builder->orderBy('sort');
        });
    }
}
