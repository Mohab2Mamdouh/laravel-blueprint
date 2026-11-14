<?php

namespace BlueprintPhp\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'make:platform-service',
    description: 'Generate Model, Repository (interface + eloquent), Abstract + per-platform Services, Controllers, Request, Resource, and register providers'
)]
class MakePlatformService extends Command
{
    use ResolvesPlatformTarget;

    protected $signature = 'make:platform-service
        {name : Base name, e.g. Role}
        {--actor=System : Actor folder (e.g., User|Admin|Merchant|...)}
        {--domain=Role : Domain folder under Actor}
        {--api-version=1 : API version number}
        {--platform=web : Comma-separated: web,mobile (default: web)}
        {--tenant : Generate model and repository for tenant (Models/Tenant, Repository/Contracts/Tenant, Repository/Eloquent/Tenant)}
        {--crud : Generate full CRUD methods in the abstract service and controller}
        {--no-controller : Skip generating controllers}
        {--no-request : Skip generating request}
        {--no-resource : Skip generating resource}
        {--no-model : Skip generating model}
        {--no-repo : Skip generating repository (interface + eloquent + provider bind)}
        {--module= : target module (Modules/<M>/app); omit for app/}
    ';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $name = Str::studly($this->argument('name')); // e.g. Role
        $actor = Str::studly($this->option('actor'));  // e.g. User
        $domain = Str::studly($this->option('domain')); // e.g. Role
        $version = (int) $this->option('api-version');
        $versionEnumCase = "V{$version}"; // Version enum case, e.g. V1
        $isTenant = (bool) $this->option('tenant');
        $makeCrud = (bool) $this->option('crud');
        $makeCtrl = ! $this->option('no-controller');
        $makeReq = ! $this->option('no-request');
        $makeRes = ! $this->option('no-resource');
        $makeModel = ! $this->option('no-model');
        $makeRepo = ! $this->option('no-repo');

        $target = $this->resolveTarget($this->option('module'));
        if ($target === null) {
            return self::FAILURE;
        }

        $baseNs = $target['baseNs'];
        $basePath = $target['basePath'];

        $platformOpt = $this->option('platform') ?: 'web';
        $platforms = collect(explode(',', (string) $platformOpt))
            ->map(fn ($p) => strtolower(trim($p)))
            ->filter()
            ->unique()
            ->values();

        if ($platforms->isEmpty()) {
            $this->error('Please provide at least one platform (web or mobile).');

            return self::FAILURE;
        }

        // === 0) Model ===
        if ($makeModel) {
            $modelNs = $isTenant ? "{$baseNs}\\Models\\Tenant" : "{$baseNs}\\Models";
            $modelDir = $isTenant ? "{$basePath}/Models/Tenant" : "{$basePath}/Models";
            $modelCls = $name;
            $modelPath = "{$modelDir}/{$modelCls}.php";

            $this->makeDirectory($modelDir);
            if (! $this->files->exists($modelPath)) {
                $this->writeFile($modelPath, $this->renderModel($modelNs, $modelCls));
                $this->info("Created: {$modelPath}");
            } else {
                $this->line("Model exists: {$modelPath}");
            }
        }

        $abstractRequestNs = $makeReq
            ? "{$baseNs}\\Http\\Requests\\V{$version}\\Abstracts\\{$actor}\\{$domain}"
            : '';

        $abstractResourceNs = $makeRes
            ? "{$baseNs}\\Http\\Resources\\V{$version}\\Abstracts\\{$actor}\\{$domain}"
            : '';

        // === 1) Abstract service ===
        $abstractNs = "{$baseNs}\\Http\\Services\\V{$version}\\Abstracts\\{$actor}\\{$domain}";
        $abstractDir = "{$basePath}/Http/Services/V{$version}/Abstracts/{$actor}/{$domain}";
        $abstractCls = "{$name}AbstractService";
        $abstractPath = "{$abstractDir}/{$abstractCls}.php";

        $this->makeDirectory($abstractDir);
        $this->writeFile($abstractPath, $this->renderAbstract(
            $abstractNs,
            $abstractCls,
            $name,
            $abstractResourceNs,
            $abstractRequestNs,
            $isTenant ? "{$baseNs}\\Repository\\Contracts\\Tenant" : "{$baseNs}\\Repository\\Contracts",
            $makeCrud,
        ));
        $this->info("Created: {$abstractPath}");

