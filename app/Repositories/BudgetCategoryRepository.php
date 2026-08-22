<?php

namespace App\Repositories;

use App\Models\BudgetCategory;
use Illuminate\Database\Eloquent\Collection;

class BudgetCategoryRepository extends BaseRepository
{
    public function __construct(BudgetCategory $model)
    {
        parent::__construct($model);
    }

    /**
     * Get categories available to a user.
     *
     * Includes system/default categories and the user's custom categories.
     *
     * @return Collection<int, BudgetCategory>
     */
    public function availableForUser(int $userId): Collection
    {
        /** @var Collection<int, BudgetCategory> $categories */
        $categories = $this->model->newQuery()
            ->where('is_default', true)
            ->orWhere('user_id', $userId)
            ->orderBy('name')
            ->get();

        return $categories;
    }

    /**
     * Get categories owned by a user.
     *
     * @return Collection<int, BudgetCategory>
     */
    public function forUser(int $userId): Collection
    {
        /** @var Collection<int, BudgetCategory> $categories */
        $categories = $this->model->newQuery()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get();

        return $categories;
    }
}
