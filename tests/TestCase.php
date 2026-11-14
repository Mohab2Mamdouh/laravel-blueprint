<?php

namespace BlueprintPhp\Laravel\Tests;

use BlueprintPhp\Laravel\BlueprintServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BlueprintServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Never write config/lang files into the testbench skeleton.
        $app['config']->set('blueprint.auto_publish', false);
        $app['config']->set('database.default', 'testing');
    }
}
