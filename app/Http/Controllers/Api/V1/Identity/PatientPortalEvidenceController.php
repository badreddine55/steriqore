<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domain\Identity\Models\PatientPortalAccount;
use App\Domain\Traceability\Actions\SearchEvidenceAction;
use App\Domain\Traceability\Data\EvidenceSearchResultData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\LaravelData\CursorPaginatedDataCollection;

class PatientPortalEvidenceController extends Controller
{
    /**
     * @return CursorPaginatedDataCollection<int, EvidenceSearchResultData>
     */
    public function __invoke(Request $request, SearchEvidenceAction $action): CursorPaginatedDataCollection
    {
        $account = $request->user();
        if (! $account instanceof PatientPortalAccount) {
            abort(403);
        }

        // Hardcode the linked patient reference — ignores any client-supplied filter
        $filters = [
            'patient_reference' => $account->patient->reference,
        ];

        $results = $action->execute($filters, (int) $request->integer('limit', 20));

        return EvidenceSearchResultData::collect($results, CursorPaginatedDataCollection::class);
    }
}
