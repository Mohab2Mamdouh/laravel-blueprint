<?php

namespace Mohab\LaravelBlueprint\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AddRepositoryPatternCommand extends Command
{
    protected $signature = 'module:add-repository
                            {module : The module name}
                            {--ver=1 : Version number for the repository pattern}
                            {--name= : Optional custom name for the repository pattern}
                            {--crud : Use CRUD stubs (interface-crud, repository-crud, service-crud, controller-crud)}
                            {--model : Generate a model using the pattern name (or module name if --name is not set)}';

    protected $description = 'Add versioned repository pattern (Service, Repository, Interface) to an existing module';

    /**
     * Base path for stubs relative to this file.
     * Stubs live at: src/Commands/stubs/
     */
    private function stubsPath(): string
    {
        return __DIR__ . '/stubs';
    }

    public function handle(): int
    {
        $moduleName  = $this->argument('module');
        $customName  = $this->option('name');
        $version     = $this->option('ver');
        $isCrud      = $this->option('crud');
        $studlyName  = Str::studly($moduleName);
        $patternName = $customName ? Str::studly($customName) : $studlyName;
        $modulePath  = base_path("Modules/{$studlyName}");

        if (!File::exists($modulePath)) {
            $this->error("Module {$studlyName} does not exist!");
            return 1;
        }

        if (!is_numeric($version) || $version < 1) {
            $this->error("Version must be a positive integer!");
            return 1;
        }

        $stubSuffix = $isCrud ? '-crud' : '';
        $this->line($isCrud ? "  <comment>Mode:</comment> CRUD stubs" : "  <comment>Mode:</comment> Basic stubs");

        $this->deleteExistingPattern($modulePath, $patternName, $version);
        $this->addRepositoryPattern($modulePath, $studlyName, $patternName, $version, $stubSuffix);
        $this->updateController($modulePath, $studlyName, $patternName, $version, $stubSuffix);
        $this->updateServiceProvider($modulePath, $studlyName, $patternName, $version);

        if ($this->option('model')) {
            $this->createModel($modulePath, $studlyName, $patternName);
        }

        $mode = $isCrud ? 'CRUD' : 'basic';
        $this->info("Repository pattern [{$mode}] (v{$version}) added to {$studlyName} module with name {$patternName} successfully!");
        return 0;
    }

    // -------------------------------------------------------------------------
    // Deletion
    // -------------------------------------------------------------------------

    private function deleteExistingPattern(string $modulePath, string $patternName, string|int $version): void
    {
        $filesToDelete = [
            "{$modulePath}/app/Http/Services/v{$version}/{$patternName}Service.php",
            "{$modulePath}/app/Repository/Eloquent/v{$version}/{$patternName}Repository.php",
            "{$modulePath}/app/Repository/Contracts/v{$version}/{$patternName}RepositoryInterface.php",
            "{$modulePath}/app/Http/Controllers/v{$version}/{$patternName}Controller.php",
        ];

        foreach ($filesToDelete as $file) {
            if (File::exists($file)) {
                File::delete($file);
                $this->line("  <comment>Deleted:</comment> " . basename($file));
            }
        }
    }

    // -------------------------------------------------------------------------
    // Directory + file creation
    // -------------------------------------------------------------------------

    private function addRepositoryPattern(string $modulePath, string $moduleName, string $patternName, string|int $version, string $stubSuffix = ''): void
    {
        $directories = [
            "{$modulePath}/app/Http/Controllers/v{$version}",
            "{$modulePath}/app/Http/Services/v{$version}",
            "{$modulePath}/app/Repository/Contracts/v{$version}",
            "{$modulePath}/app/Repository/Eloquent/v{$version}",
        ];

        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
        }

