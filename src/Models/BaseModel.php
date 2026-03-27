<?php

namespace Mohab\LaravelBlueprint\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mohab\LaravelBlueprint\Models\Scopes\UserVisibilityScope;
use Mohab\LaravelBlueprint\Traits\Actor;
use Mohab\LaravelBlueprint\Traits\HumanDates;
use Mohab\LaravelBlueprint\Traits\TranslatableFallback;

class BaseModel extends Model
{
    use SoftDeletes, HumanDates, TranslatableFallback, Actor;

    protected static function booted(): void
    {
        static::addGlobalScope(new UserVisibilityScope);
    }
}
