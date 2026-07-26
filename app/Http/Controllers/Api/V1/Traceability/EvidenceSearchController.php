<?php

namespace App\Http\Controllers\Api\V1\Traceability;

use App\Domain\Traceability\Actions\SearchEvidenceAction;
use App\Domain\Traceability\Data\EvidenceSearchResultData;
use App\Domain\Traceability\Models\LabelUsage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Traceability\EvidenceSearchRequest;
use Spatie\LaravelData\CursorPaginatedDataCollection;

class EvidenceSearchController extends Controller
{
    /**
     * @return CursorPaginatedDataCollection<int, EvidenceSearchResultData>
     */
    public function index(EvidenceSearchRequest $request, SearchEvidenceAction $action): CursorPaginatedDataCollection
    {
        $this->authorize('viewAny', LabelUsage::class);

        $results = $action->execute($request->validated(), (int) $request->integer('limit', 20));

        return EvidenceSearchResultData::collect($results, CursorPaginatedDataCollection::class);
    }
}
