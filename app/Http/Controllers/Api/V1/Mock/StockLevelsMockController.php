<?php

namespace App\Http\Controllers\Api\V1\Mock;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * MOCK — the movement ledger and stock_levels projection land in S2-ANA-04.
 * See ProductsMockController for why this exists and how it's retired.
 */
class StockLevelsMockController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                [
                    'product_id' => '01977a1e-2c3b-7000-8000-000000000001',
                    'location_id' => '01977a1e-2c3b-7000-8000-000000000010',
                    'quantity' => 42,
                    'nearest_expiry' => '2027-03-01',
                ],
            ],
            'meta' => [
                'next_cursor' => null,
            ],
        ]);
    }
}
