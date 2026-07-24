<?php

namespace App\Http\Controllers\Api\V1\Mock;

use App\Http\Controllers\Controller;
use App\Support\Api\ApiException;
use Illuminate\Http\JsonResponse;

/**
 * MOCK — the real scan resolution endpoint (S3-ANA-05) needs labels, cycles,
 * and devices, none of which exist before S3. Three fixed demo codes let
 * Meryem build all three UI states (ok / recalled / expired) against this
 * mock today: "demo-recalled-label", "demo-expired-label", anything else
 * resolves as an active label. See ProductsMockController for how this is
 * retired once S3-ANA-05 ships for real.
 */
class LabelScanMockController extends Controller
{
    public function show(string $code): JsonResponse
    {
        if ($code === 'demo-recalled-label') {
            throw new ApiException('LABEL_RECALLED', 'This label has been recalled and must not be used.', 410);
        }

        if ($code === 'demo-expired-label') {
            throw new ApiException('LABEL_EXPIRED', 'This label is past its use-by date.', 410);
        }

        return response()->json([
            'data' => [
                'label_id' => $code,
                'status' => 'active',
                'cycle_number' => 'A-2026-000042',
                'device_name' => 'Melag Vacuklav 44 B+',
                'sterilized_at' => '2026-07-24T08:45:00Z',
                'use_by_date' => '2027-07-24',
                'sequence_in_cycle' => 3,
                'site_name' => 'Cabinet Centre-Ville',
            ],
        ]);
    }
}