        // === 2) Abstract request ===
        if ($makeReq) {
            $abstractRequestDir = "{$basePath}/Http/Requests/V{$version}/Abstracts/{$actor}/{$domain}";
            $abstractRequestCls = "{$name}AbstractRequest";
            $abstractRequestPath = "{$abstractRequestDir}/{$abstractRequestCls}.php";

            $this->makeDirectory($abstractRequestDir);
            $this->writeFile($abstractRequestPath, $this->renderAbstractRequest($abstractRequestNs, $abstractRequestCls));
            $this->info("Created: {$abstractRequestPath}");
        }

        // === 3) Single Controller (not platform-specific) ===
        if ($makeCtrl) {
            $controllerNs = "{$baseNs}\\Http\\Controllers\\V{$version}\\{$actor}\\{$domain}";
            $controllerDir = "{$basePath}/Http/Controllers/V{$version}/{$actor}/{$domain}";
            $controllerCls = "{$name}Controller";
            $controllerPath = "{$controllerDir}/{$controllerCls}.php";

            $abstractServiceVar = lcfirst($abstractCls);

            $this->makeDirectory($controllerDir);
            $this->writeFile($controllerPath, $this->renderController(
                $controllerNs,
                $controllerCls,
                $abstractNs,
                $abstractCls,
                $abstractServiceVar,
                $abstractRequestNs,
                $name,
                $makeCrud,
            ));
            $this->info("Created: {$controllerPath}");
        }

        // === 3) Abstract resource ===
        $abstractResourceCls = '';
        $concreteResources = [];
        if ($makeRes) {
            $abstractResourceDir = "{$basePath}/Http/Resources/V{$version}/Abstracts/{$actor}/{$domain}";
            $abstractResourceCls = "{$name}AbstractResource";
            $abstractResourcePath = "{$abstractResourceDir}/{$abstractResourceCls}.php";

            $this->makeDirectory($abstractResourceDir);
            $this->writeFile($abstractResourcePath, $this->renderAbstractResource($abstractResourceNs, $abstractResourceCls));
            $this->info("Created: {$abstractResourcePath}");
        }

        // === 4) Per-platform services (+ request / resource) ===
        $concretes = [];
        $concreteRequests = [];
        foreach ($platforms as $platform) {
            $nsPlatform = $platform === 'mobile' ? 'Mobile' : 'Web';
            $platformEnumCase = strtoupper($platform) === 'MOBILE' ? 'MOBILE' : 'WEB';

            // Service
            $serviceNs = "{$baseNs}\\Http\\Services\\V{$version}\\{$nsPlatform}\\{$actor}\\{$domain}";
            $serviceDir = "{$basePath}/Http/Services/V{$version}/{$nsPlatform}/{$actor}/{$domain}";
            $serviceCls = "{$name}Service";
            $servicePath = "{$serviceDir}/{$serviceCls}.php";

            $this->makeDirectory($serviceDir);
            $this->writeFile($servicePath, $this->renderConcrete(
                $serviceNs,
                $serviceCls,
                $abstractNs,
                $abstractCls,
                $platformEnumCase,
                $versionEnumCase
            ));
            $this->info("Created: {$servicePath}");
            $concretes[] = "\\{$serviceNs}\\{$serviceCls}::class";

            // Request (platform-specific)
            if ($makeReq) {
                $requestNs = "{$baseNs}\\Http\\Requests\\V{$version}\\{$nsPlatform}\\{$actor}\\{$domain}";
                $requestDir = "{$basePath}/Http/Requests/V{$version}/{$nsPlatform}/{$actor}/{$domain}";
                $requestCls = "{$name}Request";
                $requestPath = "{$requestDir}/{$requestCls}.php";

                $abstractRequestNs = "{$baseNs}\\Http\\Requests\\V{$version}\\Abstracts\\{$actor}\\{$domain}";
                $abstractRequestCls = "{$name}AbstractRequest";

                $this->makeDirectory($requestDir);
                $this->writeFile($requestPath, $this->renderPlatformRequest(
                    $requestNs,
                    $requestCls,
                    $abstractRequestNs,
                    $abstractRequestCls,
                    $platformEnumCase,
                    $versionEnumCase
                ));
                $this->info("Created: {$requestPath}");
                $concreteRequests[] = "\\{$requestNs}\\{$requestCls}::class";
            }

            // Resource
            if ($makeRes) {
                $resourceNs = "{$baseNs}\\Http\\Resources\\V{$version}\\{$nsPlatform}\\{$actor}\\{$domain}";
                $resourceDir = "{$basePath}/Http/Resources/V{$version}/{$nsPlatform}/{$actor}/{$domain}";
                $resourceCls = "{$name}Resource";
                $resourcePath = "{$resourceDir}/{$resourceCls}.php";

                $this->makeDirectory($resourceDir);
                $this->writeFile($resourcePath, $this->renderResource(
                    $resourceNs,
                    $resourceCls,
                    $abstractResourceNs,
                    $abstractResourceCls,
                    $platformEnumCase,
                    $versionEnumCase
                ));
                $this->info("Created: {$resourcePath}");
                $concreteResources[] = "\\{$resourceNs}\\{$resourceCls}::class";
            }
        }

