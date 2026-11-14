<?php

namespace BlueprintPhp\Laravel\Tests;

use BlueprintPhp\Laravel\Helpers\Responser;
use BlueprintPhp\Laravel\Tests\Fixtures\ArraySettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class HelpersTest extends TestCase
{
    #[Test]
    public function get_settings_is_empty_without_a_repository(): void
    {
        $this->assertSame([], getSettings());
        $this->assertNull(getSettings('site_name'));
    }

    #[Test]
    public function get_settings_reads_the_configured_repository_once_the_table_exists(): void
    {
        config(['blueprint.settings.repository' => ArraySettings::class]);
        Schema::create('settings', fn ($t) => $t->id());
        Cache::flush();

        $this->assertSame('Blueprint', getSettings('site_name'));
    }

    #[Test]
    public function get_settings_does_not_cache_before_the_table_exists(): void
    {
        config(['blueprint.settings.repository' => ArraySettings::class]);
        Cache::flush();

        $this->assertSame([], getSettings());
        $this->assertFalse(Cache::has(config('blueprint.cache.website_settings_key')));
    }

    #[Test]
    public function responser_wraps_data_in_the_envelope(): void
    {
        $response = Responser::success(message: 'Done', data: ['id' => 1]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['status' => 200, 'message' => 'Done', 'data' => ['id' => 1]],
            $response->getData(true),
        );
    }

    #[Test]
    public function responser_fail_keeps_the_envelope(): void
    {
        $data = Responser::fail(message: 'Nope')->getData(true);

        $this->assertSame(422, $data['status']);
        $this->assertSame('Nope', $data['message']);
    }
}
