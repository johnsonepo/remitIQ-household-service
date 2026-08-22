<?php

namespace App\Services\Remittance;

use App\Exceptions\ApiException;
use App\Repositories\HouseholdRepository;
use App\Repositories\RemittanceRepository;

class RemittanceAnalyticsService
{
    public function __construct(private readonly RemittanceRepository $repository, private readonly HouseholdRepository $householdRepository) {}

    /**
     * Get remittance analytics for a user.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function forUser(int $userId, array $filters = []): array
    {
        if (isset($filters['household_id'])) {
            $householdId = (string) $filters['household_id'];

            if (! $this->householdRepository->isAccessibleByUser($householdId, $userId)) {
                throw ApiException::forbidden('You do not have access to this household.');
            }
        }

        return $this->repository->analyticsForUser($userId, $filters);
    }
}
