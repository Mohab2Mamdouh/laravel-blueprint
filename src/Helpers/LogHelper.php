<?php

namespace BlueprintPhp\Laravel\Helpers;

use Illuminate\Support\Facades\Log;
use Throwable;

class LogHelper
{
    // -------------------------------------------------------------------------
    // Severity-level methods
    // -------------------------------------------------------------------------

    public static function info(string $event, array $context = []): void
    {
        Log::info(static::formatMessage($event), static::buildContext($context));
    }

    public static function warning(string $event, array $context = []): void
    {
        Log::warning(static::formatMessage($event), static::buildContext($context));
    }

    public static function error(string $event, array $context = []): void
    {
        Log::error(static::formatMessage($event), static::buildContext($context));
    }

    public static function debug(string $event, array $context = []): void
    {
        Log::debug(static::formatMessage($event), static::buildContext($context));
    }

    // -------------------------------------------------------------------------
    // Exception-specific shorthand
    // -------------------------------------------------------------------------

    /**
     * Log a caught exception with full context in one call.
     *
     * Usage:
     *   LogHelper::exception('PaymentService: charge failed', $e);
     *   LogHelper::exception('PaymentService: charge failed', $e, ['order_id' => $id]);
     */
    public static function exception(string $event, Throwable $e, array $extra = []): void
    {
        Log::error(static::formatMessage($event), static::buildContext(
            array_merge(static::throwableContext($e), $extra)
        ));
    }

    // -------------------------------------------------------------------------
    // Formatting internals
    // -------------------------------------------------------------------------

    /**
     * Wrap the event label in a consistent header so log lines are easy to grep.
     *
     * Output: "=== [PaymentService: charge failed] ==="
     */
    private static function formatMessage(string $event): string
    {
        return "=== [{$event}] ===";
    }

    /**
     * Merge caller-supplied context with automatic request metadata.
     *
     * The request fields are appended last so they never shadow caller data.
     */
    private static function buildContext(array $context = []): array
    {
        return array_merge($context, static::requestContext());
    }

    /**
     * Automatically captured HTTP request metadata.
     * All fields are nullable — safe to call from CLI / queue contexts.
     */
    private static function requestContext(): array
    {
        $request = request();

        return [
            'url' => $request?->fullUrl(),
            'method' => $request?->method(),
            'ip' => $request?->ip(),
            'user_id' => optional(auth()->user())->id,
        ];
    }

    /**
     * Extract the standard fields from a Throwable for log context.
     */
    private static function throwableContext(Throwable $e): array
    {
        return [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => static::truncateTrace($e->getTraceAsString()),
        ];
    }

    /**
     * Trim the trace to a safe length so it never bloats your log storage.
     * Limit is configurable via `blueprint.logging.max_trace_length`.
     */
    private static function truncateTrace(string $trace): string
    {
        $limit = (int) config('blueprint.logging.max_trace_length', 5000);

        return strlen($trace) > $limit
            ? substr($trace, 0, $limit).' …[truncated]'
            : $trace;
    }
}
