<?php

namespace App\Http\Controllers\Api\V1\Remittance;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Remittance\RemittanceAnalyticsRequest;
use App\Services\Remittance\RemittanceAnalyticsService;
use Illuminate\Http\JsonResponse;

class RemittanceAnalyticsController extends BaseController
{
    public function __construct(private readonly RemittanceAnalyticsService $service) {}

    /**
     * Get remittance analytics for the authenticated user.
     */
    public function index(RemittanceAnalyticsRequest $request): JsonResponse
    {
        $analytics = $this->service->forUser($request->user()->id, $request->validated());

        return $this->success($analytics, 'Remittance analytics retrieved successfully.');
    }
}
