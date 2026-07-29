<?php

namespace App\Http\Controllers\Web\Compliance;

use App\Domain\Compliance\Actions\RaiseNonConformityAction;
use App\Domain\Compliance\Actions\ResolveNonConformityAction;
use App\Domain\Compliance\Data\NonConformityData;
use App\Domain\Compliance\Models\NonConformity;
use App\Domain\Labeling\Models\Label;
use App\Domain\Sterilization\Models\Cycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Compliance\RaiseNonConformityRequest;
use App\Http\Requests\Api\V1\Compliance\ResolveNonConformityRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Same NonConformityData/RaiseNonConformityAction/ResolveNonConformityAction
 * the mobile API uses (Api\V1\Compliance) — a second, web-only
 * presentation. The blast-radius preview shown before confirming a recall
 * lives on the cycle/label detail pages themselves (CycleController::show,
 * LabelController::show), via RaiseNonConformityAction::previewBlastRadius
 * — this controller is just the tenant-wide log + the write endpoints.
 */
class NonConformityController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', NonConformity::class);

        $nonConformities = QueryBuilder::for(NonConformity::class)
            ->allowedFilters(
                AllowedFilter::exact('subject_type'),
                // UI-30's "open non-conformities & recalls" dashboard card
                // shares this exact predicate with the list.
                AllowedFilter::callback('resolved', function ($query, $value): void {
                    $value ? $query->whereNotNull('resolved_at') : $query->whereNull('resolved_at');
                }),
            )
            ->defaultSort('-raised_at')
            ->allowedSorts('raised_at')
            ->cursorPaginate(perPage: 25)
            ->withQueryString();

        return Inertia::render('compliance/non-conformities/index', [
            'nonConformities' => NonConformityData::collect($nonConformities, CursorPaginatedDataCollection::class),
            // NonConformityPolicy::resolve() is a per-instance ability
            // (needs a NonConformity to check against) — the list's
            // "does this user manage non-conformities at all" question
            // is the flat permission itself, same one that ability gates.
            'canManage' => $request->user()->can('non_conformities.manage'),
            'filters' => $request->input('filter', []),
        ]);
    }

    public function store(RaiseNonConformityRequest $request, RaiseNonConformityAction $action): RedirectResponse
    {
        $this->authorize('create', NonConformity::class);

        $subject = $request->string('subject_type')->toString() === 'cycle'
            ? Cycle::findOrFail($request->string('subject_id')->toString())
            : Label::findOrFail($request->string('subject_id')->toString());

        $action->execute($subject, $request->user(), $request->string('description')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Non-conformity raised.']);

        return back();
    }

    public function resolve(NonConformity $nonConformity, ResolveNonConformityRequest $request, ResolveNonConformityAction $action): RedirectResponse
    {
        $this->authorize('resolve', $nonConformity);

        $action->execute($nonConformity, $request->user(), $request->string('resolution')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Non-conformity resolved.']);

        return back();
    }
}
