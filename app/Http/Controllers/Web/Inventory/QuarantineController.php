<?php

namespace App\Http\Controllers\Web\Inventory;

use App\Domain\Compliance\Models\AuditEvent;
use App\Domain\Inventory\Data\QuarantinedBatchData;
use App\Domain\Inventory\Enums\BatchStatus;
use App\Domain\Inventory\Models\Batch;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;

/**
 * Read-only (UI-24) — the only way a batch's status becomes 'quarantined'
 * is RaiseNonConformityAction, and there is no un-quarantine route
 * anywhere in the domain yet (see QuarantinedBatchData's docblock), so
 * there is nothing to write here, only to surface: which batches are
 * quarantined, and — from the audit trail — who, why, and when.
 */
class QuarantineController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Batch::class);

        $batches = Batch::where('status', BatchStatus::Quarantined)
            ->with(['product', 'supplier'])
            ->withSum('stockLevels', 'quantity')
            ->orderByDesc('updated_at')
            ->cursorPaginate(perPage: 25)
            ->withQueryString();

        $quarantineEvents = AuditEvent::where('action', 'batch.quarantined')
            ->whereIn('subject_id', $batches->pluck('id'))
            ->get()
            ->keyBy('subject_id');

        return Inertia::render('inventory/quarantine/index', [
            'batches' => QuarantinedBatchData::collect(
                $batches->through(
                    fn (Batch $batch) => QuarantinedBatchData::fromModel($batch, $quarantineEvents->get($batch->id))
                ),
                CursorPaginatedDataCollection::class
            ),
        ]);
    }
}
