<?php

declare(strict_types=1);

namespace BlueprintPhp\Laravel\Enums;

use BlueprintPhp\Laravel\Traits\Enumable;

/**
 * Client platform, resolved from the `X-Platform` header. Drives which concrete
 * (Web/Mobile) Service/Request/Resource implementation is bound per request.
 */
enum Platform: string
{
    use Enumable;

    case WEB = 'web';
    case MOBILE = 'mobile';
}
