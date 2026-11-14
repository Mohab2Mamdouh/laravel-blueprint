<?php

declare(strict_types=1);

namespace BlueprintPhp\Laravel\Logging;

use Illuminate\Log\Logger;

/**
 * Log channel "tap": pushes the RequestContextProcessor onto every handler.
 * Enable per channel in config/logging.php via 'tap' => [TapRequestContext::class].
 */
class TapRequestContext
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getLogger()->getHandlers() as $handler) {
            $handler->pushProcessor(new RequestContextProcessor);
        }
    }
}
