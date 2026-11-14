<?php

namespace BlueprintPhp\Laravel\Contracts;

use BlueprintPhp\Laravel\Enums\Platform;
use BlueprintPhp\Laravel\Enums\Version;

interface HasPlatform
{
    public static function platform(): Platform;

    public static function version(): Version;
}
