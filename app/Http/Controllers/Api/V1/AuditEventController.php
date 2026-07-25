<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Compliance\Data\AuditEventData;
use App\Domain\Compliance\Models\AuditEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\LaravelData\CursorPaginatedDataCollection;

class AuditEventController extends Controller
{
    /**
     * The append-only trail for the practice the current token is scoped
     * to — filtered by AuditEvent's TenantScope global scope, not a manual
     * where() here. Newest first; cursor-paginated per the API conventions.
     *
     * @return CursorPaginatedDataCollection<int, AuditEventData>
     */
    public function index(Request $request): CursorPaginatedDataCollection
    {
        $this->authorize('viewAny', AuditEvent::class);

        $events = AuditEvent::orderBy('occurred_at', 'desc')
            ->cursorPaginate(perPage: (int) $request->integer('limit', 20));

        return AuditEventData::collect($events, CursorPaginatedDataCollection::class);
    }
}
