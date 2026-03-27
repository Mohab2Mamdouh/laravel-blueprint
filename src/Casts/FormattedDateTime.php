<?php

namespace Mohab2Mamdouh\LaravelStarter\Casts;

use Mohab2Mamdouh\LaravelStarter\Traits\HumanDates;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class FormattedDateTime implements CastsAttributes
{
    use HumanDates;

    /**
     * Cast the given value to a human-readable formatted date string.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $this->formattedTimestamp($value);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value ? Carbon::parse($value)->toDateTimeString() : null;
    }
}
