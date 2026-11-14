<?php

namespace BlueprintPhp\Laravel\Helpers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MailHelper
{
    /**
     * Check whether the configured SMTP server is reachable.
     *
     * Returns true if a socket connection can be established, false otherwise.
     * All failures are logged as warnings — nothing is thrown.
     */
    public static function canConnect(): bool
    {
        $host = config('mail.mailers.smtp.host');
        $port = (int) config('mail.mailers.smtp.port');
        $timeout = (int) config('mail.mailers.smtp.timeout', 5);

        if (empty($host) || empty($port)) {
            LogHelper::warning('MailHelper: SMTP host or port is not configured.');

            return false;
        }

        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (! $connection) {
            LogHelper::warning("MailHelper: Cannot reach SMTP server at {$host}:{$port} — [{$errno}] {$errstr}");

            return false;
        }

        fclose($connection);

        return true;
    }

    /**
     * Return a Responser::fail() response when the mail server is unreachable,
     * or null when the connection is healthy.
     *
     * Intended for use at the top of controller actions that send mail:
     *
     *   if ($guard = MailHelper::guard()) return $guard;
     *
     * @param  string  $message  Custom failure message shown in the API response
     * @param  int  $status  HTTP status code for the failure response
     */
    public static function guard(
        string $message = 'Mail service is currently unavailable. Please try again later.',
        int $status = Response::HTTP_SERVICE_UNAVAILABLE,
    ): ?JsonResponse {
        if (static::canConnect()) {
            return null;
        }

        return Responser::fail(status: $status, message: $message);
    }
}
