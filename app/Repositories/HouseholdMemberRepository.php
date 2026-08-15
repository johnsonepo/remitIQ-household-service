<?php

namespace App\Repositories;

use App\Models\Household;
use App\Models\HouseholdMember;
use Illuminate\Database\Eloquent\Collection;

class HouseholdMemberRepository extends BaseRepository
{
    public function __construct(HouseholdMember $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all members of a household.
     *
     * @return Collection<int, HouseholdMember>
     */
    public function forHousehold(Household $household): Collection
    {
        /** @var Collection<int, HouseholdMember> $members */
        $members = $this->model
            ->newQuery()
            ->with('user')
            ->where('household_id', $household->id)
            ->orderBy('joined_at')
            ->get();

        return $members;
    }

    /**
     * Find a member in a household by user ID.
     */
    public function findByUser(Household $household, int $userId): ?HouseholdMember
    {
        /** @var HouseholdMember|null $member */
        $member = $this->model
            ->newQuery()
            ->where('household_id', $household->id)
            ->where('user_id', $userId)
            ->first();

        return $member;
    }

    /**
     * Find a member in a household by member ID.
     */
    public function findInHousehold(Household $household, string $memberId): ?HouseholdMember
    {
        /** @var HouseholdMember|null $member */
        $member = $this->model
            ->newQuery()
            ->whereKey($memberId)
            ->where('household_id', $household->id)
            ->with('user')
            ->first();

        return $member;
    }
}
