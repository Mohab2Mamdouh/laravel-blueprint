<?php

namespace BlueprintPhp\Laravel\Models;

use BlueprintPhp\Laravel\Models\Scopes\UserVisibilityScope;
use BlueprintPhp\Laravel\Traits\Actor;
use BlueprintPhp\Laravel\Traits\HumanDates;
use BlueprintPhp\Laravel\Traits\TranslatableFallback;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BaseModel extends Model
{
    use Actor, HumanDates, SoftDeletes, TranslatableFallback;

    protected static function booted(): void
    {
        static::addGlobalScope(new UserVisibilityScope);
    }

    /**
     * Columns the `byKeywords()` query macro searches. Override per model.
     *
     * @return array<int, string>
     */
    public function getSearchableColumns(): array
    {
        return [];
    }
}
