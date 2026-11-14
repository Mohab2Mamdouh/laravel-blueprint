<?php

namespace BlueprintPhp\Laravel\Tests;

use BlueprintPhp\Laravel\BlueprintServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_merges_the_package_config(): void
    {
        $this->assertSame(['en', 'ar'], config('blueprint.supported_locales'));
        $this->assertNull(config('blueprint.settings.repository'));
    }

    public static function commands(): array
    {
        return array_map(fn ($c) => [$c], [
            'make:platform-service', 'make:platform-request', 'make:platform-resource',
            'make:repo', 'make:action', 'make:seeder', 'module:add-repository',
            'seed', 'seed:rollback', 'seed:reset', 'seed:status',
        ]);
    }

    #[Test]
    #[DataProvider('commands')]
    public function it_registers_the_commands(string $command): void
    {
        $this->assertArrayHasKey($command, Artisan::all());
    }

    #[Test]
    public function it_registers_the_migration_macros(): void
    {
        foreach (['audit', 'fullAudit', 'softDeletesWithUser', 'status', 'slug', 'meta', 'price', 'ordering', 'publishedAt'] as $macro) {
            $this->assertTrue(Blueprint::hasMacro($macro), "Blueprint::{$macro} missing");
        }
    }

    #[Test]
    public function it_registers_the_query_macros(): void
    {
        foreach (['active', 'inactive', 'search', 'ordered', 'published', 'scheduled', 'byKeywords', 'empty'] as $macro) {
            $this->assertTrue(Builder::hasGlobalMacro($macro), "Builder::{$macro} missing");
        }
    }

    #[Test]
    public function it_loads_namespaced_translations(): void
    {
        $this->assertNotSame('blueprint::public.dashboard', __('blueprint::public.dashboard'));
    }

    #[Test]
    public function stub_path_falls_back_to_the_package_stub(): void
    {
        $path = BlueprintServiceProvider::stubPath('model.stub');

        $this->assertFileExists($path);
        $this->assertStringEndsWith('/stubs/model.stub', $path);
    }

    #[Test]
    public function stub_path_prefers_a_published_stub(): void
    {
        $dir = base_path('stubs/blueprint');
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/model.stub', 'custom');

        try {
            $this->assertSame($dir.'/model.stub', BlueprintServiceProvider::stubPath('model.stub'));
        } finally {
            @unlink($dir.'/model.stub');
            @rmdir($dir);
        }
    }
}
