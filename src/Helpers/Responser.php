<?php

namespace BlueprintPhp\Laravel\Helpers;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Responser
{
    /**
     * Return a successful JSON response.
     */
    public static function success(int $status = Response::HTTP_OK, string $message = 'Success', mixed $data = [])
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return a failure JSON response, optionally logging the exception.
     */
    public static function fail(
        int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
        string $message = 'Error',
        mixed $data = [],
        Exception|Throwable|null $throwable = null
    ) {
        if ($throwable) {
            $trace = $throwable->getTraceAsString();
            $trace = Str::limit($trace, 10000, ' …[truncated]');

            Log::error("=============== [ERROR CATCH] ===============\n".($message ?: 'Service error').': '.print_r([
                'exception' => get_class($throwable),
                'code' => $throwable->getCode(),
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'trace' => $trace,
                'url' => request()->fullUrl() ?? null,
                'method' => request()->method() ?? null,
                'ip' => request()->ip() ?? null,
                'user_id' => optional(auth()->user())->id,
            ], true));
        }

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return a custom JSON response with any status code.
     */
    public static function custom(int $status, string $message, mixed $data = [])
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
