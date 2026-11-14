<?php

namespace BlueprintPhp\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'make:platform-resource',
    description: 'Generate Abstract + per-platform Resource classes and register them in PlatformServiceProvider'
)]
class MakePlatformResource extends Command
{
    use ResolvesPlatformTarget;

    protected $signature = 'make:platform-resource
        {name : Resource name, e.g. Branch}
        {--actor=System : Actor folder (e.g., User|Admin|Merchant|...)}
        {--domain=Branch : Domain folder under Actor}
        {--api-version=1 : API version number}
        {--platform=web : Comma-separated: web,mobile (default: web)}
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
        $name = Str::studly($this->argument('name'));
        $actor = Str::studly($this->option('actor'));
        $domain = Str::studly($this->option('domain'));
        $version = (int) $this->option('api-version');

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

        // === 1) Abstract resource ===
        $abstractResourceNs = "{$baseNs}\\Http\\Resources\\V{$version}\\Abstracts\\{$actor}\\{$domain}";
        $abstractResourceDir = "{$basePath}/Http/Resources/V{$version}/Abstracts/{$actor}/{$domain}";
        $abstractResourceCls = "{$name}AbstractResource";
        $abstractResourcePath = "{$abstractResourceDir}/{$abstractResourceCls}.php";

        $this->makeDirectory($abstractResourceDir);
        $this->writeFile($abstractResourcePath, $this->renderAbstractResource($abstractResourceNs, $abstractResourceCls));
        $this->info("Created: {$abstractResourcePath}");

        // === 2) Per-platform resources ===
        $concreteResources = [];
        foreach ($platforms as $platform) {
            $nsPlatform = $platform === 'mobile' ? 'Mobile' : 'Web';
            $platformEnumCase = strtoupper($platform) === 'MOBILE' ? 'MOBILE' : 'WEB';

            $resourceNs = "{$baseNs}\\Http\\Resources\\V{$version}\\{$nsPlatform}\\{$actor}\\{$domain}";
            $resourceDir = "{$basePath}/Http/Resources/V{$version}/{$nsPlatform}/{$actor}/{$domain}";
            $resourceCls = "{$name}Resource";
            $resourcePath = "{$resourceDir}/{$resourceCls}.php";

            $this->makeDirectory($resourceDir);
            $this->writeFile($resourcePath, $this->renderPlatformResource(
                $resourceNs,
                $resourceCls,
                $abstractResourceNs,
                $abstractResourceCls,
                $platformEnumCase,
                "V{$version}"
            ));
            $this->info("Created: {$resourcePath}");
            $concreteResources[] = "\\{$resourceNs}\\{$resourceCls}::class";
        }

        // === 3) Register in PlatformServiceProvider ===
        $abstractResourceFqn = "\\{$abstractResourceNs}\\{$abstractResourceCls}::class";
        $this->registerResourceInPlatformServiceProvider($target, $abstractResourceFqn, $concreteResources);

        $this->info('All set ✅');

        return self::SUCCESS;
    }

    // ===== Renderers =====

    private function renderAbstractResource(string $namespace, string $class): string
    {
        return $this->stub('platform-resource.abstract.stub', [
            'namespace' => $namespace,
            'class' => $class,
        ]);
    }

    private function renderPlatformResource(
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
}
