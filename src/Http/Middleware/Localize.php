<?php

declare(strict_types=1);

namespace BlueprintPhp\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * API locale negotiation: authenticated user's `language` → Accept-Language
 * header, constrained to the supported locales (falls back to app default).
 * Alias: `localize` (attach to the API group; web uses `set.locale`).
 */
class Localize
{
    public function handle(Request $request, Closure $next)
    {
        $supported = config('blueprint.supported_locales', ['en', 'ar']);

        $locale = auth('api')->user()?->language ?? $request->header('Accept-Language');

        app()->setLocale(in_array($locale, $supported, true) ? $locale : config('app.locale'));

        return $next($request);
    }
}
