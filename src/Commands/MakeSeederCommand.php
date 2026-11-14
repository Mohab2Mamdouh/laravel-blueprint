<?php

namespace BlueprintPhp\Laravel\Commands;

use BlueprintPhp\Laravel\BlueprintServiceProvider;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;

class MakeSeederCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:seeder {name}
                            {--central : Create the seeder under database/seeders/central}
                            {--tenant : Create the seeder under database/seeders/tenant}';

    protected $description = 'Create a new timestamp-prefixed tracked seeder (target from blueprint.seeders.default_target; --central/--tenant override)';

    protected $type = 'Seeder';

    protected function getPath($name): string
    {
        $tenant = match (true) {
            (bool) $this->option('central') => false,
            (bool) $this->option('tenant') => true,
            default => config('blueprint.seeders.default_target', 'central') === 'tenant',
        };

        $dir = $tenant ? 'seeders/tenant' : 'seeders/central';
        $timestampName = time().'_'.Str::replaceFirst($this->rootNamespace(), '', $name);

        return database_path($dir).'/'.str_replace('\\', '/', $timestampName).'.php';
    }

    protected function getStub(): string
    {
        return BlueprintServiceProvider::stubPath('seeder.stub');
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace;
    }

    protected function rootNamespace(): string
    {
        return $this->laravel->getNamespace();
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the seeder'],
        ];
    }
}
