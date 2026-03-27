<?php

namespace Mohab2Mamdouh\LaravelStarter;

use Illuminate\Support\ServiceProvider;
use Mohab2Mamdouh\LaravelStarter\Commands\AddRepositoryPatternCommand;
use Mohab2Mamdouh\LaravelStarter\Commands\GenerateSeed;
use Mohab2Mamdouh\LaravelStarter\Commands\MakeSeederCommand;
use Mohab2Mamdouh\LaravelStarter\Repository\Contracts\SettingRepositoryInterface;
use Mohab2Mamdouh\LaravelStarter\Repository\Eloquent\SettingRepository;

class StarterServiceProvider extends ServiceProvider
{
    /**
     * Register package bindings.
     */
    public function register(): void
    {
        $this->app->bind(
            SettingRepositoryInterface::class,
            SettingRepository::class
        );
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AddRepositoryPatternCommand::class,
                GenerateSeed::class,
                MakeSeederCommand::class,
            ]);
        }
    }
}
