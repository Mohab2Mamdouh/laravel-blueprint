<?php

namespace BlueprintPhp\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'make:repo',
    description: 'Generate Repository interface + Eloquent implementation and register binding in RepositoryServiceProvider (supports tenant repos/models)'
)]
class MakeRepo extends Command
{
    use ResolvesPlatformTarget;

    protected $signature = 'make:repo
        {name : Base name, e.g. Role}
        {--model= : Eloquent model FQCN or short name (defaults to <BaseNs>\Models\<Name> or <BaseNs>\Models\Tenant\<Name> with --tenant)}
        {--tenant : Generate under Contracts/Tenant and Eloquent/Tenant; default model under Models\Tenant}
        {--no-model : Skip model creation even if it does not exist}
        {--module= : Target module (Modules/<M>/app); omit for app/}
        {--force : Overwrite files if they exist}
    ';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));    // e.g. Role
        $modelOpt = $this->option('model');                   // e.g. App\Models\Something or Something
        $isTenant = (bool) $this->option('tenant');
        $force = (bool) $this->option('force');
        $noModel = (bool) $this->option('no-model');

        $target = $this->resolveTarget($this->option('module'));
        if ($target === null) {
            return self::FAILURE;
        }

        $baseNs = $target['baseNs'];
        $basePath = $target['basePath'];

        $modelsNs = $isTenant ? "{$baseNs}\\Models\\Tenant" : "{$baseNs}\\Models";

        // ==== Resolve model FQCN (short names resolved by tenancy) ====
        if ($modelOpt) {
            $modelFqn = str_contains($modelOpt, '\\')
                ? ltrim($modelOpt, '\\')
                : "{$modelsNs}\\".Str::studly($modelOpt);
        } else {
            $modelFqn = "{$modelsNs}\\{$name}";
        }

        // ==== Create model if it doesn't exist (unless --no-model is set) ====
        if (! $noModel) {
            $this->ensureModelExists($target, $modelFqn, $isTenant, $name);
        }

        // ==== Namespaces & paths (Contracts & Eloquent with Tenant variants) ====
        if ($isTenant) {
            $repoInterfaceNs = "{$baseNs}\\Repository\\Contracts\\Tenant";
            $repoInterfaceDir = "{$basePath}/Repository/Contracts/Tenant";
            $repoEloquentNs = "{$baseNs}\\Repository\\Eloquent\\Tenant";
            $repoEloquentDir = "{$basePath}/Repository/Eloquent/Tenant";
        } else {
            $repoInterfaceNs = "{$baseNs}\\Repository\\Contracts";
            $repoInterfaceDir = "{$basePath}/Repository/Contracts";
            $repoEloquentNs = "{$baseNs}\\Repository\\Eloquent";
            $repoEloquentDir = "{$basePath}/Repository/Eloquent";
        }

        $repoInterfaceCls = "{$name}RepositoryInterface";
        $repoInterfacePath = "{$repoInterfaceDir}/{$repoInterfaceCls}.php";

        $repoEloquentCls = "{$name}Repository";
        $repoEloquentPath = "{$repoEloquentDir}/{$repoEloquentCls}.php";

        // ==== Create interface ====
        $this->makeDirectory($repoInterfaceDir);
        if ($force || ! $this->files->exists($repoInterfacePath)) {
            $this->writeFile($repoInterfacePath, $this->renderRepoInterface($repoInterfaceNs, $repoInterfaceCls));
            $this->info("Created: {$repoInterfacePath}");
        } else {
            $this->line("Exists:  {$repoInterfacePath}");
        }

        // ==== Create eloquent implementation ====
        $this->makeDirectory($repoEloquentDir);
        if ($force || ! $this->files->exists($repoEloquentPath)) {
            $this->writeFile(
                $repoEloquentPath,
                $this->renderRepoConcrete(
                    $repoEloquentNs,
                    $repoEloquentCls,
                    $modelFqn,
                    $repoInterfaceNs,
                    $repoInterfaceCls
                )
            );
            $this->info("Created: {$repoEloquentPath}");
        } else {
            $this->line("Exists:  {$repoEloquentPath}");
        }

        // ==== Register binding in RepositoryServiceProvider with clean imports ====
        $this->registerInRepositoryServiceProvider(
            $target,
            "{$repoInterfaceNs}\\{$repoInterfaceCls}",
            "{$repoEloquentNs}\\{$repoEloquentCls}"
        );

        $this->info('Repository scaffolding complete ✅');

        return self::SUCCESS;
    }

    // ===== Renderers =====

    private function renderRepoInterface(string $namespace, string $class): string
    {
        return $this->stub('platform-repository.interface.stub', [
            'namespace' => $namespace,
            'class' => $class,
        ]);
    }

    private function renderRepoConcrete(
        string $namespace,
        string $class,
        string $modelFqn,        // e.g. App\Models\Role or App\Models\Tenant\Role
        string $interfaceNs,     // e.g. App\Repository\Contracts or App\Repository\Contracts\Tenant
        string $interfaceCls     // e.g. RoleRepositoryInterface
    ): string {
        $modelFqn = ltrim($modelFqn, '\\');

        return $this->stub('platform-repository.eloquent.stub', [
            'namespace' => $namespace,
            'class' => $class,
            'model_fqn' => $modelFqn,
            'model_class' => class_basename($modelFqn),
            'interface_namespace' => $interfaceNs,
            'interface_class' => $interfaceCls,
        ]);
    }

    // ===== Model creation =====

    private function ensureModelExists(array $target, string $modelFqn, bool $isTenant, string $name): void
    {
        $modelPath = $this->fqcnToPath($target, $modelFqn);

        if ($this->files->exists($modelPath)) {
            $this->line("Model exists: {$modelPath}");

            return;
        }

        $this->info("Creating model: {$modelFqn}");

        $namespace = substr($modelFqn, 0, strrpos($modelFqn, '\\'));
        $this->makeDirectory(dirname($modelPath));
        $this->writeFile($modelPath, $this->stub('platform-model.stub', [
            'namespace' => $namespace,
            'class' => class_basename($modelFqn),
        ]));

        $this->info("Created: {$modelPath}");
    }
}
