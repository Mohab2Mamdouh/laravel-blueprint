<?php

namespace BlueprintPhp\Laravel\Commands;

use BlueprintPhp\Laravel\BlueprintServiceProvider;
use Illuminate\Support\Str;

/**
 * Shared module/app resolution + provider-registration logic for the platform
 * code-generation commands (make:platform-service / -request / -resource / repo).
 *
 * App target (no --module): namespace root `App\`, path root `app/` — exactly
 * like v6. Module target (--module=Attendance): files under
 * `Modules/Attendance/app/…` with namespace root `Modules\Attendance\` (nwidart
 * maps `app/` as the PSR-4 root, so there is NO `app` segment in the namespace).
 */
trait ResolvesPlatformTarget
{
    /**
     * Build the generation target descriptor. Returns null (after printing an
     * error) when a module was requested but does not exist.
     *
     * @return array{
     *     isModule: bool,
     *     baseNs: string,
     *     basePath: string,
     *     providersPath: string,
     *     providersNs: string,
     *     moduleName: ?string,
     *     label: string
     * }|null
     */
    protected function resolveTarget(?string $moduleInput): ?array
    {
        if ($moduleInput === null || $moduleInput === '') {
            // App mode — generate under app/ with the App namespace.
            return [
                'isModule' => false,
                'baseNs' => 'App',
                'basePath' => app_path(),
                'providersPath' => app_path('Providers'),
                'providersNs' => 'App\\Providers',
                'moduleName' => null,
                'label' => 'app/ directory',
            ];
        }

        // Module mode — validate the module exists before touching anything.
        $moduleName = Str::studly($moduleInput);
        $modulePath = base_path("Modules/{$moduleName}");

        if (! $this->files->isDirectory($modulePath)) {
            $this->error("Module {$moduleName} does not exist! Create it first with: php artisan module:make {$moduleName}");

            return null;
        }

        // nwidart maps `Modules\<Module>\` to the module's `app/` folder, so
        // `app/` is the PSR-4 root — there is NO `app` segment in the namespace.
        return [
            'isModule' => true,
            'baseNs' => "Modules\\{$moduleName}",
            'basePath' => "{$modulePath}/app",
            'providersPath' => "{$modulePath}/app/Providers",
            'providersNs' => "Modules\\{$moduleName}\\Providers",
            'moduleName' => $moduleName,
            'label' => "Modules/{$moduleName}",
        ];
    }

    // ===== Stub helpers =====

    protected function stubPath(string $stub): string
    {
        return BlueprintServiceProvider::stubPath($stub);
    }

    protected function stub(string $stub, array $replacements): string
    {
        $content = $this->files->get($this->stubPath($stub));
        foreach ($replacements as $search => $replace) {
            $content = str_replace('{{ '.$search.' }}', $replace, $content);
        }

        return $content;
    }

    // ===== File helpers =====

    protected function makeDirectory(string $dir): void
    {
        if (! $this->files->isDirectory($dir)) {
            $this->files->makeDirectory($dir, 0777, true);
        }
    }

    protected function writeFile(string $path, string $contents): void
    {
        $this->files->put($path, $contents);
    }

    /**
     * Convert a FQCN into an absolute file path within the target's base path,
     * stripping the base namespace prefix (which maps onto the base path).
     */
    protected function fqcnToPath(array $target, string $fqcn): string
    {
        $fqcn = ltrim($fqcn, '\\');
        $relative = Str::after($fqcn, $target['baseNs'].'\\');

        return $target['basePath'].'/'.str_replace('\\', '/', $relative).'.php';
    }

    // ===== PlatformServiceProvider path resolution =====

    /**
     * Resolve the PlatformServiceProvider path for the target, creating a module
     * provider (and registering it in the module's main provider) when missing.
     */
    protected function platformProviderPath(array $target): string
    {
        $providerPath = "{$target['providersPath']}/PlatformServiceProvider.php";

        if (! $target['isModule']) {
            return $providerPath;
        }

        if (! $this->files->exists($providerPath)) {
            $this->makeDirectory($target['providersPath']);
            $this->createModulePlatformServiceProvider($providerPath, $target['providersNs']);
            $this->registerServiceProviderInModule($target, 'PlatformServiceProvider');
        }

        return $providerPath;
    }

    /**
     * Resolve the RepositoryServiceProvider path for the target, creating a
     * module provider (and registering it in the module's main provider) when
     * missing.
     */
    protected function repositoryProviderPath(array $target): string
    {
        $providerPath = "{$target['providersPath']}/RepositoryServiceProvider.php";

        if (! $target['isModule']) {
            return $providerPath;
        }

        if (! $this->files->exists($providerPath)) {
            $this->makeDirectory($target['providersPath']);
            $this->createModuleRepositoryServiceProvider($providerPath, $target['providersNs']);
            $this->registerServiceProviderInModule($target, 'RepositoryServiceProvider');
        }

        return $providerPath;
    }

