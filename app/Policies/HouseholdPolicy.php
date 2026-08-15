<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\User;

class HouseholdPolicy
{
    public function view(User $user, Household $household): bool
    {
        return $household->owner_id === $user->id
            || $household->members()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Household $household): bool
    {
        return $household->owner_id === $user->id;
    }

    public function delete(User $user, Household $household): bool
    {
        return $household->owner_id === $user->id;
    }

    public function manageMembers(User $user, Household $household): bool
    {
        $membership = $household->memberships()->where('user_id', $user->id)->first();

        return $membership?->isAdmin() ?? false;
    }
}