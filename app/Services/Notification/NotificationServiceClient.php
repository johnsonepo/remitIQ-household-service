<?php

declare(strict_types=1);

namespace App\Services\Notification;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class NotificationServiceClient
{
    /**
     * Send a notification event to the notification service.
     *
     * Notification failures are intentionally isolated from the
     * household service business operation.
     */
    public function send(NotificationEvent $event): void
    {
        $url = (string) config('services.notification.url');

        if ($url === '') {
            return;
        }

        $apiKey = (string) config('services.notification.api_key');
        $timeout = (int) config('services.notification.timeout', 5);

        try {
            $request = Http::timeout($timeout)
                ->acceptJson()
                ->asJson();

            if ($apiKey !== '') {
                $request = $request->withHeaders([
                    'X-Service-API-Key' => $apiKey,
                ]);
            }

            $request->post($url, [
                'eventId' => $event->eventId,
                'eventType' => $event->eventType,
                'userId' => $event->userId,
                'source' => $event->source,
                'timestamp' => $event->timestamp,
                'data' => $event->data,
            ]);
        } catch (ConnectionException) {
            // Notification delivery must not break the main operation.
        } catch (Throwable) {
            // Notification failures are intentionally isolated.
        }
    }
}
