<?php

namespace App\Http\Controllers\Web\Traceability;

use App\Domain\Equipment\Models\Device;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Traceability\Actions\SearchEvidenceAction;
use App\Domain\Traceability\Data\EvidenceSearchResultData;
use App\Domain\Traceability\Models\LabelUsage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Traceability\EvidenceSearchRequest;
use App\Models\User;
use App\Support\Data\OptionData;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;

/**
 * Same SearchEvidenceAction and EvidenceSearchResultData the mobile API's
 * EvidenceSearchController uses (Api\V1\Traceability) — this is a second,
 * web-only presentation of the identical query (REC-09: complete result
 * < 2s at pilot volume, via the btree indexes SearchEvidenceAction's own
 * docblock already covers), not new domain logic. Exporting these results
 * (EvidenceExportController already exists) is UI-22, not this screen.
 */
class EvidenceSearchController extends Controller
{
    public function index(EvidenceSearchRequest $request, SearchEvidenceAction $action): Response
    {
        $this->authorize('viewAny', LabelUsage::class);

        $results = $action->execute($request->validated(), 25);

        $tenant = Tenant::current();
        $devices = Device::orderBy('name')->get()
            ->map(fn (Device $device) => new OptionData($device->id, $device->name));
        $operators = User::whereHas(
            'tenantUsers',
            fn ($query) => $query->where('tenant_id', $tenant->id)->where('status', 'active')
        )->orderBy('name')->get()
            ->map(fn (User $user) => new OptionData($user->id, $user->name));

        return Inertia::render('traceability/evidence-search/index', [
            'results' => EvidenceSearchResultData::collect($results, CursorPaginatedDataCollection::class),
            'devices' => OptionData::collect($devices),
            'operators' => OptionData::collect($operators),
            'filters' => $request->validated(),
        ]);
    }
}
