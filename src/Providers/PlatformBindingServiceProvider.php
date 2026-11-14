<?php

namespace BlueprintPhp\Laravel\Providers;

use BlueprintPhp\Laravel\Enums\Platform;
use BlueprintPhp\Laravel\Enums\Version;
use Illuminate\Support\ServiceProvider;

/**
 * Base for platform-aware providers. Binds each abstract Service/Request/Resource
 * to the concrete whose static platform() matches the request's `X-Platform`
 * header (web | mobile), keyed by the API version detected from the URL.
 *
 * Subclasses only declare the versioned implementation maps
 * (getServiceImplementations / getRequestImplementations / getResourceImplementations);
 * the detection + resolver logic lives here.
 */
abstract class PlatformBindingServiceProvider extends ServiceProvider
{
    private Version $version;

    private Platform $platform;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->detectPlatformAndVersion();
    }

    /**
     * Detect the API version and platform used to select the correct bindings.
     *
     * IMPORTANT: This runs inside the provider constructor, i.e. during the
     * `RegisterProviders` bootstrap phase — BEFORE the framework's core services
     * (cache, session, JWT auth driver) are registered. Nothing thrown here can
     * be rendered into an HTTP response, so this method must NEVER throw. It only
     * picks safe defaults for binding resolution; the real client-facing
     * validation (bad version / missing / invalid X-Platform) lives in the shared
     * ValidatePlatformVersion middleware, where the framework is fully booted.
     */
    private function detectPlatformAndVersion(): void
    {
        // Safe defaults used for console, non-API traffic, and invalid requests.
        $this->version = Version::current();
        $this->platform = Platform::cases()[0];

        // Skip detection if running in console (artisan commands).
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        // Skip detection for media URLs (no X-Platform needed).
        if (request()->is('api/*/media/*')) {
            return;
        }

        // Detect version from URL (api/v1/*, api/v2/*, etc.). Non-versioned
        // requests (root, health checks, scanners) keep the defaults and fall
        // through to normal routing (404) — no exception is thrown here.
        foreach (Version::cases() as $version) {
            if (request()->is("api/{$version->value}/*")) {
                $this->version = $version;
                break;
            }
        }

        // Detect platform from the X-Platform header. Keep the default when the
        // header is missing/invalid; the middleware rejects those with a 400.
        $platformHeader = strtolower(request()->header('X-Platform', ''));

        foreach (Platform::cases() as $platformCase) {
            if ($platformCase->value === $platformHeader) {
                $this->platform = $platformCase;
                break;
            }
        }
    }

    public function register(): void
    {
        $this->bindServices();
        $this->bindRequests();
        $this->bindResources();
    }

    private function bindServices(): void
    {
        $serviceBindings = $this->getServiceImplementations($this->version->number());

        foreach ($serviceBindings as $abstract => $implementations) {
            $this->app->bind($abstract, function () use ($abstract, $implementations) {
                foreach ($implementations as $implementation) {
                    if ($implementation::platform() === $this->platform && $implementation::version() === $this->version) {
                        return app($implementation);
                    }
                }
                throw new \RuntimeException("No implementation found for {$abstract} with platform {$this->platform->value} and version {$this->version->value}");
            });
        }
    }

    private function bindRequests(): void
    {
        $requestBindings = $this->getRequestImplementations($this->version->number());

        foreach ($requestBindings as $abstract => $implementations) {
            $this->app->bind($abstract, function () use ($abstract, $implementations) {
                foreach ($implementations as $implementation) {
                    if ($implementation::platform() === $this->platform && $implementation::version() === $this->version) {
                        return app($implementation);
                    }
                }
                throw new \RuntimeException("No request implementation found for {$abstract} with platform {$this->platform->value} and version {$this->version->value}");
            });
        }
    }

    private function bindResources(): void
    {
        $resourceBindings = $this->getResourceImplementations($this->version->number());

        foreach ($resourceBindings as $abstract => $implementations) {
            $this->app->bind($abstract, function ($app, $parameters) use ($implementations, $abstract) {
                foreach ($implementations as $implementation) {
                    if ($implementation::platform() === $this->platform && $implementation::version() === $this->version) {
                        return $app->make($implementation, $parameters);
                    }
                }
                throw new \RuntimeException("No resource implementation found for {$abstract} with platform {$this->platform->value} and version {$this->version->value}");
            });
        }
    }

    abstract protected function getServiceImplementations(int $version): array;

    abstract protected function getRequestImplementations(int $version): array;

    abstract protected function getResourceImplementations(int $version): array;
}
