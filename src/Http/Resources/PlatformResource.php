<?php

namespace BlueprintPhp\Laravel\Http\Resources;

use BlueprintPhp\Laravel\Contracts\HasPlatform;
use BlueprintPhp\Laravel\Enums\Platform;
use BlueprintPhp\Laravel\Enums\Version;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class PlatformResource extends JsonResource implements HasPlatform
{
    /**
     * Get the platform this resource belongs to.
     */
    abstract public static function platform(): Platform;

    /**
     * Get the API version this resource belongs to.
     */
    abstract public static function version(): Version;

    /**
     * Override make to resolve concrete class from container.
     *
     * This allows abstract resources to automatically resolve to the correct
     * platform-specific implementation (Web/Mobile) based on container bindings.
     */
    public static function make(...$parameters)
    {
        return app(static::class, ['resource' => $parameters[0] ?? null]);
    }

    /**
     * Override newCollection to resolve concrete class from container.
     *
     * This allows abstract resources to automatically resolve to the correct
     * platform-specific implementation (Web/Mobile) based on container bindings.
     */
    protected static function newCollection($resource)
    {
        // Resolve the concrete resource class from container based on platform binding
        // Pass empty array as resource parameter to get the concrete class
        $instance = app(static::class, ['resource' => []]);

        // Get the actual concrete class name (Web\Resource or Mobile\Resource)
        $concreteClass = get_class($instance);

        // Pass the concrete class to AnonymousResourceCollection
        return new AnonymousResourceCollection($resource, $concreteClass);
    }
}
