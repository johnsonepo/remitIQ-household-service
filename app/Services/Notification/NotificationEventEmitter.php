<?php

declare(strict_types=1);

namespace App\Services\Notification;

final class NotificationEventEmitter
{
    public function __construct(private readonly NotificationServiceClient $client) {}

    public function emit(NotificationEvent $event): void
    {
        $this->client->send($event);
    }
}
