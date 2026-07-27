<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Compliance\Data\AuditEventData;
use App\Domain\Compliance\Models\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Compliance\ListAuditEventsRequest;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AuditEventController extends Controller
{
    /**
     * The append-only trail for the practice the current token is scoped
     * to — filtered by AuditEvent's TenantScope global scope, not a manual
     * where() here. Newest first; cursor-paginated per the API conventions.
     *
     * @return CursorPaginatedDataCollection<int, AuditEventData>
     */
    public function index(ListAuditEventsRequest $request): CursorPaginatedDataCollection
    {
        $this->authorize('viewAny', AuditEvent::class);

        $events = QueryBuilder::for(AuditEvent::class)
            ->allowedFilters(
                AllowedFilter::exact('actor_id'),
                AllowedFilter::exact('action'),
                AllowedFilter::exact('subject_type'),
                AllowedFilter::callback('from', function ($query, $value): void {
                    $query->where('occurred_at', '>=', $value);
                }),
                AllowedFilter::callback('to', function ($query, $value): void {
                    $query->where('occurred_at', '<=', $value);
                }),
            )
            ->allowedSorts('occurred_at')
            ->defaultSort('-occurred_at')
            ->cursorPaginate(perPage: (int) $request->integer('limit', 20));

        return AuditEventData::collect($events, CursorPaginatedDataCollection::class);
    }
}
