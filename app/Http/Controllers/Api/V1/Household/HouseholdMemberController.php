<?php

namespace App\Http\Controllers\Api\V1\Household;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Household\AddHouseholdMemberRequest;
use App\Http\Requests\Api\Household\UpdateHouseholdMemberRequest;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Services\Household\HouseholdMemberService;
use Illuminate\Http\JsonResponse;

class HouseholdMemberController extends BaseController
{
    public function __construct(private readonly HouseholdMemberService $service) {}

    /**
     * List household members.
     */
    public function index(Household $household): JsonResponse
    {
        $this->authorize('view', $household);

        $members = $this->service->list($household);

        return $this->success($members, 'Household members retrieved successfully.');
    }

    /**
     * Add a user to the household.
     */
    public function store(AddHouseholdMemberRequest $request, Household $household): JsonResponse
    {
        $this->authorize('manageMembers', $household);

        $user = User::findOrFail($request->validated()['user_id']);

        $member = $this->service->add($household, $user, $request->validated());

        return $this->created($member, 'Household member added successfully.');
    }

    /**
     * Show a household member.
     */
    public function show(Household $household, HouseholdMember $member): JsonResponse
    {
        $this->authorize('view', $household);

        $member = $this->service->find($household, $member->id);

        return $this->success($member, 'Household member retrieved successfully.');
    }

    /**
     * Update a household member.
     */
    public function update(UpdateHouseholdMemberRequest $request, Household $household, HouseholdMember $member): JsonResponse
    {
        $this->authorize('manageMemberRole', [$household, $member]);

        $member = $this->service->find($household, $member->id);

        $member = $this->service->update($member, $request->validated());

        return $this->success($member, 'Household member updated successfully.');
    }

    /**
     * Remove a member from the household.
     */
    public function destroy(Household $household, HouseholdMember $member): JsonResponse
    {
        $this->authorize('removeMember', [$household, $member]);

        $member = $this->service->find($household, $member->id);

        $this->service->remove($member);

        return $this->success(null, 'Household member removed successfully.');
    }
}