        // === 3) Repository ===
        if ($makeRepo) {
            // Interface
            $repoInterfaceNs = $isTenant ? "{$baseNs}\\Repository\\Contracts\\Tenant" : "{$baseNs}\\Repository\\Contracts";
            $repoInterfaceDir = $isTenant ? "{$basePath}/Repository/Contracts/Tenant" : "{$basePath}/Repository/Contracts";
            $repoInterfaceCls = "{$name}RepositoryInterface";
            $repoInterfacePath = "{$repoInterfaceDir}/{$repoInterfaceCls}.php";

            $this->makeDirectory($repoInterfaceDir);
            if (! $this->files->exists($repoInterfacePath)) {
                $this->writeFile($repoInterfacePath, $this->renderRepoInterface($repoInterfaceNs, $repoInterfaceCls));
                $this->info("Created: {$repoInterfacePath}");
            }

            // Eloquent implementation
            $repoEloquentNs = $isTenant ? "{$baseNs}\\Repository\\Eloquent\\Tenant" : "{$baseNs}\\Repository\\Eloquent";
            $repoEloquentDir = $isTenant ? "{$basePath}/Repository/Eloquent/Tenant" : "{$basePath}/Repository/Eloquent";
            $repoEloquentCls = "{$name}Repository";
            $repoEloquentPath = "{$repoEloquentDir}/{$repoEloquentCls}.php";

            // Determine model FQN based on tenant flag
            $modelFqn = $isTenant ? "{$baseNs}\\Models\\Tenant\\{$name}" : "{$baseNs}\\Models\\{$name}";

            $this->makeDirectory($repoEloquentDir);
            $this->writeFile($repoEloquentPath, $this->renderRepoConcrete(
                $repoEloquentNs,
                $repoEloquentCls,
                $modelFqn,
                $repoInterfaceNs,
                $repoInterfaceCls
            ));
            $this->info("Created: {$repoEloquentPath}");

            // Bind in RepositoryServiceProvider (with clean imports)
            $this->registerInRepositoryServiceProvider(
                $target,
                "{$repoInterfaceNs}\\{$repoInterfaceCls}",
                "{$repoEloquentNs}\\{$repoEloquentCls}"
            );
        }

        // === 4) Register in PlatformServiceProvider ===
        $abstractFqn = "\\{$abstractNs}\\{$abstractCls}::class";
        $this->registerServiceInPlatformServiceProvider($target, $abstractFqn, $concretes);

        if ($makeReq && ! empty($concreteRequests)) {
            $abstractRequestFqn = "\\{$abstractRequestNs}\\{$abstractRequestCls}::class";
            $this->registerRequestInPlatformServiceProvider($target, $abstractRequestFqn, $concreteRequests);
        }

        if ($makeRes && ! empty($concreteResources)) {
            $abstractResourceFqn = "\\{$abstractResourceNs}\\{$abstractResourceCls}::class";
            $this->registerResourceInPlatformServiceProvider($target, $abstractResourceFqn, $concreteResources);
        }

        $this->info('All set ✅');

