<?php

declare(strict_types=1);

namespace BlueprintPhp\Laravel\Enums;

use BlueprintPhp\Laravel\Traits\Enumable;

enum UserStatus: int
{
    use Enumable;

    case INACTIVE = 0;
    case ACTIVE = 1;
    case PENDING = 2;
    case SUSPENDED = 3;

    public function label(): string
    {
        return match ($this) {
            self::INACTIVE => __('Inactive'),
            self::ACTIVE => __('Active'),
            self::PENDING => __('Pending'),
            self::SUSPENDED => __('Suspended'),
        };
    }
}
