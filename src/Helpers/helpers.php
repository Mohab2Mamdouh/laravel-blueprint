<?php

use BlueprintPhp\Laravel\Contracts\ProvidesSettings;
use BlueprintPhp\Laravel\Exceptions\DebugException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Global helper functions
|--------------------------------------------------------------------------
| This file is autoloaded via composer's "files" directive.
|
| NOTE: Do NOT define a `platform()` helper here. Because this file loads very
| early, any function defined here can never be overridden by the consuming
| application. If your app needs a `platform()` helper, define it in your own
| app (e.g. app/helpers.php) so it can resolve your real Platform service.
*/

if (! function_exists('getSettings')) {
    /**
     * Retrieve application settings on demand (cached forever).
     *
     * Replaces the old boot-time `View::share('websiteSettings', …)` query — the
     * database is only hit lazily, the first time settings are actually needed.
     *
     *   getSettings();            // full ['key' => value, …] map
     *   getSettings('site_name'); // a single value (null when the key is absent)
     *
     * The values come from the class named in `blueprint.settings.repository`
     * (see {@see ProvidesSettings}); without one
     * this returns an empty array. The cache is keyed by
     * `blueprint.cache.website_settings_key` — forget that key whenever your
     * settings change.
     *
     * @return mixed The full settings array, or the single value for $key.
     */
    function getSettings(?string $key = null): mixed
    {
        $cacheKey = config('blueprint.cache.website_settings_key', 'view.websiteSettings');

        try {
            $settings = Cache::rememberForever($cacheKey, function () {
                // No settings source configured: nothing to cache.
                $source = config('blueprint.settings.repository');

                if (! $source) {
                    throw new RuntimeException('no settings repository configured');
                }

                // Skip caching when the table isn't migrated yet: throwing here
                // prevents an empty array from being cached forever.
                if (! Schema::hasTable(config('blueprint.settings.table', 'settings'))) {
                    throw new RuntimeException('settings table not available yet');
                }

                return resolve($source)->settings();
            });
        } catch (Throwable $e) {
            $settings = [];
        }

        return $key !== null ? ($settings[$key] ?? null) : $settings;
    }
}

if (! function_exists('dbg')) {
    /**
     * Debug helper: throw a DebugException that dumps the given value(s) plus the
     * caller trace and request context as a JSON response (via the API renderer).
     *
     *   dbg($user);  dbg('checkpoint', $payload);
     */
    function dbg(mixed ...$args): never
    {
        throw new DebugException(...$args);
    }
}
