<?php

declare(strict_types=1);

namespace App\Services\Notification;

final readonly class NotificationEvent
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public string $eventId, public string $eventType, public string $userId, public string $source, public string $timestamp, public array $data) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eventId' => $this->eventId,
            'eventType' => $this->eventType,
            'userId' => $this->userId,
            'source' => $this->source,
            'timestamp' => $this->timestamp,
            'data' => $this->data,
        ];
    }
}
