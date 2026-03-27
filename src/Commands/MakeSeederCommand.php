<?php

namespace Mohab2Mamdouh\LaravelStarter\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;

class MakeSeederCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:seeder {name}';

    protected $description = 'Create a new seeder class with a timestamp prefix for tenant databases';

    protected $type = 'Seeder';

    protected function getPath($name): string
    {
        $timestampName = time() . '_' . Str::replaceFirst($this->rootNamespace(), '', $name);

        return database_path('seeders/RunningSeeder') . '/' . str_replace('\\', '/', $timestampName) . '.php';
    }

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/seeder.stub';
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
