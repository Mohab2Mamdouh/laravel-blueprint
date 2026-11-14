<?php

namespace BlueprintPhp\Laravel\Models;

use Illuminate\Database\Eloquent\Model;

class Seeder extends Model
{
    protected $fillable = ['seeder', 'batch'];

    public $timestamps = false;
}
