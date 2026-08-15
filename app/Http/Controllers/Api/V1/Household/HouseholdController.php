<?php

namespace App\Http\Controllers\Api\V1\Household;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Household\CreateHouseholdRequest;
use App\Http\Requests\Api\Household\UpdateHouseholdRequest;
use App\Models\Household;
use App\Services\Household\HouseholdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HouseholdController extends BaseController
{
    public function __construct(private readonly HouseholdService $service) {}

    /**
     * Get households accessible to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $households = $this->service->forUser($request->user()->id);

        return $this->success($households, 'Households retrieved successfully.');
    }

    /**
     * Create a household.
     */
    public function store(CreateHouseholdRequest $request): JsonResponse
    {
        $household = $this->service->create($request->user()->id, $request->validated());

        return $this->created($household, 'Household created successfully.');
    }

    /**
     * Display a household.
     */
    public function show(Household $household): JsonResponse
    {
        Gate::authorize('view', $household);

        return $this->success($household->load(['owner', 'members']), 'Household retrieved successfully.');
    }

    /**
     * Update a household.
     */
    public function update(UpdateHouseholdRequest $request, Household $household): JsonResponse
    {
        Gate::authorize('update', $household);

        $household = $this->service->update($household, $request->validated());

        return $this->success($household, 'Household updated successfully.');
    }

    /**
     * Delete a household.
     */
    public function destroy(Household $household): JsonResponse
    {
        Gate::authorize('delete', $household);

        $this->service->delete($household);

        return $this->success(null, 'Household deleted successfully.');
    }
}
