<?php

namespace App\Http\Controllers\Api\V1\Remittance;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Remittance\CreateRemittanceRequest;
use App\Http\Requests\Api\Remittance\RemittanceHistoryRequest;
use App\Http\Requests\Api\Remittance\UpdateRemittanceRequest;
use App\Models\Remittance;
use App\Services\Remittance\RemittanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemittanceController extends BaseController
{
    public function __construct(private readonly RemittanceService $service) {}

    /**
     * List remittances belonging to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $remittances = $this->service->forUser($request->user()->id);

        return $this->success($remittances, 'Remittances retrieved successfully.');
    }

    /**
     * Get filtered remittance history.
     */
    public function history(RemittanceHistoryRequest $request): JsonResponse
    {
        $remittances = $this->service->historyForUser($request->user()->id, $request->validated());

        return $this->success($remittances, 'Remittance history retrieved successfully.');
    }

    /**
     * Get remittances for a household.
     */
    public function household(Request $request, string $householdId): JsonResponse
    {
        $remittances = $this->service->forHousehold($request->user()->id, $householdId);

        return $this->success($remittances, 'Household remittances retrieved successfully.');
    }

    /**
     * Create a remittance record.
     */
    public function store(CreateRemittanceRequest $request): JsonResponse
    {
        $remittance = $this->service->create($request->user()->id, $request->validated());

        return $this->created($remittance, 'Remittance created successfully.');
    }

    /**
     * Show a remittance.
     */
    public function show(Request $request, Remittance $remittance): JsonResponse
    {
        $this->authorize('view', $remittance);

        $remittance = $this->service->findForUser($request->user()->id, $remittance->id);

        return $this->success($remittance, 'Remittance retrieved successfully.');
    }

    /**
     * Update a remittance.
     */
    public function update(UpdateRemittanceRequest $request, Remittance $remittance): JsonResponse
    {
        $this->authorize('update', $remittance);

        $remittance = $this->service->update($remittance, $request->validated());

        return $this->success($remittance, 'Remittance updated successfully.');
    }

    /**
     * Delete a remittance.
     */
    public function destroy(Remittance $remittance): JsonResponse
    {
        $this->authorize('delete', $remittance);

        $this->service->delete($remittance);

        return $this->success(null, 'Remittance deleted successfully.');
    }
}
