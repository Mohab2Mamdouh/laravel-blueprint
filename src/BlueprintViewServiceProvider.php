<?php

namespace Mohab\LaravelBlueprint;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Mohab\LaravelBlueprint\Repository\Contracts\SettingRepositoryInterface;
use Mohab\LaravelBlueprint\Repository\Eloquent\SettingRepository;

class BlueprintViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('*', function ($view) {
            $useCache = !app()->environment('local');

            $websiteSettings = resolve(SettingRepositoryInterface::class)->settings();

            $view->with('websiteSettings', $useCache ? Cache::rememberForever('view.websiteSettings', fn() => $websiteSettings) : $websiteSettings);
        });
    }
}