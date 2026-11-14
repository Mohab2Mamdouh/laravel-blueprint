<?php

declare(strict_types=1);

namespace BlueprintPhp\Laravel\Http\Middleware;

use BlueprintPhp\Laravel\Enums\Platform;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps a default `X-Platform` header on requests that don't already carry one.
 *
 * The API relies on clients (mobile/web SPA) sending `X-Platform` explicitly, but
 * server-rendered `web` routes have no client to set it. Applying this to the
 * `web` group makes every web request behave as `X-Platform: web` by default, so
 * platform-aware code (Context logging, platform-resolved Service/Request/Resource
 * concretes) resolves consistently. An explicit header is always respected.
 *
 * Alias: `platform.default`. Usage: `platform.default` (defaults to web) or
 * `platform.default:mobile` to force another platform.
 */
class SetDefaultPlatform
{
    public function handle(Request $request, Closure $next, ?string $platform = null): Response
    {
        $platform = Platform::tryFrom(strtolower((string) $platform))?->value ?? Platform::WEB->value;

        if (blank($request->header('X-Platform'))) {
            $request->headers->set('X-Platform', $platform);
        }

        return $next($request);
    }
}
