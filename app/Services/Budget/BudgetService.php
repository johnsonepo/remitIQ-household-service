<?php

namespace App\Services\Budget;

use App\Exceptions\ApiException;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Repositories\BudgetRepository;
use App\Repositories\HouseholdRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class BudgetService
{
    public function __construct(private readonly BudgetRepository $repository, private readonly HouseholdRepository $householdRepository) {}

    /**
     * Get all budgets belonging to a user.
     *
     * @return Collection<int, Budget>
     */
    public function forUser(int $userId): Collection
    {
        return $this->repository->forUser($userId);
    }

    /**
     * Create a monthly budget.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(int $userId, array $data): Budget
    {
        $householdId = (string) $data['household_id'];
        $month = (string) $data['month'];

        if (! $this->householdRepository->isAccessibleByUser($householdId, $userId)) {
            throw ApiException::forbidden('You do not have access to this household.');
        }

        $existing = $this->repository->forUserHouseholdMonth($userId, $householdId, $month);

        if ($existing !== null) {
            throw ApiException::conflict('A budget already exists for this household and month.');
        }

        $data['user_id'] = $userId;

        /** @var Budget $budget */
        $budget = $this->repository->create($data);

        return $budget->fresh(['household', 'items.category']);
    }

    /**
     * Get budget history for a user and household.
     *
     * @return Collection<int, Budget>
     */
    public function history(int $userId, string $householdId): Collection
    {
        if (! $this->householdRepository->isAccessibleByUser($householdId, $userId)) {
            throw ApiException::forbidden('You do not have access to this household.');
        }

        return $this->repository->historyForHousehold($userId, $householdId);
    }

    /**
     * Get a budget belonging to a user.
     */
    public function findForUser(int $userId, string $budgetId): Budget
    {
        /** @var Budget|null $budget */
        $budget = Budget::query()
            ->whereKey($budgetId)
            ->where('user_id', $userId)
            ->with(['household', 'items.category'])
            ->first();

        if ($budget === null) {
            throw ApiException::notFound('Budget not found.');
        }

        return $budget;
    }

    /**
     * Update a budget.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Budget $budget, array $data): Budget
    {
        if (isset($data['month'])) {
            $month = (string) $data['month'];

            $existing = $this->repository->forUserHouseholdMonth($budget->user_id, $budget->household_id, $month);

            if ($existing !== null && $existing->id !== $budget->id) {
                throw ApiException::conflict('A budget already exists for this household and month.');
            }
        }

        $budget->update($data);

        return $budget->fresh(['household', 'items.category']);
    }

    /**
     * Delete a budget.
     */
    public function delete(Budget $budget): bool
    {
        return (bool) $budget->delete();
    }

    /**
     * Compare two monthly budgets for the same household.
     *
     * @return array<string, mixed>
     */
    public function compare(int $userId, string $householdId, string $month, string $compareMonth): array
    {
        if (! $this->householdRepository->isAccessibleByUser($householdId, $userId)) {
            throw ApiException::forbidden('You do not have access to this household.');
        }

        $current = $this->repository->forUserHouseholdMonth($userId, $householdId, $month);

        $comparison = $this->repository->forUserHouseholdMonth($userId, $householdId, $compareMonth);

        if ($current === null) {
            throw ApiException::notFound('Current month budget not found.');
        }

        if ($comparison === null) {
            throw ApiException::notFound('Comparison month budget not found.');
        }

        $currentPlanned = (float) $current->items->sum('planned_amount');
        $currentActual = (float) $current->items->sum('actual_amount');

        $comparisonPlanned = (float) $comparison->items->sum('planned_amount');
        $comparisonActual = (float) $comparison->items->sum('actual_amount');

        $currentRemaining = $currentPlanned - $currentActual;
        $comparisonRemaining = $comparisonPlanned - $comparisonActual;

        /** @var Collection<int, BudgetItem> $currentItems */
        $currentItems = $current->items;

        /** @var Collection<int, BudgetItem> $comparisonItems */
        $comparisonItems = $comparison->items;

        $currentByCategory = $currentItems->keyBy('budget_category_id');
        $comparisonByCategory = $comparisonItems->keyBy('budget_category_id');

        $categoryIds = $currentByCategory
            ->keys()
            ->merge($comparisonByCategory->keys())
            ->unique();

        $categories = $categoryIds
            ->map(function (mixed $categoryId) use ($currentByCategory, $comparisonByCategory): array {
                $categoryId = (string) $categoryId;

                /** @var BudgetItem|null $currentItem */
                $currentItem = $currentByCategory->get($categoryId);

                /** @var BudgetItem|null $comparisonItem */
                $comparisonItem = $comparisonByCategory->get($categoryId);

                $currentPlanned = $currentItem !== null
                    ? (float) $currentItem->planned_amount
                    : 0.0;

                $currentActual = $currentItem !== null
                    ? (float) $currentItem->actual_amount
                    : 0.0;

                $comparisonPlanned = $comparisonItem !== null
                    ? (float) $comparisonItem->planned_amount
                    : 0.0;

                $comparisonActual = $comparisonItem !== null
                    ? (float) $comparisonItem->actual_amount
                    : 0.0;

                $currentRemaining = $currentPlanned - $currentActual;
                $comparisonRemaining = $comparisonPlanned - $comparisonActual;

                return [
                    'category_id' => $categoryId,
                    'category' => $currentItem !== null
                        ? $currentItem->category
                        : $comparisonItem?->category,
                    'current' => [
                        'planned' => $currentPlanned,
                        'actual' => $currentActual,
                        'remaining' => $currentRemaining,
                    ],
                    'comparison' => [
                        'planned' => $comparisonPlanned,
                        'actual' => $comparisonActual,
                        'remaining' => $comparisonRemaining,
                    ],
                    'change' => [
                        'planned' => $currentPlanned - $comparisonPlanned,
                        'actual' => $currentActual - $comparisonActual,
                        'remaining' => $currentRemaining - $comparisonRemaining,
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'household_id' => $householdId,
            'current' => [
                'month' => Carbon::parse($current->month)->toDateString(),
                'budget_id' => $current->id,
                'planned' => $currentPlanned,
                'actual' => $currentActual,
                'remaining' => $currentRemaining,
            ],
            'comparison' => [
                'month' => Carbon::parse($comparison->month)->toDateString(),
                'budget_id' => $comparison->id,
                'planned' => $comparisonPlanned,
                'actual' => $comparisonActual,
                'remaining' => $comparisonRemaining,
            ],
            'change' => [
                'planned' => $currentPlanned - $comparisonPlanned,
                'actual' => $currentActual - $comparisonActual,
                'remaining' => $currentRemaining - $comparisonRemaining,
            ],
            'categories' => $categories,
        ];
    }
}
