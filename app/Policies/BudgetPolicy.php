<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    /**
     * A budget belongs to the user who created it — only they can
     * view, update, or delete it. This matches the confirmed design:
     * a sender abroad tracks their own budget, not a shared
     * household budget other members can see or edit.
     */
    public function view(User $user, Budget $budget): bool
    {
        return $budget->user_id === $user->id;
    }

    public function update(User $user, Budget $budget): bool
    {
        return $budget->user_id === $user->id;
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $budget->user_id === $user->id;
    }
}
