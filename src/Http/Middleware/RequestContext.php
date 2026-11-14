<?php

declare(strict_types=1);

namespace BlueprintPhp\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Seeds Laravel's Context repository with request-scoped metadata (platform,
 * user, request id) so every Log:: call and dbg() is automatically correlated.
 * Apply to the `api` route group.
 */
class RequestContext
{
    public function handle(Request $request, Closure $next)
    {
        Context::add([
            'platform' => strtolower($request->header('X-Platform', '')) ?: null,
            'user_id' => $this->userId(),
        ]);

        Context::addHidden(
            'request_id',
            $request->headers->get('X-Request-Id') ?? (string) Str::uuid(),
        );

        return $next($request);
    }

    private function userId(): mixed
    {
        try {
            return auth('api')->id();
        } catch (\Throwable) {
            return null;
        }
    }
}
