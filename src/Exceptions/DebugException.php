<?php

declare(strict_types=1);

namespace BlueprintPhp\Laravel\Exceptions;

use Illuminate\Support\Facades\Context;
use RuntimeException;

/**
 * Thrown by the dbg() helper to dump a value + caller trace + request context
 * as a JSON response (handled by the API exception renderer). Debugging aid.
 */
class DebugException extends RuntimeException
{
    private mixed $dump;

    private array $callerTrace;

    public function __construct(mixed ...$args)
    {
        $this->dump = count($args) > 1 ? $args : ($args[0] ?? null);
        $this->callerTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        parent::__construct('DebugException');
    }

    public function toArray(): array
    {
        return [
            'debug' => [
                'file' => $this->callerTrace[2]['file'] ?? null,
                'line' => $this->callerTrace[2]['line'] ?? null,
                'function' => $this->callerTrace[2]['function'] ?? null,
                'class' => $this->callerTrace[2]['class'] ?? null,
                'context' => class_exists(Context::class) ? Context::all() : [],
                'dump' => $this->dump,
            ],
        ];
    }
}
