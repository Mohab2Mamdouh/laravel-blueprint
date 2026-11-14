<?php

namespace BlueprintPhp\Laravel\Commands;

use BlueprintPhp\Laravel\BlueprintServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'make:action',
    description: 'Generate an Action class in app/Actions (supports subdirectories via dot or slash notation)'
)]
class MakeAction extends Command
{
    protected $signature = 'make:action
        {name : Action name, e.g. CreateOrder or Order/CreateOrder or Order.CreateOrder}
        {--force : Overwrite file if it exists}
    ';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $raw = str_replace('.', '/', $this->argument('name'));
        $parts = explode('/', $raw);
        $force = (bool) $this->option('force');

        // The last segment is the class name; everything before is the subdirectory
        $className = Str::studly(array_pop($parts));
        $subPath = implode('/', array_map(fn ($p) => Str::studly($p), $parts));

        // Build namespace and directory
        $baseNs = 'App\\Actions';
        $baseDir = app_path('Actions');

        if ($subPath) {
            $namespace = $baseNs.'\\'.str_replace('/', '\\', $subPath);
            $directory = $baseDir.'/'.$subPath;
        } else {
            $namespace = $baseNs;
            $directory = $baseDir;
        }

        $filePath = "{$directory}/{$className}.php";

        // Create directory if needed
        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0777, true);
        }

        // Check if file already exists
        if (! $force && $this->files->exists($filePath)) {
            $this->error("Action already exists: {$filePath}");

            return self::FAILURE;
        }

        // Render stub
        $stub = $this->files->get($this->stubPath());
        $content = str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$namespace, $className],
            $stub
        );

        $this->files->put($filePath, $content);
        $this->info("Created: {$filePath}");
        $this->info('Action scaffolding complete ✅');

        return self::SUCCESS;
    }

    private function stubPath(): string
    {
        return BlueprintServiceProvider::stubPath('action.stub');
    }
}
