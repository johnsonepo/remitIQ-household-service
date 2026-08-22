<?php

namespace App\Repositories;

use App\Models\BudgetItem;
use Illuminate\Database\Eloquent\Collection;

class BudgetItemRepository extends BaseRepository
{
    public function __construct(BudgetItem $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all items belonging to a budget.
     *
     * @return Collection<int, BudgetItem>
     */
    public function forBudget(string $budgetId): Collection
    {
        /** @var Collection<int, BudgetItem> $items */
        $items = $this->model->newQuery()
            ->where('budget_id', $budgetId)
            ->with('category')
            ->orderBy('created_at')
            ->get();

        return $items;
    }

    /**
     * Find an item belonging to a specific budget and category.
     */
    public function forBudgetCategory(string $budgetId, string $categoryId): ?BudgetItem
    {
        /** @var BudgetItem|null $item */
        $item = $this->model->newQuery()
            ->where('budget_id', $budgetId)
            ->where('budget_category_id', $categoryId)
            ->with('category')
            ->first();

        return $item;
    }

    /**
     * Find an item belonging to a specific budget.
     */
    public function findForBudget(string $budgetId, string $itemId): ?BudgetItem
    {
        /** @var BudgetItem|null $item */
        $item = $this->model->newQuery()
            ->where('budget_id', $budgetId)
            ->whereKey($itemId)
            ->with('category')
            ->first();

        return $item;
    }
}
