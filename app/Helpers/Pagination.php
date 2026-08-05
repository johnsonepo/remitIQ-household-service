<?php

namespace App\Helpers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * ============================================================================
 * Pagination Helper
 * ============================================================================
 *
 * Generates a consistent pagination metadata structure
 * for all RemitIQ API responses.
 *
 * Example:
 *
 * "meta": {
 *     "pagination": {
 *          "current_page": 1,
 *          "per_page": 15,
 *          "total": 100,
 *          "last_page": 7,
 *          "from": 1,
 *          "to": 15
 *     }
 * }
 *
 * ============================================================================
 */
class Pagination
{
    /**
     * Generate pagination metadata.
     */
    public static function meta(
        LengthAwarePaginator $paginator
    ): array {

        return [
            'pagination' => [
                'current_page' => $paginator->currentPage(),

                'per_page' => $paginator->perPage(),

                'total' => $paginator->total(),

                'last_page' => $paginator->lastPage(),

                'from' => $paginator->firstItem(),

                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
