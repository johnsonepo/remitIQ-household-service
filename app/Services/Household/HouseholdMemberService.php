<?php

namespace App\Services\Household;

use App\Exceptions\ApiException;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Services\Notification\NotificationEventBuilder;
use App\Services\Notification\NotificationEventEmitter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class HouseholdMemberService
{
    public function __construct(private readonly NotificationEventBuilder $notificationEventBuilder, private readonly NotificationEventEmitter $notificationEventEmitter) {}

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

            $member = $member->load('user', 'household');

            $event = $this->notificationEventBuilder->build(eventType: 'HOUSEHOLD_MEMBER_ADDED', userId: (string) $user->id, data: [
                'memberId' => $member->id,
                'householdId' => $household->id,
                'userId' => $user->id,
                'role' => $member->role,
            ], );

            $this->notificationEventEmitter->emit($event);

            return $member;
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

        $member = $member->refresh()->load('user', 'household');

        $event = $this->notificationEventBuilder->build(eventType: 'HOUSEHOLD_MEMBER_ROLE_UPDATED', userId: (string) $member->user_id, data: [
            'memberId' => $member->id,
            'householdId' => $member->household_id,
            'userId' => $member->user_id,
            'role' => $member->role,
        ], );

        $this->notificationEventEmitter->emit($event);

        return $member;
    }

    /**
     * Remove a household member.
     */
    public function remove(HouseholdMember $member): bool
    {
        if ($member->isOwner()) {
            throw ApiException::badRequest('The household owner cannot be removed.');
        }

        $memberId = $member->id;
        $householdId = $member->household_id;
        $userId = $member->user_id;

        $deleted = (bool) $member->delete();

        if ($deleted) {
            $event = $this->notificationEventBuilder->build(eventType: 'HOUSEHOLD_MEMBER_REMOVED', userId: (string) $userId, data: [
                'memberId' => $memberId,
                'householdId' => $householdId,
                'userId' => $userId,
            ], );

            $this->notificationEventEmitter->emit($event);
        }

        return $deleted;
    }
}
