<?php

namespace BlueprintPhp\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'make:platform-request',
    description: 'Generate Abstract + per-platform Request classes and register them in PlatformServiceProvider'
)]
class MakePlatformRequest extends Command
{
    use ResolvesPlatformTarget;

    protected $signature = 'make:platform-request
        {name : Request name, e.g. SignIn}
        {--actor=System : Actor folder (e.g., User|Admin|Merchant|...)}
        {--domain=Auth : Domain folder under Actor}
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

        // === 1) Abstract request ===
        $abstractRequestNs = "{$baseNs}\\Http\\Requests\\V{$version}\\Abstracts\\{$actor}\\{$domain}";
        $abstractRequestDir = "{$basePath}/Http/Requests/V{$version}/Abstracts/{$actor}/{$domain}";
        $abstractRequestCls = "{$name}AbstractRequest";
        $abstractRequestPath = "{$abstractRequestDir}/{$abstractRequestCls}.php";

        $this->makeDirectory($abstractRequestDir);
        $this->writeFile($abstractRequestPath, $this->renderAbstractRequest($abstractRequestNs, $abstractRequestCls));
        $this->info("Created: {$abstractRequestPath}");

        // === 2) Per-platform requests ===
        $concreteRequests = [];
        foreach ($platforms as $platform) {
            $nsPlatform = $platform === 'mobile' ? 'Mobile' : 'Web';
            $platformEnumCase = strtoupper($platform) === 'MOBILE' ? 'MOBILE' : 'WEB';

            $requestNs = "{$baseNs}\\Http\\Requests\\V{$version}\\{$nsPlatform}\\{$actor}\\{$domain}";
            $requestDir = "{$basePath}/Http/Requests/V{$version}/{$nsPlatform}/{$actor}/{$domain}";
            $requestCls = "{$name}Request";
            $requestPath = "{$requestDir}/{$requestCls}.php";

            $this->makeDirectory($requestDir);
            $this->writeFile($requestPath, $this->renderPlatformRequest(
                $requestNs,
                $requestCls,
                $abstractRequestNs,
                $abstractRequestCls,
                $platformEnumCase,
                "V{$version}"
            ));
            $this->info("Created: {$requestPath}");
            $concreteRequests[] = "\\{$requestNs}\\{$requestCls}::class";
        }

        // === 3) Register in PlatformServiceProvider ===
        $abstractRequestFqn = "\\{$abstractRequestNs}\\{$abstractRequestCls}::class";
        $this->registerRequestInPlatformServiceProvider($target, $abstractRequestFqn, $concreteRequests);

        $this->info('All set ✅');

        return self::SUCCESS;
    }

    // ===== Renderers =====

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
}
