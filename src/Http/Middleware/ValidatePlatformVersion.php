<?php

declare(strict_types=1);

namespace BlueprintPhp\Laravel\Http\Middleware;

use BlueprintPhp\Laravel\Enums\Platform;
use BlueprintPhp\Laravel\Enums\Version;
use BlueprintPhp\Laravel\Helpers\Responser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the API version segment and the X-Platform header for API requests
 * (the header our PlatformServiceProvider resolves concretes from). Runs as
 * middleware — not in the provider — so a bad request returns a clean 400 after
 * the framework is fully booted. Alias: `platform.version`.
 */
class ValidatePlatformVersion
{
    public function handle(Request $request, Closure $next)
    {
        // Endpoints that legitimately don't carry an X-Platform header.
        //        if (
        //            $request->is('api/*/media/*')
        //            || $request->is('api/*/files')
        //            || $request->is('api/*/files/*')
        //            || $request->is('api/*/signup/*')
        //        ) {
        //            return $next($request);
        //        }

        // Version segment lives right after the `api` prefix, e.g. api/v1/settings.
        $versionSegment = strtolower((string) $request->segment(2));

        if (Version::tryFrom($versionSegment) === null) {
            return $this->fail(__('Invalid API version. Supported versions: :versions', [
                'versions' => implode(', ', Version::values()),
            ]));
        }

        $platformHeader = strtolower($request->header('X-Platform', ''));

        if ($platformHeader === '') {
            return $this->fail(__('X-Platform header is required'));
        }

        if (Platform::tryFrom($platformHeader) === null) {
            return $this->fail(__('Invalid platform ":platform". Supported platforms: :platforms', [
                'platform' => $platformHeader,
                'platforms' => implode(', ', array_map(fn (Platform $p) => $p->value, Platform::cases())),
            ]));
        }

        return $next($request);
    }

    private function fail(string $message)
    {
        return Responser::fail(Response::HTTP_BAD_REQUEST, $message);
    }
}
