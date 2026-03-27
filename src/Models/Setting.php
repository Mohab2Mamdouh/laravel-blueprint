<?php

namespace Mohab2Mamdouh\LaravelStarter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mohab2Mamdouh\LaravelStarter\Traits\HumanDates;

class Setting extends Model
{
    use SoftDeletes, HumanDates;

    protected $guarded = [];

    protected $casts = [
        'value'      => 'array',
        'validation' => 'array',
    ];
}
