<?php

namespace App\Http\Controllers\Api\V1\Budget;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Budget\CreateBudgetItemRequest;
use App\Http\Requests\Api\Budget\UpdateBudgetItemRequest;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Services\Budget\BudgetItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetItemController extends BaseController
{
    public function __construct(private readonly BudgetItemService $service) {}

    /**
     * List all items belonging to a budget.
     */
    public function index(Request $request, Budget $budget): JsonResponse
    {
        $items = $this->service->forBudget($budget, $request->user()->id);

        return $this->success($items, 'Budget items retrieved successfully.');
    }

    /**
     * Create a budget item.
     */
    public function store(CreateBudgetItemRequest $request, Budget $budget): JsonResponse
    {
        $item = $this->service->create($budget, $request->user()->id, $request->validated());

        return $this->created($item, 'Budget item created successfully.');
    }

    /**
     * Show a budget item.
     */
    public function show(Request $request, Budget $budget, BudgetItem $budgetItem): JsonResponse
    {
        $item = $this->service->findForBudget($budget, $request->user()->id, $budgetItem->id);

        return $this->success($item, 'Budget item retrieved successfully.');
    }

    /**
     * Update a budget item.
     */
    public function update(UpdateBudgetItemRequest $request, Budget $budget, BudgetItem $budgetItem): JsonResponse
    {
        $item = $this->service->update($budgetItem, $budget, $request->user()->id, $request->validated());

        return $this->success($item, 'Budget item updated successfully.');
    }

    /**
     * Delete a budget item.
     */
    public function destroy(Request $request, Budget $budget, BudgetItem $budgetItem): JsonResponse
    {
        $this->service->delete($budgetItem, $budget, $request->user()->id);

        return $this->success(null, 'Budget item deleted successfully.');
    }
}
