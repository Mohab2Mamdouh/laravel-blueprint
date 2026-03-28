<?php

namespace Mohab\LaravelBlueprint;

use Illuminate\Support\ServiceProvider;
use Mohab\LaravelBlueprint\Commands\AddRepositoryPatternCommand;
use Mohab\LaravelBlueprint\Commands\GenerateSeed;
use Mohab\LaravelBlueprint\Commands\MakeSeederCommand;
use Mohab\LaravelBlueprint\Repository\Contracts\SettingRepositoryInterface;
use Mohab\LaravelBlueprint\Repository\Eloquent\SettingRepository;

class BlueprintServiceProvider extends ServiceProvider
{
    /**
     * Register package bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/blueprint.php', 'blueprint'
        );
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/blueprint.php' => config_path('blueprint.php'),
        ], 'blueprint-config');
    }
}