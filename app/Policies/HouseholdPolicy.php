<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\HouseholdMember;
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
        if ($household->owner_id === $user->id) {
            return true;
        }

        /** @var HouseholdMember|null $membership */
        $membership = $household->memberships()
            ->where('user_id', $user->id)
            ->first();

        return $membership?->isAdmin() ?? false;
    }

    public function manageMemberRole(User $user, Household $household, HouseholdMember $member): bool
    {
        if ($household->owner_id !== $user->id) {
            return false;
        }

        return $member->household_id === $household->id;
    }

    public function removeMember(User $user, Household $household, HouseholdMember $member): bool
    {
        if ($member->household_id !== $household->id) {
            return false;
        }

        if ($member->isOwner() || $member->user_id === $household->owner_id) {
            return false;
        }

        if ($household->owner_id === $user->id) {
            return true;
        }

        /** @var HouseholdMember|null $membership */
        $membership = $household->memberships()
            ->where('user_id', $user->id)
            ->first();

        return $membership?->isAdmin() === true
            && $member->role === 'member';
    }

    public function inviteMembers(User $user, Household $household): bool
    {
        return $this->manageMembers($user, $household);
    }
}
