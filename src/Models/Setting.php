<?php

namespace Mohab\LaravelBlueprint\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mohab\LaravelBlueprint\Traits\HumanDates;

class Setting extends Model
{
    use SoftDeletes, HumanDates;

    protected $guarded = [];

    protected $casts = [
        'value'      => 'array',
        'validation' => 'array',
    ];
}
