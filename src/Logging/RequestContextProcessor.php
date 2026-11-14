<?php

declare(strict_types=1);

namespace BlueprintPhp\Laravel\Logging;

use Illuminate\Support\Facades\Context;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Enriches every log record with request-scoped context (request id, user, ip)
 * seeded by the RequestContext middleware — the monolith analogue of v6's
 * ServiceNameProcessor.
 */
class RequestContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = [
            'user_id' => Context::get('user_id'),
            'request_id' => Context::getHidden('request_id'),
            'ip' => function_exists('request') ? optional(request())->ip() : null,
        ];

        return $record->with(extra: array_merge($record->extra, array_filter($context, fn ($v) => $v !== null)));
    }
}
