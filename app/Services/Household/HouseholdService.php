<?php

declare(strict_types=1);

namespace App\Services\Household;

use App\Models\Household;
use App\Repositories\HouseholdRepository;
use App\Services\Notification\NotificationEventBuilder;
use App\Services\Notification\NotificationEventEmitter;
use Illuminate\Database\Eloquent\Collection;

final class HouseholdService
{
    public function __construct(private readonly HouseholdRepository $repository, private readonly NotificationEventBuilder $notificationEventBuilder, private readonly NotificationEventEmitter $notificationEventEmitter) {}

    /**
     * Get all households accessible by the user.
     *
     * @return Collection<int, Household>
     */
    public function forUser(int $userId): Collection
    {
        return $this->repository->forUser($userId);
    }

    /**
     * Determine whether a user can access a household.
     */
    public function canAccess(int $userId, string $householdId): bool
    {
        return $this->repository->isAccessibleByUser($householdId, $userId);
    }

    /**
     * Create a household owned by the authenticated user.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(int $userId, array $data): Household
    {
        $data['owner_id'] = $userId;

        /** @var Household $household */
        $household = $this->repository->create($data);

        $event = $this->notificationEventBuilder->build(eventType: 'HOUSEHOLD_CREATED', userId: (string) $userId, data: [
            'householdId' => $household->id,
            'ownerId' => $userId,
            'name' => $household->name,
        ], );

        $this->notificationEventEmitter->emit($event);

        return $household;
    }

    /**
     * Update a household.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Household $household, array $data): Household
    {
        $household->update($data);

        $household = $household->refresh();

        $event = $this->notificationEventBuilder->build(eventType: 'HOUSEHOLD_UPDATED', userId: (string) $household->owner_id, data: [
            'householdId' => $household->id,
            'ownerId' => $household->owner_id,
            'changes' => $data,
        ], );

        $this->notificationEventEmitter->emit($event);

        return $household;
    }

    /**
     * Delete a household.
     */
    public function delete(Household $household): bool
    {
        $householdId = $household->id;
        $ownerId = $household->owner_id;

        $deleted = (bool) $household->delete();

        if ($deleted) {
            $event = $this->notificationEventBuilder->build(eventType: 'HOUSEHOLD_DELETED', userId: (string) $ownerId, data: [
                'householdId' => $householdId,
                'ownerId' => $ownerId,
            ], );

            $this->notificationEventEmitter->emit($event);
        }

        return $deleted;
    }
}
