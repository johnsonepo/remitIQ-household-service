<?php

namespace App\Http\Controllers\Api\V1\Budget;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Budget\CreateBudgetCategoryRequest;
use App\Http\Requests\Api\Budget\UpdateBudgetCategoryRequest;
use App\Models\BudgetCategory;
use App\Services\Budget\BudgetCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetCategoryController extends BaseController
{
    public function __construct(private readonly BudgetCategoryService $service) {}

    /**
     * List budget categories available to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = $this->service->availableForUser($request->user()->id);

        return $this->success($categories, 'Budget categories retrieved successfully.');
    }

    /**
     * Create a custom budget category.
     */
    public function store(CreateBudgetCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', BudgetCategory::class);

        $category = $this->service->create($request->user()->id, $request->validated());

        return $this->created($category, 'Budget category created successfully.');
    }

    /**
     * Show a budget category.
     */
    public function show(Request $request, BudgetCategory $budgetCategory): JsonResponse
    {
        $this->authorize('view', $budgetCategory);

        $category = $this->service->findForUser($request->user()->id, $budgetCategory->id);

        return $this->success($category, 'Budget category retrieved successfully.');
    }

    /**
     * Update a custom budget category.
     */
    public function update(UpdateBudgetCategoryRequest $request, BudgetCategory $budgetCategory): JsonResponse
    {
        $this->authorize('update', $budgetCategory);

        $category = $this->service->update($budgetCategory, $request->user()->id, $request->validated());

        return $this->success($category, 'Budget category updated successfully.');
    }

    /**
     * Delete a custom budget category.
     */
    public function destroy(BudgetCategory $budgetCategory): JsonResponse
    {
        $this->authorize('delete', $budgetCategory);

        $this->service->delete($budgetCategory, request()->user()->id);

        return $this->success(null, 'Budget category deleted successfully.');
    }
}
