<?php

declare(strict_types=1);

namespace BlueprintPhp\Laravel\Enums;

use BlueprintPhp\Laravel\Traits\Enumable;

/**
 * Supported API versions, resolved from the URL segment (`api/v1/*`). Mirrors
 * {@see Platform}: an enum validated the same way (tryFrom / cases) and declared
 * on each platform concrete via a static version() accessor, exactly like
 * platform().
 */
enum Version: string
{
    use Enumable;

    case V1 = 'v1';

    /**
     * Numeric form of the version (e.g. 1 for v1). Used for building the URL
     * pattern (`api/v1/*`) and for keying the version `match` blocks in the
     * platform binding providers, which work with the plain integer.
     */
    public function number(): int
    {
        return (int) ltrim($this->value, 'vV');
    }

    /**
     * The current/default version, used for console + non-versioned requests
     * where no version segment is present in the URL.
     */
    public static function current(): self
    {
        return self::cases()[0];
    }
}
