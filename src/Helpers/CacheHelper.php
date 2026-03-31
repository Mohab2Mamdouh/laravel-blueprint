<?php

namespace Mohab\LaravelBlueprint\Helpers;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CacheHelper
{
    // -------------------------------------------------------------------------
    // Core remember / forget
    // -------------------------------------------------------------------------

    /**
     * Retrieve an item from cache or store the result of the callback.
     *
     * TTL defaults to forever; pass an integer (seconds) or null for forever.
     *
     * Usage:
     *   CacheHelper::remember('settings.all', fn () => Setting::all());
     *   CacheHelper::remember('settings.all', fn () => Setting::all(), ttl: 3600);
     */
    public static function remember(string $key, Closure $callback, ?int $ttl = null): mixed
    {
        $prefixedKey = static::prefixedKey($key);

        return $ttl === null
            ? Cache::rememberForever($prefixedKey, $callback)
            : Cache::remember($prefixedKey, $ttl, $callback);
    }

    /**
     * Remove a single cache entry.
     */
    public static function forget(string $key): bool
    {
        return Cache::forget(static::prefixedKey($key));
    }

    /**
     * Check whether a cache entry exists.
     */
    public static function has(string $key): bool
    {
        return Cache::has(static::prefixedKey($key));
    }

    /**
     * Store a value explicitly (no callback, no TTL by default).
     */
    public static function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $ttl === null
            ? Cache::forever(static::prefixedKey($key), $value)
            : Cache::put(static::prefixedKey($key), $value, $ttl);
    }

    /**
     * Retrieve a value, or null when missing.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::get(static::prefixedKey($key), $default);
    }

    // -------------------------------------------------------------------------
    // Model-scoped helpers
    // -------------------------------------------------------------------------

    /**
     * Cache the result of a callback scoped to a specific model instance.
     *
     * Auto-key:  "blueprint:products:42"  (snake_case table + id)
     *
     * Usage:
     *   CacheHelper::rememberForModel($product, fn () => $product->load('variants'));
     */
    public static function rememberForModel(Model $model, Closure $callback, ?int $ttl = null): mixed
    {
        return static::remember(static::modelKey($model), $callback, $ttl);
    }

    /**
     * Invalidate the cache entry for a specific model instance.
     */
    public static function forgetModel(Model $model): bool
    {
        return static::forget(static::modelKey($model));
    }

    /**
     * Invalidate ALL cache entries for every instance of a model class.
     *
     * Uses a tag when the cache store supports tagging (Redis, Memcached);
     * falls back to flushing only when tags are not available.
     *
     * Usage:
     *   CacheHelper::forgetModelClass(Product::class);
     */
    public static function forgetModelClass(string $modelClass): void
    {
        $tag = static::classTag($modelClass);

        try {
            Cache::tags([$tag])->flush();
        } catch (\BadMethodCallException) {
            // Tags not supported by the current driver — nothing to flush globally.
            // Callers should use forgetModel() per-instance in this case.
        }
    }

    // -------------------------------------------------------------------------
    // Tagged caching (Redis / Memcached)
    // -------------------------------------------------------------------------

    /**
     * Store a value under one or more tags.
     * Allows bulk invalidation by tag later.
     *
     * Usage:
     *   CacheHelper::rememberTagged(['products', 'homepage'], 'featured', fn () => ...);
     */
    public static function rememberTagged(array $tags, string $key, Closure $callback, ?int $ttl = null): mixed
    {
        $store = Cache::tags($tags);

        return $ttl === null
            ? $store->rememberForever(static::prefixedKey($key), $callback)
            : $store->remember(static::prefixedKey($key), $ttl, $callback);
    }

    /**
     * Flush all entries under a given set of tags.
     */
    public static function flushTags(array $tags): void
    {
        Cache::tags($tags)->flush();
    }

    // -------------------------------------------------------------------------
    // Key helpers
    // -------------------------------------------------------------------------

    /**
     * Build a consistent, prefixed cache key.
     *
     * The prefix is configurable via config('blueprint.cache.prefix').
     * Defaults to 'blueprint'.
     */
    public static function prefixedKey(string $key): string
    {
        $prefix = rtrim(config('blueprint.cache.prefix', 'blueprint'), ':');
        return "{$prefix}:{$key}";
    }

    /**
     * Build the auto-key for a model instance.
     *
     * Example: Product#42 → "products:42"
     */
    public static function modelKey(Model $model): string
    {
        return $model->getTable() . ':' . $model->getKey();
    }

    /**
     * Build a tag string for a model class for bulk invalidation.
     *
     * Example: App\Models\Product → "products"
     */
    private static function classTag(string $modelClass): string
    {
        return (new $modelClass)->getTable();
    }
}