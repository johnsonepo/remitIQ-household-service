<?php

namespace App\Services\Budget;

use App\Exceptions\ApiException;
use App\Models\BudgetCategory;
use App\Repositories\BudgetCategoryRepository;
use Illuminate\Database\Eloquent\Collection;

class BudgetCategoryService
{
    public function __construct(private readonly BudgetCategoryRepository $repository) {}

    /**
     * Get system categories and the authenticated user's custom categories.
     *
     * @return Collection<int, BudgetCategory>
     */
    public function availableForUser(int $userId): Collection
    {
        return $this->repository->availableForUser($userId);
    }

    /**
     * Create a custom budget category.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(int $userId, array $data): BudgetCategory
    {
        $data['user_id'] = $userId;
        $data['is_default'] = false;

        /** @var BudgetCategory $category */
        $category = $this->repository->create($data);

        return $category;
    }

    /**
     * Find a budget category accessible to the user.
     */
    public function findForUser(int $userId, string $categoryId): BudgetCategory
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

        if (! $category) {
            throw ApiException::notFound('Budget category not found.');
        }

        return $category;
    }

    /**
     * Update a user's custom budget category.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(BudgetCategory $category, int $userId, array $data): BudgetCategory
    {
        if ($category->is_default || $category->user_id !== $userId) {
            throw ApiException::forbidden('You are not allowed to modify this budget category.');
        }

        $category->update($data);

        return $category->refresh();
    }

    /**
     * Delete a user's custom budget category.
     */
    public function delete(BudgetCategory $category, int $userId): bool
    {
        if ($category->is_default || $category->user_id !== $userId) {
            throw ApiException::forbidden('You are not allowed to delete this budget category.');
        }

        return (bool) $category->delete();
    }
}
