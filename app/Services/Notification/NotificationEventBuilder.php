<?php

declare(strict_types=1);

namespace App\Services\Notification;

use Illuminate\Support\Str;

final class NotificationEventBuilder
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function build(string $eventType, string $userId, array $data = []): NotificationEvent
    {
        return new NotificationEvent(eventId: (string) Str::uuid(), eventType: $eventType, userId: $userId, source: 'household-service', timestamp: now()->toISOString(), data: $data);
    }
}
