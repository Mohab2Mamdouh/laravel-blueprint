<?php

declare(strict_types=1);

namespace BlueprintPhp\Laravel\Enums;

use BlueprintPhp\Laravel\Traits\Enumable;

enum SystemStatus: int
{
    use Enumable;

    case INACTIVE = 0;
    case ACTIVE = 1;
    case PENDING = 2;
    case SUSPENDED = 3;
}
