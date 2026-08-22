<?php

namespace App\Services\Remittance;

use App\Exceptions\ApiException;
use App\Models\Remittance;
use App\Repositories\HouseholdRepository;
use App\Repositories\RemittanceRepository;
use App\Services\Notification\NotificationEventBuilder;
use App\Services\Notification\NotificationEventEmitter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class RemittanceService
{
    public function __construct(private readonly RemittanceRepository $repository, private readonly HouseholdRepository $householdRepository, private readonly NotificationEventBuilder $notificationEventBuilder, private readonly NotificationEventEmitter $notificationEventEmitter) {}

    /**
     * Get all remittances belonging to a user.
     *
     * @return Collection<int, Remittance>
     */
    public function forUser(int $userId): Collection
    {
        return $this->repository->forUser($userId);
    }

    /**
     * Get remittances for a household.
     *
     * @return Collection<int, Remittance>
     */
    public function forHousehold(int $userId, string $householdId): Collection
    {
        if (! $this->householdRepository->isAccessibleByUser($householdId, $userId)) {
            throw ApiException::forbidden('You do not have access to this household.');
        }

        return $this->repository->forUserHousehold($userId, $householdId);
    }

    /**
     * Create a remittance record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(int $userId, array $data): Remittance
    {
        $householdId = (string) $data['household_id'];

        if (! $this->householdRepository->isAccessibleByUser($householdId, $userId)) {
            throw ApiException::forbidden('You do not have access to this household.');
        }

        $data['user_id'] = $userId;

        /** @var Remittance $remittance */
        $remittance = $this->repository->create($data);

        $remittance = $remittance->fresh([
            'household',
            'provider',
            'attachments',
        ]);

        $event = $this->notificationEventBuilder->build(eventType: 'REMITTANCE_CREATED', userId: (string) $userId, data: [
            'remittanceId' => $remittance->id,
            'householdId' => $remittance->household_id,
            'userId' => $remittance->user_id,
            'providerId' => $remittance->transfer_provider_id,
            'amountSent' => $remittance->amount_sent,
            'amountReceived' => $remittance->amount_received,
            'exchangeRate' => $remittance->exchange_rate,
            'sentDate' => Carbon::parse($remittance->sent_at)->toISOString(),
        ], );

        $this->notificationEventEmitter->emit($event);

        return $remittance;
    }

    /**
     * Get a remittance belonging to a user.
     */
    public function findForUser(int $userId, string $remittanceId): Remittance
    {
        /** @var Remittance|null $remittance */
        $remittance = $this->repository->findForUser($userId, $remittanceId);

        if ($remittance === null) {
            throw ApiException::notFound('Remittance not found.');
        }

        return $remittance;
    }

    /**
     * Update a remittance.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Remittance $remittance, array $data): Remittance
    {
        $remittance->update($data);

        $remittance = $remittance->fresh([
            'household',
            'provider',
            'attachments',
        ]);

        $event = $this->notificationEventBuilder->build(eventType: 'REMITTANCE_UPDATED', userId: (string) $remittance->user_id, data: [
            'remittanceId' => $remittance->id,
            'householdId' => $remittance->household_id,
            'userId' => $remittance->user_id,
            'providerId' => $remittance->transfer_provider_id,
            'amountSent' => $remittance->amount_sent,
            'amountReceived' => $remittance->amount_received,
            'exchangeRate' => $remittance->exchange_rate,
            'sentDate' => Carbon::parse($remittance->sent_at)->toISOString(),
        ], );

        $this->notificationEventEmitter->emit($event);

        return $remittance;
    }

    /**
     * Delete a remittance.
     */
    public function delete(Remittance $remittance): bool
    {
        $remittanceId = $remittance->id;
        $householdId = $remittance->household_id;
        $userId = $remittance->user_id;

        $deleted = (bool) $remittance->delete();

        if ($deleted) {
            $event = $this->notificationEventBuilder->build(eventType: 'REMITTANCE_DELETED', userId: (string) $userId, data: [
                'remittanceId' => $remittanceId,
                'householdId' => $householdId,
                'userId' => $userId,
            ], );

            $this->notificationEventEmitter->emit($event);
        }

        return $deleted;
    }

    /**
     * Get filtered remittance history for a user.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Remittance>
     */
    public function historyForUser(int $userId, array $filters = []): Collection
    {
        if (isset($filters['household_id'])) {
            $householdId = (string) $filters['household_id'];

            if (! $this->householdRepository->isAccessibleByUser($householdId, $userId)) {
                throw ApiException::forbidden('You do not have access to this household.');
            }
        }

        return $this->repository->historyForUser($userId, $filters);
    }
}
