<?php

namespace Mohab\LaravelBlueprint;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Mohab\LaravelBlueprint\Commands\AddRepositoryPatternCommand;
use Mohab\LaravelBlueprint\Commands\GenerateSeed;
use Mohab\LaravelBlueprint\Commands\MakeSeederCommand;
use Mohab\LaravelBlueprint\Repository\Contracts\SettingRepositoryInterface;
use Mohab\LaravelBlueprint\Repository\Eloquent\SettingRepository;

class BlueprintCommandServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AddRepositoryPatternCommand::class,
                GenerateSeed::class,
                MakeSeederCommand::class,
            ]);
        }
    }
}