        return self::SUCCESS;
    }

    // ===== Renderers =====

    private function renderModel(string $namespace, string $class): string
    {
        return $this->stub('platform-model.stub', [
            'namespace' => $namespace,
            'class' => $class,
        ]);
    }

    private function renderAbstract(string $namespace, string $class, string $name, string $resourceAbstractNs, string $requestAbstractNs, string $repoInterfaceNs, bool $makeCrud = false): string
    {
        if (! $makeCrud) {
            return $this->stub('platform-service.abstract.stub', [
                'namespace' => $namespace,
                'class' => $class,
            ]);
        }

        $extraImports = '';
        if ($requestAbstractNs) {
            $extraImports .= "use {$requestAbstractNs}\\{$name}AbstractRequest;\n";
        }
        if ($resourceAbstractNs) {
            $extraImports .= "use {$resourceAbstractNs}\\{$name}AbstractResource;\n";
        }

        return $this->stub('platform-service.abstract.crud.stub', [
            'namespace' => $namespace,
            'class' => $class,
            'name' => lcfirst($name),
            'name_class' => $name,
            'name_plural' => Str::camel(Str::plural($name)),
            'resource_abstract_namespace' => $resourceAbstractNs,
            'request_abstract_namespace' => $requestAbstractNs,
            'repo_interface_namespace' => $repoInterfaceNs,
            'extra_imports' => rtrim($extraImports),
        ]);
    }

    private function renderConcrete(
        string $namespace,
        string $class,
        string $abstractNamespace,
        string $abstractClass,
        string $platformEnumCase,
        string $versionEnumCase
    ): string {
        return $this->stub('platform-service.platform.stub', [
            'namespace' => $namespace,
            'class' => $class,
            'abstract_namespace' => $abstractNamespace,
            'abstract_class' => $abstractClass,
            'platform_enum_case' => $platformEnumCase,
            'version_enum_case' => $versionEnumCase,
        ]);
    }

    private function renderController(
        string $namespace,
        string $class,
        string $abstractServiceNs,
        string $abstractServiceClass,
        string $serviceVar,
        string $requestAbstractNs,
        string $nameClass,
        bool $makeCrud = false,
    ): string {
        if (! $makeCrud) {
            return $this->stub('platform-controller.stub', [
                'namespace' => $namespace,
                'class' => $class,
                'abstract_service_namespace' => $abstractServiceNs,
                'abstract_service_class' => $abstractServiceClass,
                'abstract_service_var' => $serviceVar,
            ]);
        }

        $extraImports = '';
        if ($requestAbstractNs) {
            $extraImports .= "use {$requestAbstractNs}\\{$nameClass}AbstractRequest;\n";
        }

        return $this->stub('platform-controller.crud.stub', [
            'namespace' => $namespace,
            'class' => $class,
            'abstract_service_namespace' => $abstractServiceNs,
            'abstract_service_class' => $abstractServiceClass,
            'abstract_service_var' => $serviceVar,
            'request_abstract_class' => $requestAbstractNs ? "{$nameClass}AbstractRequest" : 'Request',
            'name_class' => $nameClass,
            'extra_imports' => rtrim($extraImports),
        ]);
    }

    private function renderAbstractRequest(string $namespace, string $class): string
    {
        return $this->stub('platform-request.abstract.stub', [
            'namespace' => $namespace,
            'class' => $class,
        ]);
    }

    private function renderPlatformRequest(
        string $namespace,
        string $class,
        string $abstractNamespace,
        string $abstractClass,
        string $platformEnumCase,
        string $versionEnumCase
    ): string {
        return $this->stub('platform-request.platform.stub', [
            'namespace' => $namespace,
            'class' => $class,
            'abstract_namespace' => $abstractNamespace,
            'abstract_class' => $abstractClass,
            'platform_enum_case' => $platformEnumCase,
            'version_enum_case' => $versionEnumCase,
        ]);
    }

    private function renderAbstractResource(string $namespace, string $class): string
    {
        return $this->stub('platform-resource.abstract.stub', [
            'namespace' => $namespace,
            'class' => $class,
        ]);
    }

    private function renderResource(
        string $namespace,
        string $class,
        string $abstractNamespace,
        string $abstractClass,
        string $platformEnumCase,
        string $versionEnumCase
    ): string {
        return $this->stub('platform-resource.platform.stub', [
            'namespace' => $namespace,
            'class' => $class,
            'abstract_namespace' => $abstractNamespace,
            'abstract_class' => $abstractClass,
            'platform_enum_case' => $platformEnumCase,
            'version_enum_case' => $versionEnumCase,
        ]);
    }

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
        string $modelFqn,
        string $interfaceNs,
        string $interfaceCls
    ): string {
        return $this->stub('platform-repository.eloquent.stub', [
            'namespace' => $namespace,
            'class' => $class,
            'model_fqn' => ltrim($modelFqn, '\\'),
            'model_class' => class_basename($modelFqn),
            'interface_namespace' => $interfaceNs,
            'interface_class' => $interfaceCls,
        ]);
    }
}