        $this->createInterface($modulePath, $moduleName, $patternName, $version, $stubSuffix);
        $this->createRepository($modulePath, $moduleName, $patternName, $version, $stubSuffix);
        $this->createService($modulePath, $moduleName, $patternName, $version, $stubSuffix);
    }

    // -------------------------------------------------------------------------
    // Stub rendering helpers
    // -------------------------------------------------------------------------

    /**
     * Load a stub file and replace all placeholders.
     *
     * @param  string               $stubName      Filename inside stubs/ (without path)
     * @param  array<string,string> $replacements  Key => Value pairs to replace
     */
    private function renderStub(string $stubName, array $replacements): string
    {
        $stubPath = $this->stubsPath() . "/{$stubName}";

        if (!File::exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            return '';
        }

        $content = File::get($stubPath);

        foreach ($replacements as $placeholder => $value) {
            $content = str_replace("{{ {$placeholder} }}", $value, $content);
        }

        return $content;
    }

    private function replacements(string $moduleName, string $patternName, string|int $version): array
    {
        return [
            'ModuleName'  => $moduleName,
            'PatternName' => $patternName,
            'Version'     => $version,
        ];
    }

    // -------------------------------------------------------------------------
    // Individual file generators
    // -------------------------------------------------------------------------

    private function createInterface(string $modulePath, string $moduleName, string $patternName, string|int $version, string $stubSuffix = ''): void
    {
        $content     = $this->renderStub("interface{$stubSuffix}.stub", $this->replacements($moduleName, $patternName, $version));
        $destination = "{$modulePath}/app/Repository/Contracts/v{$version}/{$patternName}RepositoryInterface.php";

        File::put($destination, $content);
        $this->line("  <info>Created:</info> Repository/Contracts/v{$version}/{$patternName}RepositoryInterface.php");
    }

    private function createRepository(string $modulePath, string $moduleName, string $patternName, string|int $version, string $stubSuffix = ''): void
    {
        $content     = $this->renderStub("repository{$stubSuffix}.stub", $this->replacements($moduleName, $patternName, $version));
        $destination = "{$modulePath}/app/Repository/Eloquent/v{$version}/{$patternName}Repository.php";

        File::put($destination, $content);
        $this->line("  <info>Created:</info> Repository/Eloquent/v{$version}/{$patternName}Repository.php");
    }

    private function createService(string $modulePath, string $moduleName, string $patternName, string|int $version, string $stubSuffix = ''): void
    {
        $content     = $this->renderStub("service{$stubSuffix}.stub", $this->replacements($moduleName, $patternName, $version));
        $destination = "{$modulePath}/app/Http/Services/v{$version}/{$patternName}Service.php";

        File::put($destination, $content);
        $this->line("  <info>Created:</info> Http/Services/v{$version}/{$patternName}Service.php");
    }

    // -------------------------------------------------------------------------
    // Controller update
    // -------------------------------------------------------------------------

    private function updateController(string $modulePath, string $moduleName, string $patternName, string|int $version, string $stubSuffix = ''): void
    {
        $controllerPath = "{$modulePath}/app/Http/Controllers/v{$version}/{$moduleName}Controller.php";

        $content = $this->renderStub("controller{$stubSuffix}.stub", $this->replacements($moduleName, $patternName, $version));
        File::put($controllerPath, $content);
        $this->line("  <info>Created:</info> Http/Controllers/v{$version}/{$moduleName}Controller.php");
    }

    // -------------------------------------------------------------------------
    // Model creation
    // -------------------------------------------------------------------------

    private function createModel(string $modulePath, string $moduleName, string $patternName): void
    {
        $modelDir  = "{$modulePath}/app/Models";
        $modelPath = "{$modelDir}/{$patternName}.php";

        if (!File::exists($modelDir)) {
            File::makeDirectory($modelDir, 0755, true);
        }

        if (File::exists($modelPath)) {
            $this->line("  <comment>Skipped:</comment> Model already exists: Models/{$patternName}.php");
            return;
        }

        $content = $this->renderStub('model.stub', [
            'ModuleName'  => $moduleName,
            'PatternName' => $patternName,
        ]);

        File::put($modelPath, $content);
        $this->line("  <info>Created:</info> Models/{$patternName}.php");
    }

    // -------------------------------------------------------------------------
    // Service-provider update
    // -------------------------------------------------------------------------

    private function updateServiceProvider(string $modulePath, string $moduleName, string $patternName, string|int $version): void
    {
        $providerPath = "{$modulePath}/app/Providers/{$moduleName}ServiceProvider.php";

        if (!File::exists($providerPath)) {
            $this->warn("Service provider not found – skipping binding registration.");
            return;
        }

        $content = File::get($providerPath);

        $interfaceFqn  = "Modules\\{$moduleName}\\app\\Repository\\Contracts\\v{$version}\\{$patternName}RepositoryInterface";
        $repositoryFqn = "Modules\\{$moduleName}\\app\\Repository\\Eloquent\\v{$version}\\{$patternName}Repository";
        $versionedMarker = "v{$version}\\{$patternName}RepositoryInterface";

        // Skip if this exact versioned binding is already present
        if (str_contains($content, $versionedMarker)) {
            $this->line("  <comment>Skipped:</comment> Service provider already has v{$version} binding for {$patternName}.");
            return;
        }

        // Build the binding line
        $bindingLine = "        \$this->app->bind({$interfaceFqn}::class, {$repositoryFqn}::class);\n";

        // Inject into register() method body
        $registerPattern = '/public function register\(\)[^{]*\{/';
        if (preg_match($registerPattern, $content)) {
            $content = preg_replace(
                $registerPattern,
                "$1\n{$bindingLine}",
                $content,
                1
            );
        } else {
            $this->warn("Could not locate register() method in service provider.");
        }

        // Add use statements after the last existing use statement (or after namespace)
        $useStatements  = "use {$interfaceFqn};\n";
        $useStatements .= "use {$repositoryFqn};\n";

        if (preg_match('/^use .+;$/m', $content)) {
            // Insert after the LAST use statement block
            $content = preg_replace(
                '/^(use [^;]+;)(\s*)(?!use )/m',
                "$1\n{$useStatements}$2",
                $content,
                1
            );
        } else {
            $content = preg_replace(
                '/^(namespace .+;)$/m',
                "$1\n\n{$useStatements}",
                $content,
                1
            );
        }

        File::put($providerPath, $content);
        $this->line("  <info>Updated:</info> Service provider with v{$version} bindings.");
    }
}
