<?php

namespace App\Repositories;

use App\Models\Household;
use Illuminate\Database\Eloquent\Collection;

class HouseholdRepository extends BaseRepository
{
    public function __construct(Household $model)
    {
        parent::__construct($model);
    }

    /**
     * Get households accessible to a user.
     *
     * @return Collection<int, Household>
     */
    public function forUser(int $userId): Collection
    {
        /** @var Collection<int, Household> $households */
        $households = Household::query()
            ->where('owner_id', $userId)
            ->orWhereHas('members', function ($query) use ($userId): void {
                $query->where('users.id', $userId);
            })
            ->get();

        return $households;
    }
}
