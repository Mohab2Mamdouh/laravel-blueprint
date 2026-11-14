<?php

namespace BlueprintPhp\Laravel\Tests\Fixtures;

use BlueprintPhp\Laravel\Contracts\ProvidesSettings;

class ArraySettings implements ProvidesSettings
{
    public function settings(): array
    {
        return ['site_name' => 'Blueprint'];
    }
}
