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

class BlueprintRepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SettingRepositoryInterface::class,
            SettingRepository::class
        );
    }
}