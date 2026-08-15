<?php

namespace App\Services\Household;

use App\Models\Household;
use App\Repositories\HouseholdRepository;
use Illuminate\Database\Eloquent\Collection;

class HouseholdService
{
    public function __construct(private readonly HouseholdRepository $repository) {}

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
     * Create a household owned by the authenticated user.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(int $userId, array $data): Household
    {
        $data['owner_id'] = $userId;

        /** @var Household $household */
        $household = $this->repository->create($data);

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

        return $household->refresh();
    }

    /**
     * Delete a household.
     */
    public function delete(Household $household): bool
    {
        return (bool) $household->delete();
    }
}
