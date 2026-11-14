<?php

namespace BlueprintPhp\Laravel\Tests;

use BlueprintPhp\Laravel\Enums\Platform;
use PHPUnit\Framework\Attributes\Test;

class EnumableTest extends TestCase
{
    #[Test]
    public function it_lists_values_and_resolves_by_name(): void
    {
        $this->assertSame(['web', 'mobile'], Platform::values());
        $this->assertSame(Platform::MOBILE, Platform::fromName('MOBILE'));
    }
}
