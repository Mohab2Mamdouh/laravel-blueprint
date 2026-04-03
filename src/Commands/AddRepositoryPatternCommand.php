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

        // Register the binding in a dedicated RepositoryServiceProvider …
        $this->updateRepositoryServiceProvider($modulePath, $studlyName, $patternName, $version);
        // … then make sure that provider is booted by the module's main provider.
        $this->registerRepositoryServiceProviderInModule($modulePath, $studlyName);

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

        $this->createInterface($modulePath, $moduleName, $patternName, $version);
        $this->createRepository($modulePath, $moduleName, $patternName, $version);
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

    private function createInterface(string $modulePath, string $moduleName, string $patternName, string|int $version): void
    {
        $content     = $this->renderStub("interface.stub", $this->replacements($moduleName, $patternName, $version));
        $destination = "{$modulePath}/app/Repository/Contracts/v{$version}/{$patternName}RepositoryInterface.php";

        File::put($destination, $content);
        $this->line("  <info>Created:</info> Repository/Contracts/v{$version}/{$patternName}RepositoryInterface.php");
    }

    private function createRepository(string $modulePath, string $moduleName, string $patternName, string|int $version): void
    {
        $content     = $this->renderStub("repository.stub", $this->replacements($moduleName, $patternName, $version));
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
    // RepositoryServiceProvider — create / update
    // -------------------------------------------------------------------------

    /**
     * Ensure RepositoryServiceProvider exists for the module, then inject the
     * interface→repository binding for the requested version + pattern name.
     */
    private function updateRepositoryServiceProvider(
        string     $modulePath,
        string     $moduleName,
        string     $patternName,
        string|int $version,
    ): void {
        $providerDir  = "{$modulePath}/app/Providers";
        $providerPath = "{$providerDir}/RepositoryServiceProvider.php";

        // Create the file from scratch when it doesn't exist yet.
        if (!File::exists($providerPath)) {
            if (!File::exists($providerDir)) {
                File::makeDirectory($providerDir, 0755, true);
            }
            $this->createRepositoryServiceProvider($providerPath, $moduleName);
        }

        $content = File::get($providerPath);

        $interfaceShort  = "{$patternName}RepositoryInterface";
        $repositoryShort = "{$patternName}Repository";
        $interfaceFqn    = "Modules\\{$moduleName}\\app\\Repository\\Contracts\\v{$version}\\{$interfaceShort}";
        $repositoryFqn   = "Modules\\{$moduleName}\\app\\Repository\\Eloquent\\v{$version}\\{$repositoryShort}";
        $versionedMarker = "v{$version}\\{$interfaceShort}";

        // Guard — skip if this exact versioned binding is already registered.
        if (str_contains($content, $versionedMarker)) {
            $this->line("  <comment>Skipped:</comment> RepositoryServiceProvider already contains v{$version} binding for {$patternName}.");
            return;
        }

        // ------------------------------------------------------------------
        // 1. Inject use-statements after the last existing use line.
        // ------------------------------------------------------------------
        $useBlock = "use {$interfaceFqn};\nuse {$repositoryFqn};\n";

        if (preg_match_all('/^use [^;]+;$/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            // Insert right after the last use statement.
            $lastMatch = end($matches[0]);
            $insertAt  = $lastMatch[1] + strlen($lastMatch[0]);
            $content   = substr($content, 0, $insertAt) . "\n" . $useBlock . substr($content, $insertAt);
        } else {
            // No use statements yet — add a blank line after the namespace declaration.
            $content = preg_replace(
                '/^(namespace .+;)$/m',
                "$1\n\n" . $useBlock,
                $content,
                1,
            );
        }

        // ------------------------------------------------------------------
        // 2. Inject the bind() call into the register() method body.
        // ------------------------------------------------------------------
        $bindingLine = "        \$this->app->bind({$interfaceShort}::class, {$repositoryShort}::class);\n";

        $content = preg_replace(
            '/(public function register\(\)[^{]*\{)/',
            "$1\n{$bindingLine}",
            $content,
            1,
        );

        File::put($providerPath, $content);
        $this->line("  <info>Updated:</info> Providers/RepositoryServiceProvider.php — added v{$version} binding for {$patternName}.");
    }

    /**
     * Scaffold an empty RepositoryServiceProvider for the given module.
     */
    private function createRepositoryServiceProvider(string $providerPath, string $moduleName): void
    {
        $content = <<<PHP
        <?php

        namespace Modules\\{$moduleName}\\app\\Providers;

        use Illuminate\\Support\\ServiceProvider;

        class RepositoryServiceProvider extends ServiceProvider
        {
            public function register(): void
            {
                //
            }
        }
        PHP;

        // Normalise indentation produced by the heredoc.
        $content = implode("\n", array_map(
            static fn (string $line) => preg_replace('/^\s{8}/', '', $line),
            explode("\n", $content),
        ));

        File::put($providerPath, $content);
        $this->line("  <info>Created:</info> Providers/RepositoryServiceProvider.php");
    }

    // -------------------------------------------------------------------------
    // Module's main ServiceProvider — register RepositoryServiceProvider
    // -------------------------------------------------------------------------

    /**
     * Add `$this->app->register(RepositoryServiceProvider::class)` to the
     * module's primary ServiceProvider if it isn't already there.
     */
    private function registerRepositoryServiceProviderInModule(string $modulePath, string $moduleName): void
    {
        $providerPath = "{$modulePath}/app/Providers/{$moduleName}ServiceProvider.php";

        if (!File::exists($providerPath)) {
            $this->warn("{$providerPath} not found — skipping RepositoryServiceProvider registration.");
            return;
        }

        $content = File::get($providerPath);

        // ------------------------------------------------------------------
        // 0. Guard — already registered
        // ------------------------------------------------------------------
        if (str_contains($content, 'RepositoryServiceProvider::class')) {
            $this->line("  <comment>Skipped:</comment> {$moduleName}ServiceProvider already registers RepositoryServiceProvider.");
            return;
        }

        // ------------------------------------------------------------------
        // 1. Inject use statement
        // ------------------------------------------------------------------
        $fqn          = "Modules\\{$moduleName}\\app\\Providers\\RepositoryServiceProvider";
        $useStatement = "use {$fqn};\n";

        if (!str_contains($content, $fqn)) {
            if (preg_match_all('/^use [^;]+;$/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $lastMatch = end($matches[0]);
                $insertAt  = $lastMatch[1] + strlen($lastMatch[0]);
                $content   = substr($content, 0, $insertAt) . "\n" . $useStatement . substr($content, $insertAt);
            } else {
                $content = preg_replace(
                    '/^(namespace .+;)$/m',
                    "$1\n\n" . $useStatement,
                    $content,
                    1,
                );
            }
        }

        // ------------------------------------------------------------------
        // 2. Add to $providers array
        // ------------------------------------------------------------------
        if (preg_match('/protected\s+array\s+\$providers\s*=\s*\[([\s\S]*?)\];/', $content, $matches)) {
            $providersBlock = $matches[0];
            $providersInner = $matches[1];

            // Add new provider with proper indentation
            $newLine = "RepositoryServiceProvider::class,\n";

            $updatedProvidersBlock = str_replace(
                $providersInner,
                $providersInner . $newLine,
                $providersBlock
            );

            $content = str_replace($providersBlock, $updatedProvidersBlock, $content);

            $this->line("  <info>Updated:</info> Added RepositoryServiceProvider to \$providers in {$moduleName}ServiceProvider.");
        } else {
            // ------------------------------------------------------------------
            // 3. If $providers does NOT exist → create it
            // ------------------------------------------------------------------
            $property = "\n    protected array \$providers = [\n        RepositoryServiceProvider::class,\n    ];\n";

            $content = preg_replace(
                '/class\s+' . $moduleName . 'ServiceProvider[^{]*\{/',
                "$0" . $property,
                $content,
                1
            );

            $this->line("  <info>Added:</info> Created \$providers property in {$moduleName}ServiceProvider.");
        }

        // ------------------------------------------------------------------
        // 4. Save file
        // ------------------------------------------------------------------
        File::put($providerPath, $content);
    }
}