<?php

namespace App\Services\Budget;

use App\Exceptions\ApiException;
use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Repositories\BudgetItemRepository;
use Illuminate\Database\Eloquent\Collection;

class BudgetItemService
{
    public function __construct(private readonly BudgetItemRepository $repository) {}

    /**
     * Get all items belonging to a budget.
     *
     * @return Collection<int, BudgetItem>
     */
    public function forBudget(Budget $budget, int $userId): Collection
    {
        $this->ensureBudgetOwnership($budget, $userId);

        return $this->repository->forBudget($budget->id);
    }

    /**
     * Create a budget item.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Budget $budget, int $userId, array $data): BudgetItem
    {
        $this->ensureBudgetOwnership($budget, $userId);

        $category = $this->findAccessibleCategory((string) $data['budget_category_id'], $userId);

        $existing = $this->repository->forBudgetCategory($budget->id, $category->id);

        if ($existing !== null) {
            throw ApiException::conflict('This category already exists in the budget.');
        }

        $data['budget_id'] = $budget->id;

        /** @var BudgetItem $item */
        $item = $this->repository->create($data);

        return $item->load('category');
    }

    /**
     * Find an item belonging to a budget.
     */
    public function findForBudget(Budget $budget, int $userId, string $itemId): BudgetItem
    {
        $this->ensureBudgetOwnership($budget, $userId);

        $item = $this->repository->findForBudget($budget->id, $itemId);

        if ($item === null) {
            throw ApiException::notFound('Budget item not found.');
        }

        return $item;
    }

    /**
     * Update a budget item.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(BudgetItem $item, Budget $budget, int $userId, array $data): BudgetItem
    {
        $this->ensureBudgetOwnership($budget, $userId);
        $this->ensureItemBelongsToBudget($item, $budget);

        if (isset($data['budget_category_id'])) {
            $category = $this->findAccessibleCategory((string) $data['budget_category_id'], $userId);

            $existing = $this->repository->forBudgetCategory($budget->id, $category->id);

            if ($existing !== null && $existing->id !== $item->id) {
                throw ApiException::conflict('This category already exists in the budget.');
            }
        }

        $item->update($data);

        return $item->fresh('category');
    }

    /**
     * Delete a budget item.
     */
    public function delete(BudgetItem $item, Budget $budget, int $userId): bool
    {
        $this->ensureBudgetOwnership($budget, $userId);
        $this->ensureItemBelongsToBudget($item, $budget);

        return (bool) $item->delete();
    }

    /**
     * Ensure the authenticated user owns the budget.
     */
    private function ensureBudgetOwnership(Budget $budget, int $userId): void
    {
        if ($budget->user_id !== $userId) {
            throw ApiException::forbidden('You do not have access to this budget.');
        }
    }

    /**
     * Ensure the item belongs to the supplied budget.
     */
    private function ensureItemBelongsToBudget(BudgetItem $item, Budget $budget): void
    {
        if ($item->budget_id !== $budget->id) {
            throw ApiException::notFound('Budget item not found.');
        }
    }

    /**
     * Find a category accessible to the user.
     */
    private function findAccessibleCategory(string $categoryId, int $userId): BudgetCategory
    {
        /** @var BudgetCategory|null $category */
        $category = BudgetCategory::query()
            ->whereKey($categoryId)
            ->where(function ($query) use ($userId): void {
                $query
                    ->where('is_default', true)
                    ->orWhere('user_id', $userId);
            })
            ->first();

        if ($category === null) {
            throw ApiException::notFound('Budget category not found.');
        }

        return $category;
    }
}