    protected function createModulePlatformServiceProvider(string $providerPath, string $providerNamespace): void
    {
        $content = <<<PHP
        <?php

        namespace {$providerNamespace};

        use BlueprintPhp\\Laravel\\Providers\\PlatformBindingServiceProvider;

        /**
         * Resolves each Abstract Service/Request/Resource to its platform concrete
         * based on the `X-Platform` request header (web | mobile), keyed by API
         * version. Add entries in the version-`match` blocks below.
         */
        class PlatformServiceProvider extends PlatformBindingServiceProvider
        {
            protected function getServiceImplementations(int \$version): array
            {
                return match (\$version) {
                    1 => [
                    ],
                    default => [],
                };
            }

            protected function getRequestImplementations(int \$version): array
            {
                return match (\$version) {
                    1 => [
                    ],
                    default => [],
                };
            }

            protected function getResourceImplementations(int \$version): array
            {
                return match (\$version) {
                    1 => [
                    ],
                    default => [],
                };
            }
        }
        PHP;

        $this->writeFile($providerPath, $content."\n");
        $this->info("Created: {$providerPath}");
    }

    protected function createModuleRepositoryServiceProvider(string $providerPath, string $providerNamespace): void
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

        $this->writeFile($providerPath, $content."\n");
        $this->info("Created: {$providerPath}");
    }

    /**
     * Add `<ProviderShort>::class` to the module's primary ServiceProvider
     * `$providers` array if it isn't already there.
     */
    protected function registerServiceProviderInModule(array $target, string $providerShort): void
    {
        $moduleName = $target['moduleName'];
        $providerPath = "{$target['providersPath']}/{$moduleName}ServiceProvider.php";

        if (! $this->files->exists($providerPath)) {
            $this->warn("{$providerPath} not found — skipping {$providerShort} registration.");

            return;
        }

        $content = $this->files->get($providerPath);

        // Guard — already registered
        if (str_contains($content, "{$providerShort}::class")) {
            return;
        }

        // 1. Inject use statement
        $fqn = "{$target['providersNs']}\\{$providerShort}";
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

        // 2. Add to $providers array
        if (preg_match('/protected\s+array\s+\$providers\s*=\s*\[([\s\S]*?)\];/', $content, $matches)) {
            $providersBlock = $matches[0];
            $providersInner = $matches[1];

            $newLine = "{$providerShort}::class,\n";

            $updatedProvidersBlock = str_replace(
                $providersInner,
                $providersInner.$newLine,
                $providersBlock
            );

            $content = str_replace($providersBlock, $updatedProvidersBlock, $content);
        } else {
            // 3. If $providers does NOT exist → create it
            $property = "\n    protected array \$providers = [\n        {$providerShort}::class,\n    ];\n";

            $content = preg_replace(
                '/class\s+'.$moduleName.'ServiceProvider[^{]*\{/',
                '$0'.$property,
                $content,
                1
            );
        }

        $this->files->put($providerPath, $content);
        $this->info("Updated: {$moduleName}ServiceProvider — registered {$providerShort}.");
    }

    // ===== RepositoryServiceProvider binding injection (verbatim from v6) =====

    protected function registerInRepositoryServiceProvider(array $target, string $interfaceFqn, string $eloquentFqn): void
    {
        $providerPath = $this->repositoryProviderPath($target);
        if (! $this->files->exists($providerPath)) {
            $this->error('RepositoryServiceProvider.php not found. Skipping repository registration.');

            return;
        }

        $content = $this->files->get($providerPath);

        // Normalize FQCNs
        $interfaceFqn = ltrim($interfaceFqn, '\\');
        $eloquentFqn = ltrim($eloquentFqn, '\\');

        $interfaceShort = class_basename($interfaceFqn);
        $eloquentShort = class_basename($eloquentFqn);

        // Ensure clean `use` imports (no leading backslash, no ::class in use)
        foreach ([$interfaceFqn, $eloquentFqn] as $fqcn) {
            $useStmt = "use {$fqcn};";
            if (! str_contains($content, $useStmt)) {
                $content = preg_replace(
                    '/^(namespace\s+[^\n]+;\s*\R)/m',
                    "$0{$useStmt}\n",
                    $content,
                    1
                );
            }
        }

        // Binding line using short names; no extra spaces
        $bindLine = "        \$this->app->singleton({$interfaceShort}::class, {$eloquentShort}::class);";

        // Skip if already present
        if (str_contains($content, $bindLine)) {
            $this->files->put($providerPath, $content);
            $this->line('RepositoryServiceProvider already has this binding.');

            return;
        }

        // Insert inside register()
        if (preg_match('/public function register\(\)\s*(?::\s*void\s*)?\{/', $content, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);
            $content = substr($content, 0, $pos)."\n".$bindLine.substr($content, $pos);
        } else {
            // Fallback: append to end
            $content .= "\n{$bindLine}\n";
        }

        $this->files->put($providerPath, $content);
        $this->info('RepositoryServiceProvider updated.');
    }

    // ===== PlatformServiceProvider injection (verbatim from v6, per section) =====

    protected function registerServiceInPlatformServiceProvider(array $target, string $abstractFqn, array $concretes): void
    {
        $this->injectPlatformImplementation($target, 'getServiceImplementations', $abstractFqn, $concretes, 'services');
    }

    protected function registerRequestInPlatformServiceProvider(array $target, string $abstractFqn, array $concretes): void
    {
        $this->injectPlatformImplementation($target, 'getRequestImplementations', $abstractFqn, $concretes, 'requests');
    }

    protected function registerResourceInPlatformServiceProvider(array $target, string $abstractFqn, array $concretes): void
    {
        $this->injectPlatformImplementation($target, 'getResourceImplementations', $abstractFqn, $concretes, 'resources');
    }

    /**
     * Inject an abstract => [concretes] entry into one of the version-keyed
     * `match` blocks of the target's PlatformServiceProvider. Ported verbatim
     * from v6's per-section registration logic (shared here across sections).
     */
    private function injectPlatformImplementation(array $target, string $method, string $abstractFqn, array $concretes, string $label): void
    {
        $providerPath = $this->platformProviderPath($target);
        if (! $this->files->exists($providerPath)) {
            $this->error('PlatformServiceProvider.php not found. Skipping platform registration.');

            return;
        }

        $content = $this->files->get($providerPath);

        // Extract version from abstractFqn (e.g., \App\Http\Services\V1\...)
        preg_match('/\\\\V(\d+)\\\\/', $abstractFqn, $matches);
        $version = $matches[1] ?? '1';

        // Build the implementation array with proper formatting (each on new line)
        $uniqueConcretes = collect($concretes)->unique()->values()->all();
        $implLines = array_map(fn ($impl) => "                    {$impl},", $uniqueConcretes);
        $implArray = "[\n".implode("\n", $implLines)."\n                ]";

        $newEntry = "{$abstractFqn} => {$implArray},";

        // Pattern to find the version block in the target method
        $versionPattern = '/(private function '.$method.'\(int \$version\): array\s*\{\s*return match \(\$version\) \{.*?'.$version.' => \[)(.*?)(\n            \],\n(?=(?:\s*\/\/.*\n)*\s*(?:            \d+|            default|        \})))/s';

        // The base declares these methods `abstract protected`, so concretes use
        // `protected`. Match either visibility.
        $versionPattern = str_replace('private function', '(?:private|protected) function', $versionPattern);

        if (preg_match($versionPattern, $content, $matches)) {
            // Version block exists
            $existingBindings = $matches[2];

            // Check if abstract already exists
            if (preg_match('/'.preg_quote($abstractFqn, '/').' => \[.*?\],/s', $existingBindings)) {
                // Update existing entry
                $content = preg_replace(
                    '/((?:private|protected) function '.$method.'.*?'.$version.' => \[.*?)'.preg_quote($abstractFqn, '/').' => \[.*?\],/s',
                    "$1{$newEntry}",
                    $content,
                    1
                );
            } else {
                // Add new entry before the closing bracket
                $replacement = "$1$2\n                {$newEntry}$3";
                $content = preg_replace($versionPattern, $replacement, $content, 1);
            }
        } else {
            // Version block doesn't exist - create it
            $newVersionBlock = "            {$version} => [\n                {$newEntry}\n            ],\n";

            // Insert before the default block (most reliable anchor point)
            $insertPattern = '/((?:private|protected) function '.$method.'\(int \$version\): array\s*\{\s*return match \(\$version\) \{.*?)(            default => \[\],)/s';

            if (preg_match($insertPattern, $content)) {
                $content = preg_replace(
                    $insertPattern,
                    "$1{$newVersionBlock}$2",
                    $content,
                    1
                );
                $this->info("Created new version {$version} block in {$method}.");
            } else {
                $this->warn("Could not automatically add version {$version} block. Please add it manually to {$method}().");
            }
        }

        $this->files->put($providerPath, $content);
        $this->info("PlatformServiceProvider updated ({$label}).");
    }
}
