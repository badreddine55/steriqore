<?php

namespace App\Http\Controllers\Api\V1\Mock;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * MOCK — sterilization_cycles lands in S3-ANA-02. See
 * ProductsMockController for why this exists and how it's retired.
 */
class CyclesMockController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                [
                    'id' => '01977a1e-2c3b-7000-8000-000000000020',
                    'device_id' => '01977a1e-2c3b-7000-8000-000000000030',
                    'cycle_number' => 'A-2026-000042',
                    'status' => 'released',
                    'result' => 'compliant',
                    'started_at' => '2026-07-24T08:00:00Z',
                    'ended_at' => '2026-07-24T08:45:00Z',
                ],
            ],
            'meta' => [
                'next_cursor' => null,
            ],
        ]);
    }
}
