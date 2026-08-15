<?php

namespace App\Services\Household;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use App\Exceptions\ApiException;


class HouseholdMemberService
{
    /**
     * Get all members of a household.
     *
     * @return Collection<int, HouseholdMember>
     */
    public function list(Household $household): Collection
    {
        /** @var Collection<int, HouseholdMember> $members */
        $members = $household->memberships()
            ->with('user')
            ->orderBy('joined_at')
            ->get();

        return $members;
    }

    /**
     * Find a member within a household.
     */
    public function find(Household $household, string $memberId): HouseholdMember
    {
        /** @var HouseholdMember|null $member */
        $member = $household->memberships()
            ->with('user')
            ->whereKey($memberId)
            ->first();

        if (! $member) {
            throw (new ModelNotFoundException)->setModel(HouseholdMember::class, [$memberId]);
        }

        return $member;
    }

    /**
     * Add a user to a household.
     *
     * @param  array<string, mixed>  $data
     */
    public function add(Household $household, User $user, array $data): HouseholdMember
    {
        $role = $data['role'] ?? 'member';

        if ($role === 'owner') {
            throw ApiException::badRequest('The owner role cannot be assigned through member management.');
        }

        if ($household->owner_id === $user->id) {
            throw ApiException::conflict('The household owner is already a member of this household.');
        }

        if ($household->memberships()->where('user_id', $user->id)->exists()) {
            throw ApiException::conflict('User is already a member of this household.');
        }

        return DB::transaction(function () use ($household, $user, $role): HouseholdMember {
            /** @var HouseholdMember $member */
            $member = $household->memberships()->create([
                'user_id' => $user->id,
                'role' => $role,
                'joined_at' => now(),
            ]);

            return $member->load('user');
        });
    }

    /**
     * Update a household member role.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(HouseholdMember $member, array $data): HouseholdMember
    {
        $role = $data['role'];

        if ($member->isOwner()) {
            throw ApiException::badRequest('The household owner role cannot be changed.');
        }

        if ($role === 'owner') {
            throw ApiException::badRequest('The owner role cannot be assigned through member management.');
        }

        $member->update([
            'role' => $role,
        ]);

        return $member->refresh()->load('user');
    }

    /**
     * Remove a household member.
     */
    public function remove(HouseholdMember $member): bool
    {
        if ($member->isOwner()) {
            throw ApiException::badRequest('The household owner cannot be removed.');
        }

        return (bool) $member->delete();
    }
}
