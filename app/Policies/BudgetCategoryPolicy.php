<?php

namespace App\Policies;

use App\Models\BudgetCategory;
use App\Models\User;

class BudgetCategoryPolicy
{
    /**
     * View a budget category.
     *
     * Users can view system categories and their own custom categories.
     */
    public function view(User $user, BudgetCategory $category): bool
    {
        return $category->is_default
            || $category->user_id === $user->id;
    }

    /**
     * Create a custom budget category.
     */
    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Update a budget category.
     *
     * Default/system categories cannot be modified.
     */
    public function update(User $user, BudgetCategory $category): bool
    {
        return ! $category->is_default
            && $category->user_id === $user->id;
    }

    /**
     * Delete a budget category.
     *
     * Default/system categories cannot be deleted.
     */
    public function delete(User $user, BudgetCategory $category): bool
    {
        return ! $category->is_default
            && $category->user_id === $user->id;
    }
}
