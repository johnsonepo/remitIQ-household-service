<?php

namespace App\Http\Controllers\Api\V1\Budget;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Budget\CompareBudgetRequest;
use App\Http\Requests\Api\Budget\CreateBudgetRequest;
use App\Http\Requests\Api\Budget\UpdateBudgetRequest;
use App\Models\Budget;
use App\Services\Budget\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends BaseController
{
    public function __construct(private readonly BudgetService $service) {}

    /**
     * List budgets belonging to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $budgets = $this->service->forUser($request->user()->id);

        return $this->success($budgets, 'Budgets retrieved successfully.');
    }

    /**
     * Get budget history for a household.
     */
    public function history(Request $request, string $householdId): JsonResponse
    {
        $budgets = $this->service->history($request->user()->id, $householdId);

        return $this->success($budgets, 'Budget history retrieved successfully.');
    }

    /**
     * Create a monthly budget.
     */
    public function store(CreateBudgetRequest $request): JsonResponse
    {
        $budget = $this->service->create($request->user()->id, $request->validated());

        return $this->created($budget, 'Budget created successfully.');
    }

    /**
     * Show a budget.
     */
    public function show(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('view', $budget);

        $budget = $this->service->findForUser($request->user()->id, $budget->id);

        return $this->success($budget, 'Budget retrieved successfully.');
    }

    /**
     * Update a budget.
     */
    public function update(UpdateBudgetRequest $request, Budget $budget): JsonResponse
    {
        $this->authorize('update', $budget);

        $budget = $this->service->update($budget, $request->validated());

        return $this->success($budget, 'Budget updated successfully.');
    }

    /**
     * Delete a budget.
     */
    public function destroy(Budget $budget): JsonResponse
    {
        $this->authorize('delete', $budget);

        $this->service->delete($budget);

        return $this->success(null, 'Budget deleted successfully.');
    }

    /**
     * Compare two monthly budgets for a household.
     */
    public function compare(CompareBudgetRequest $request): JsonResponse
    {
        $result = $this->service->compare($request->user()->id, (string) $request->validated('household_id'), (string) $request->validated('month'), (string) $request->validated('compare_month'));

        return $this->success($result, 'Budgets compared successfully.');
    }
}
