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

class BlueprintTranslationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Load package translations so they can be referenced with the "blueprint::" namespace
        $this->loadTranslationsFrom(__DIR__ . '/lang', 'blueprint');
        // In case you have JSON translations in the lang folder as well
        $this->loadJsonTranslationsFrom(__DIR__ . '/lang');

        // Allow applications to publish package translation files to their resources/lang/vendor/blueprint
        $this->publishes([
            __DIR__ . '/lang' => resource_path('lang/vendor/blueprint'),
        ], 'blueprint-lang');
    }
}