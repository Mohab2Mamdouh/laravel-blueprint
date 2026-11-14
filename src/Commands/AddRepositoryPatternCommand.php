<?php

namespace BlueprintPhp\Laravel\Commands;

use BlueprintPhp\Laravel\BlueprintServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AddRepositoryPatternCommand extends Command
{
    protected $signature = 'module:add-repository
                            {name : The entity / repository-pattern name (e.g. Attendance)}
                            {--module= : Existing module to place the files in (Modules/<Module>/app). Omit to generate in the app/ directory.}
                            {--ver=1 : Version number for the repository pattern}
                            {--crud : Use CRUD stubs for the Service and Controller (service-crud, controller-crud). The Interface and Repository always extend the CRUD-complete base.}
                            {--model : Generate a model using the pattern name}';

    protected $description = 'Add a versioned repository pattern (Service, Repository, Interface) to a module (--module=) or the app/ directory (default)';

    public function handle(): int
    {
        $patternName = Str::studly($this->argument('name'));
        $moduleInput = $this->option('module');
        $version = $this->option('ver');
        $isCrud = (bool) $this->option('crud');

        if (! is_numeric($version) || $version < 1) {
            $this->error('Version must be a positive integer!');

            return self::FAILURE;
        }

        // Resolve the generation target: a module (Modules/<Module>/app) or the
        // application itself (app/). Everything downstream is driven by these.
        $target = $this->resolveTarget($moduleInput, $patternName);

        if ($target === null) {
            return self::FAILURE;
        }

        $stubSuffix = $isCrud ? '-crud' : '';
        $this->line("  <comment>Target:</comment> {$target['label']}");
        $this->line($isCrud ? '  <comment>Mode:</comment> CRUD stubs' : '  <comment>Mode:</comment> Basic stubs');

        // The data layer (Interface + Repository) is shared and generated in every mode.
        $this->deleteExistingPattern($target, $patternName, $version);
        $this->createDataLayer($target, $patternName, $version);

        $this->createService($target, $patternName, $version, $stubSuffix);
        $this->updateController($target, $patternName, $version, $stubSuffix);

        // Register the interface→implementation binding in a RepositoryServiceProvider.
        $this->updateRepositoryServiceProvider($target, $patternName, $version);

        // For modules, make sure the generated provider is booted by the module's
        // main provider. The app providers are already in bootstrap/providers.php.
        if ($target['isModule']) {
            $this->registerServiceProviderInModule($target, 'RepositoryServiceProvider');
        }

        if ($this->option('model')) {
            $this->createModel($target, $patternName);
        }

        $mode = $isCrud ? 'CRUD' : 'basic';
        $this->info("Repository pattern [{$mode}] (V{$version}) named {$patternName} added to {$target['label']} successfully!");

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Target resolution (module vs app)
    // -------------------------------------------------------------------------

    /**
     * Build the generation target descriptor. Returns null (after printing an
     * error) when a module was requested but does not exist.
     *
     * @return array{
     *     isModule: bool,
     *     rootPath: string,
     *     rootNamespace: string,
     *     controllerName: string,
     *     viewPrefix: string,
     *     providerNamespace: string,
     *     label: string,
     *     modulePath: ?string,
     *     moduleName: ?string
     * }|null
     */
    private function resolveTarget(?string $moduleInput, string $patternName): ?array
    {
        if ($moduleInput === null || $moduleInput === '') {
            // App mode — generate under app/ with the App namespace.
            return [
                'isModule' => false,
                'rootPath' => base_path('app'),
                'rootNamespace' => 'App',
                'controllerName' => $patternName,
                'viewPrefix' => '',
                'providerNamespace' => 'App\\Providers',
                'label' => 'app/ directory',
                'modulePath' => null,
                'moduleName' => null,
            ];
        }

        // Module mode — validate the module exists before touching anything.
        $moduleName = Str::studly($moduleInput);
        $modulePath = base_path("Modules/{$moduleName}");

        if (! File::isDirectory($modulePath)) {
            $this->error("Module {$moduleName} does not exist! Create it first with: php artisan module:make {$moduleName}");

            return null;
        }

        // nwidart v13 maps `Modules\<Module>\` to the module's `app/` folder, so
        // `app/` is the PSR-4 root — there is NO `app` segment in the namespace.
        return [
            'isModule' => true,
            'rootPath' => "{$modulePath}/app",
            'rootNamespace' => "Modules\\{$moduleName}",
            'controllerName' => $moduleName,
            'viewPrefix' => "{$moduleName}::",
            'providerNamespace' => "Modules\\{$moduleName}\\Providers",
            'label' => "Modules/{$moduleName}",
            'modulePath' => $modulePath,
            'moduleName' => $moduleName,
        ];
    }

    // -------------------------------------------------------------------------
    // Deletion
    // -------------------------------------------------------------------------

    private function deleteExistingPattern(array $target, string $patternName, string|int $version): void
    {
        $root = $target['rootPath'];

        $filesToDelete = [
            "{$root}/Http/Services/V{$version}/{$patternName}Service.php",
            "{$root}/Repository/Eloquent/V{$version}/{$patternName}Repository.php",
            "{$root}/Repository/Contracts/V{$version}/{$patternName}RepositoryInterface.php",
            "{$root}/Http/Controllers/V{$version}/{$target['controllerName']}Controller.php",
        ];

        foreach ($filesToDelete as $file) {
            if (File::exists($file)) {
                File::delete($file);
                $this->line('  <comment>Deleted:</comment> '.basename($file));
            }
        }
    }

    // -------------------------------------------------------------------------
    // Directory + file creation
    // -------------------------------------------------------------------------

    private function createDataLayer(array $target, string $patternName, string|int $version): void
    {
        $root = $target['rootPath'];

        $directories = [
            "{$root}/Repository/Contracts/V{$version}",
            "{$root}/Repository/Eloquent/V{$version}",
        ];

        foreach ($directories as $dir) {
            if (! File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
        }

        $this->createInterface($target, $patternName, $version);
        $this->createRepository($target, $patternName, $version);
    }

    // -------------------------------------------------------------------------
    // Stub rendering helpers
    // -------------------------------------------------------------------------

    /**
     * Load a stub file and replace all placeholders.
     *
     * @param  string  $stubName  Filename inside stubs/ (without path)
     * @param  array<string,string>  $replacements  Key => Value pairs to replace
     */
    private function renderStub(string $stubName, array $replacements): string
    {
        $stubPath = BlueprintServiceProvider::stubPath($stubName);

        if (! File::exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");

            return '';
        }

        $content = File::get($stubPath);

        foreach ($replacements as $placeholder => $value) {
            $content = str_replace("{{ {$placeholder} }}", $value, $content);
        }

        return $content;
    }

    /**
     * @return array<string,string>
     */
    private function replacements(array $target, string $patternName, string|int $version): array
    {
        return [
            'RootNamespace' => $target['rootNamespace'],
            'ControllerName' => $target['controllerName'],
            'ViewPrefix' => $target['viewPrefix'],
            'PatternName' => $patternName,
            'Version' => (string) $version,
        ];
    }

    // -------------------------------------------------------------------------
    // Individual file generators
    // -------------------------------------------------------------------------

    private function createInterface(array $target, string $patternName, string|int $version): void
    {
        $content = $this->renderStub('interface.stub', $this->replacements($target, $patternName, $version));
        $destination = "{$target['rootPath']}/Repository/Contracts/V{$version}/{$patternName}RepositoryInterface.php";

        File::put($destination, $content);
        $this->line("  <info>Created:</info> Repository/Contracts/V{$version}/{$patternName}RepositoryInterface.php");
    }

    private function createRepository(array $target, string $patternName, string|int $version): void
    {
        $content = $this->renderStub('repository.stub', $this->replacements($target, $patternName, $version));
        $destination = "{$target['rootPath']}/Repository/Eloquent/V{$version}/{$patternName}Repository.php";

        File::put($destination, $content);
        $this->line("  <info>Created:</info> Repository/Eloquent/V{$version}/{$patternName}Repository.php");
    }

    private function createService(array $target, string $patternName, string|int $version, string $stubSuffix = ''): void
    {
        $content = $this->renderStub("service{$stubSuffix}.stub", $this->replacements($target, $patternName, $version));
        $destination = "{$target['rootPath']}/Http/Services/V{$version}/{$patternName}Service.php";

        File::ensureDirectoryExists(dirname($destination));
        File::put($destination, $content);
        $this->line("  <info>Created:</info> Http/Services/V{$version}/{$patternName}Service.php");
    }

    // -------------------------------------------------------------------------
    // Controller update
    // -------------------------------------------------------------------------

    private function updateController(array $target, string $patternName, string|int $version, string $stubSuffix = ''): void
    {
        $controllerName = $target['controllerName'];
        $controllerPath = "{$target['rootPath']}/Http/Controllers/V{$version}/{$controllerName}Controller.php";

        $content = $this->renderStub("controller{$stubSuffix}.stub", $this->replacements($target, $patternName, $version));
        File::ensureDirectoryExists(dirname($controllerPath));
        File::put($controllerPath, $content);
        $this->line("  <info>Created:</info> Http/Controllers/V{$version}/{$controllerName}Controller.php");
    }

    // -------------------------------------------------------------------------
    // Model creation
    // -------------------------------------------------------------------------

    private function createModel(array $target, string $patternName): void
    {
        $modelDir = "{$target['rootPath']}/Models";
        $modelPath = "{$modelDir}/{$patternName}.php";

        if (! File::exists($modelDir)) {
            File::makeDirectory($modelDir, 0755, true);
        }

        if (File::exists($modelPath)) {
            $this->line("  <comment>Skipped:</comment> Model already exists: Models/{$patternName}.php");

            return;
        }

        $content = $this->renderStub('model.stub', $this->replacements($target, $patternName, 1));

        File::put($modelPath, $content);
        $this->line("  <info>Created:</info> Models/{$patternName}.php");
    }

    // -------------------------------------------------------------------------
    // RepositoryServiceProvider — create / update
    // -------------------------------------------------------------------------

    /**
     * Ensure a RepositoryServiceProvider exists for the target, then inject the
     * interface→repository binding for the requested version + pattern name.
     */
    private function updateRepositoryServiceProvider(array $target, string $patternName, string|int $version): void
    {
        $providerDir = "{$target['rootPath']}/Providers";
        $providerPath = "{$providerDir}/RepositoryServiceProvider.php";

        // Create the file from scratch when it doesn't exist yet. In app mode the
        // provider already exists (and is registered in bootstrap/providers.php).
        if (! File::exists($providerPath)) {
            if (! File::exists($providerDir)) {
                File::makeDirectory($providerDir, 0755, true);
            }
            $this->createRepositoryServiceProvider($providerPath, $target['providerNamespace']);
        }

        $content = File::get($providerPath);

        $interfaceShort = "{$patternName}RepositoryInterface";
        $repositoryShort = "{$patternName}Repository";
        $interfaceFqn = "{$target['rootNamespace']}\\Repository\\Contracts\\V{$version}\\{$interfaceShort}";
        $repositoryFqn = "{$target['rootNamespace']}\\Repository\\Eloquent\\V{$version}\\{$repositoryShort}";
        $versionedMarker = "V{$version}\\{$interfaceShort}";

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
            $insertAt = $lastMatch[1] + strlen($lastMatch[0]);
            $content = substr($content, 0, $insertAt)."\n".$useBlock.substr($content, $insertAt);
        } else {
            // No use statements yet — add a blank line after the namespace declaration.
            $content = preg_replace(
                '/^(namespace .+;)$/m',
                "$1\n\n".$useBlock,
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
     * Scaffold an empty RepositoryServiceProvider under the given namespace.
     */
    private function createRepositoryServiceProvider(string $providerPath, string $providerNamespace): void
    {
        $content = <<<PHP
        <?php

        namespace {$providerNamespace};

        use Illuminate\\Support\\ServiceProvider;

        class RepositoryServiceProvider extends ServiceProvider
        {
            public function register(): void
            {
                //
            }
        }
        PHP;

        File::put($providerPath, $content."\n");
        $this->line('  <info>Created:</info> Providers/RepositoryServiceProvider.php');
    }

    // -------------------------------------------------------------------------
    // Module's main ServiceProvider — register a generated provider
    // -------------------------------------------------------------------------

    /**
     * Add `<ProviderShort>::class` to the module's primary ServiceProvider
     * `$providers` array if it isn't already there.
     */
    private function registerServiceProviderInModule(array $target, string $providerShort): void
    {
        $moduleName = $target['moduleName'];
        $providerPath = "{$target['rootPath']}/Providers/{$moduleName}ServiceProvider.php";

        if (! File::exists($providerPath)) {
            $this->warn("{$providerPath} not found — skipping {$providerShort} registration.");

            return;
        }

        $content = File::get($providerPath);

        // ------------------------------------------------------------------
        // 0. Guard — already registered
        // ------------------------------------------------------------------
        if (str_contains($content, "{$providerShort}::class")) {
            $this->line("  <comment>Skipped:</comment> {$moduleName}ServiceProvider already registers {$providerShort}.");

            return;
        }

        // ------------------------------------------------------------------
        // 1. Inject use statement
        // ------------------------------------------------------------------
        $fqn = "{$target['providerNamespace']}\\{$providerShort}";
        $useStatement = "use {$fqn};\n";

        if (! str_contains($content, $fqn)) {
            if (preg_match_all('/^use [^;]+;$/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $lastMatch = end($matches[0]);
                $insertAt = $lastMatch[1] + strlen($lastMatch[0]);
                $content = substr($content, 0, $insertAt)."\n".$useStatement.substr($content, $insertAt);
            } else {
                $content = preg_replace(
                    '/^(namespace .+;)$/m',
                    "$1\n\n".$useStatement,
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
            $newLine = "{$providerShort}::class,\n";

            $updatedProvidersBlock = str_replace(
                $providersInner,
                $providersInner.$newLine,
                $providersBlock
            );

            $content = str_replace($providersBlock, $updatedProvidersBlock, $content);

            $this->line("  <info>Updated:</info> Added {$providerShort} to \$providers in {$moduleName}ServiceProvider.");
        } else {
            // ------------------------------------------------------------------
            // 3. If $providers does NOT exist → create it
            // ------------------------------------------------------------------
            $property = "\n    protected array \$providers = [\n        {$providerShort}::class,\n    ];\n";

            $content = preg_replace(
                '/class\s+'.$moduleName.'ServiceProvider[^{]*\{/',
                '$0'.$property,
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
