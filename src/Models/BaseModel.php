<?php

namespace Mohab2Mamdouh\LaravelStarter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mohab2Mamdouh\LaravelStarter\Models\Scopes\UserVisibilityScope;
use Mohab2Mamdouh\LaravelStarter\Traits\Actor;
use Mohab2Mamdouh\LaravelStarter\Traits\HumanDates;
use Mohab2Mamdouh\LaravelStarter\Traits\TranslatableFallback;

class BaseModel extends Model
{
    use SoftDeletes, HumanDates, TranslatableFallback, Actor;

    protected static function booted(): void
    {
        static::addGlobalScope(new UserVisibilityScope);
    }
}
