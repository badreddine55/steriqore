<?php

namespace App\Http\Controllers\Api\V1\Mock;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * MOCK — no `products` table exists yet (catalogue lands in S2-ANA-01).
 * Static example data so the mobile app can build its catalogue screens
 * against the eventual shape now, per S0-ANA-03's "ship mock examples"
 * requirement. Replace with a real controller in S2, keeping this response
 * shape unless a documented breaking change is agreed first.
 */
class ProductsMockController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                [
                    'id' => '01977a1e-2c3b-7000-8000-000000000001',
                    'name' => 'Sterilization pouch 90x260mm',
                    'reference' => 'POU-90260',
                    'unit' => 'box_of_200',
                    'is_sterilizable' => false,
                    'min_threshold' => 5,
                ],
            ],
            'meta' => [
                'next_cursor' => null,
            ],
        ]);
    }
}
