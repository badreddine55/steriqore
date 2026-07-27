<?php

namespace App\Http\Controllers\Web\Compliance;

use App\Domain\Compliance\Data\AuditEventData;
use App\Domain\Compliance\Models\AuditEvent;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Data\OptionData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Same AuditEvent model/AuditEventData the mobile API uses
 * (Api\V1\AuditEventController) — a second, web-only presentation of the
 * identical query. The actor/action/subject-type filter option lists are
 * built from what's actually in this tenant's log (not a hardcoded
 * enum — new domains keep adding new action strings over time, so a
 * fixed list would just go stale).
 */
class AuditEventController extends Controller
{
    public function index(Request $request): Response
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
            ->cursorPaginate(perPage: 25)
            ->withQueryString();

        $tenant = Tenant::current();
        $actors = User::whereHas(
            'tenantUsers',
            fn ($query) => $query->where('tenant_id', $tenant->id)
        )->orderBy('name')->get()
            ->map(fn (User $user) => new OptionData($user->id, $user->name));

        $actions = AuditEvent::query()->distinct()->orderBy('action')->pluck('action');
        $subjectTypes = AuditEvent::query()->distinct()->orderBy('subject_type')->pluck('subject_type')
            ->map(fn (string $type) => new OptionData($type, class_basename($type)));

        return Inertia::render('compliance/audit-log/index', [
            'events' => AuditEventData::collect($events, CursorPaginatedDataCollection::class),
            'actors' => OptionData::collect($actors),
            'actions' => $actions,
            'subjectTypes' => OptionData::collect($subjectTypes),
            'filters' => $request->input('filter', []),
        ]);
    }
}
