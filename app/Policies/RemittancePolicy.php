<?php

namespace App\Policies;

use App\Models\Remittance;
use App\Models\User;

class RemittancePolicy
{
    /**
     * Same ownership model as Budget — a remittance record belongs
     * to the sender who tracked it, not the receiving household
     * collectively.
     */
    public function view(User $user, Remittance $remittance): bool
    {
        return $remittance->user_id === $user->id;
    }

    public function update(User $user, Remittance $remittance): bool
    {
        return $remittance->user_id === $user->id;
    }

    public function delete(User $user, Remittance $remittance): bool
    {
        return $remittance->user_id === $user->id;
    }
}
