<?php

namespace Mohab\LaravelBlueprint\Helpers;

use Illuminate\Http\JsonResponse;

use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Mohab\LaravelBlueprint\Helpers\LogHelper;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Symfony\Component\HttpFoundation\Response;

class FcmHelper
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Send an FCM notification to one or multiple device tokens.
     *
     * @param  string|string[] $tokens       One token or an array of tokens
     * @param  string          $title        Notification title
     * @param  string          $body         Notification body
     * @param  array           $data         Arbitrary key→value payload (all values cast to string)
     * @param  string|null     $image        Optional image URL
     * @return bool                          True when at least one message was delivered
     */
    public static function send(
        string|array $tokens,
        string       $title,
        string       $body,
        array        $data  = [],
        ?string      $image = null,
    ): bool {
        $tokens = static::normalizeTokens($tokens);

        if (empty($tokens)) {
            LogHelper::warning('FcmHelper: No valid FCM tokens provided.');
            return false;
        }

        try {
            $report = static::messaging()->sendMulticast(
                static::buildMessage($title, $body, $data, $image),
                $tokens,
            );

            static::logReport($report, $tokens);

            return $report->successes()->count() > 0;

        } catch (MessagingException $e) {
            LogHelper::error('FcmHelper: Messaging error — ' . $e->getMessage(), ['exception' => $e]);
            return false;
        } catch (FirebaseException $e) {
            LogHelper::error('FcmHelper: Firebase error — ' . $e->getMessage(), ['exception' => $e]);
            return false;
        } catch (\Throwable $e) {
            LogHelper::error('FcmHelper: Unexpected error — ' . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * Return a Responser::fail() response when FCM credentials are missing,
     * or null when everything looks healthy.
     *
     * Usage in a controller:
     *   if ($guard = FcmHelper::guard()) return $guard;
     *
     * @param  string $message  Custom failure message for the API response
     * @param  int    $status   HTTP status code for the failure response
     */
    public static function guard(
        string $message = 'Push notification service is currently unavailable.',
        int    $status  = Response::HTTP_SERVICE_UNAVAILABLE,
    ): ?JsonResponse {
        if (static::credentialsExist()) {
            return null;
        }

        return Responser::fail(status: $status, message: $message);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Resolve the Firebase credentials file path from config/env.
     *
     * Set FIREBASE_CREDENTIALS in your .env to a path relative to storage/:
     *   FIREBASE_CREDENTIALS=firebase/my-project-adminsdk.json
     *
     * Or publish the package config and set:
     *   'blueprint.firebase.credentials' => 'firebase/my-project-adminsdk.json'
     */
    private static function credentialsPath(): string
    {
        return storage_path(
            config('blueprint.firebase.credentials', env('FIREBASE_CREDENTIALS', 'firebase/credentials.json'))
        );
    }

    private static function credentialsExist(): bool
    {
        $path = static::credentialsPath();

        if (!file_exists($path)) {
            LogHelper::warning('FcmHelper: Credentials file not found.', ['path' => $path]);
            return false;
        }

        return true;
    }

    private static function messaging(): \Kreait\Firebase\Contract\Messaging
    {
        return (new Factory)
            ->withServiceAccount(static::credentialsPath())
            ->createMessaging();
    }

    /**
     * Build the platform-aware CloudMessage.
     *
     * @param  array<string, mixed> $data  Arbitrary payload — values are cast to string
     *                                     as FCM only accepts string data pairs.
     */
    private static function buildMessage(
        string  $title,
        string  $body,
        array   $data  = [],
        ?string $image = null,
    ): CloudMessage {
        // FCM data values must all be strings.
        $stringData = array_map('strval', $data);

        $notification = array_filter([
            'title' => $title,
            'body'  => $body,
            'image' => $image,
        ]);

        return CloudMessage::new()
            ->withNotification($notification)
            ->withData($stringData)
            ->withAndroidConfig([
                'priority'     => 'high',
                'notification' => [
                    'sound'      => 'default',
                    'channel_id' => config('blueprint.firebase.android_channel', 'default_channel_id'),
                ],
            ])
            ->withApnsConfig([
                'headers' => ['apns-priority' => '10'],
                'payload' => [
                        'aps' => array_filter([
                            'alert' => array_filter([
                                'title' => $title,
                                'body'  => $body,
                                'image' => $image,
                            ]),
                            'sound'             => 'default',
                            'content-available' => 1,
                            'mutable-content'   => 1,
                        ]),
                    ] + $stringData,
            ]);
    }

    /**
     * Filter out blank/null tokens so we never send to an empty string.
     *
     * @param  string|string[] $tokens
     * @return string[]
     */
    private static function normalizeTokens(string|array $tokens): array
    {
        return array_values(array_filter(
            is_array($tokens) ? $tokens : [$tokens],
            static fn ($t) => is_string($t) && $t !== '',
        ));
    }

    private static function logReport(MulticastSendReport $report, array $tokens): void
    {
        LogHelper::info('FcmHelper: Multicast sent.', [
            'total'    => count($tokens),
            'success'  => $report->successes()->count(),
            'failures' => $report->failures()->count(),
        ]);

        foreach ($report->failures()->getItems() as $failure) {
            LogHelper::error('FcmHelper: Token delivery failed.', [
                'token' => $failure->target()->value(),
                'error' => $failure->error()->getMessage(),
            ]);
        }
    }
}