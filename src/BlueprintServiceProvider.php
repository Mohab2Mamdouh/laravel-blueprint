<?php

namespace Mohab\LaravelBlueprint;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Mohab\LaravelBlueprint\Commands\AddRepositoryPatternCommand;
use Mohab\LaravelBlueprint\Commands\GenerateSeed;
use Mohab\LaravelBlueprint\Commands\MakeSeederCommand;
use Mohab\LaravelBlueprint\Repository\Contracts\SettingRepositoryInterface;
use Mohab\LaravelBlueprint\Repository\Eloquent\SettingRepository;

class BlueprintServiceProvider extends ServiceProvider
{
    /**
     * Register package bindings and merge config.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/blueprint.php', 'blueprint'
        );

        // Repository bindings
        $this->registerRepository();
    }

    /**
     * Bootstrap package services by delegating to small methods.
     */
    public function boot(): void
    {
        $this->bootConfig();
        $this->bootMigrations();
        $this->bootTranslations();
        $this->bootCommands();
        $this->bootViews();
    }

    private function bootConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/blueprint.php' => config_path('blueprint.php'),
        ], 'blueprint-config');

        // Auto-publish config if enabled and the file doesn't exist in the app.
        // This makes the package more plug-and-play, but can be disabled via config('blueprint.auto_publish')
        try {
            if (config('blueprint.auto_publish', true) && ! file_exists(config_path('blueprint.php'))) {
                (new Filesystem())->copy(__DIR__ . '/../config/blueprint.php', config_path('blueprint.php'));
            }
        } catch (\Throwable $e) {
            // Fail silently if running in an environment without Laravel helpers available yet.
        }
    }

    private function bootMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    private function bootTranslations(): void
    {
        // Load package translations so they can be referenced with the "blueprint::" namespace
        $this->loadTranslationsFrom(__DIR__ . '/lang', 'blueprint');
        // In case you have JSON translations in the lang folder as well
        $this->loadJsonTranslationsFrom(__DIR__ . '/lang');

        // Allow applications to publish package translation files to their resources/lang/vendor/blueprint
        $this->publishes([
            __DIR__ . '/lang' => resource_path('lang/vendor/blueprint'),
        ], 'blueprint-lang');

        // Auto-publish translations if enabled and the directory doesn't exist in the app.
        try {
            if (config('blueprint.auto_publish', true) && ! is_dir(resource_path('lang/vendor/blueprint'))) {
                (new Filesystem())->ensureDirectoryExists(resource_path('lang/vendor/blueprint'));
                (new Filesystem())->copyDirectory(__DIR__ . '/lang', resource_path('lang/vendor/blueprint'));
            }
        } catch (\Throwable $e) {
            // Fail silently when resource_path or filesystem is not available yet.
        }
    }

    private function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AddRepositoryPatternCommand::class,
                GenerateSeed::class,
                MakeSeederCommand::class,
            ]);
        }
    }

    private function bootViews(): void
    {
        View::composer('*', function ($view) {
            $useCache = !app()->environment('local');

            $websiteSettings = resolve(SettingRepositoryInterface::class)->settings();

            $view->with('websiteSettings', $useCache ? Cache::rememberForever('view.websiteSettings', fn() => $websiteSettings) : $websiteSettings);
        });
    }

    private function registerRepository(): void
    {
        $this->app->bind(
            SettingRepositoryInterface::class,
            SettingRepository::class
        );
    }
}