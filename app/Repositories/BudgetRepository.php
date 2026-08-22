<?php

namespace App\Repositories;

use App\Models\Budget;
use Illuminate\Database\Eloquent\Collection;

class BudgetRepository extends BaseRepository
{
    public function __construct(Budget $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all budgets belonging to a user.
     *
     * @return Collection<int, Budget>
     */
    public function forUser(int $userId): Collection
    {
        /** @var Collection<int, Budget> $budgets */
        $budgets = $this->model->newQuery()
            ->where('user_id', $userId)
            ->with(['household', 'items.category'])
            ->orderByDesc('month')
            ->get();

        return $budgets;
    }

    /**
     * Get budget history for a user and household.
     *
     * @return Collection<int, Budget>
     */
    public function historyForHousehold(int $userId, string $householdId): Collection
    {
        /** @var Collection<int, Budget> $budgets */
        $budgets = $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('household_id', $householdId)
            ->with(['household', 'items.category'])
            ->orderByDesc('month')
            ->get();

        return $budgets;
    }

    /**
     * Get a user's budget for a specific household and month.
     */
    public function forUserHouseholdMonth(int $userId, string $householdId, string $month): ?Budget
    {
        /** @var Budget|null $budget */
        $budget = $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('household_id', $householdId)
            ->whereDate('month', $month)
            ->with(['household', 'items.category'])
            ->first();

        return $budget;
    }

    /**
     * Get a user's budget for a household and month with its items.
     */
    public function forUserHouseholdMonthWithItems(int $userId, string $householdId, string $month): ?Budget
    {
        /** @var Budget|null $budget */
        $budget = $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('household_id', $householdId)
            ->whereDate('month', $month)
            ->with(['household', 'items.category'])
            ->first();

        return $budget;
    }
}
