<?php

namespace BlueprintPhp\Laravel\Service;

use BlueprintPhp\Laravel\Contracts\HasPlatform;
use BlueprintPhp\Laravel\Enums\Platform;
use BlueprintPhp\Laravel\Enums\Version;

abstract class PlatformService implements HasPlatform
{
    abstract public static function platform(): Platform;

    abstract public static function version(): Version;
}
