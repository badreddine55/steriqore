<?php

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Domain\Compliance\Actions\RaiseNonConformityAction;
use App\Domain\Compliance\Actions\ResolveNonConformityAction;
use App\Domain\Compliance\Data\NonConformityData;
use App\Domain\Compliance\Models\NonConformity;
use App\Domain\Labeling\Models\Label;
use App\Domain\Sterilization\Models\Cycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Compliance\RaiseNonConformityRequest;
use App\Http\Requests\Api\V1\Compliance\ResolveNonConformityRequest;
use Illuminate\Http\Request;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class NonConformityController extends Controller
{
    /**
     * @return CursorPaginatedDataCollection<int, NonConformityData>
     */
    public function index(Request $request): CursorPaginatedDataCollection
    {
        $this->authorize('viewAny', NonConformity::class);

        $nonConformities = QueryBuilder::for(NonConformity::class)
            ->allowedFilters(AllowedFilter::exact('subject_type'), AllowedFilter::exact('subject_id'))
            ->defaultSort('-raised_at')
            ->allowedSorts('raised_at')
            ->cursorPaginate(perPage: (int) $request->integer('limit', 20));

        return NonConformityData::collect($nonConformities, CursorPaginatedDataCollection::class);
    }

    public function store(RaiseNonConformityRequest $request, RaiseNonConformityAction $action): NonConformityData
    {
        $this->authorize('create', NonConformity::class);

        $subject = $request->string('subject_type')->toString() === 'cycle'
            ? Cycle::findOrFail($request->string('subject_id')->toString())
            : Label::findOrFail($request->string('subject_id')->toString());

        $nonConformity = $action->execute($subject, $request->user(), $request->string('description')->toString());

        return NonConformityData::fromModel($nonConformity);
    }

    public function show(NonConformity $nonConformity): NonConformityData
    {
        $this->authorize('view', $nonConformity);

        return NonConformityData::fromModel($nonConformity);
    }

    public function resolve(NonConformity $nonConformity, ResolveNonConformityRequest $request, ResolveNonConformityAction $action): NonConformityData
    {
        $this->authorize('resolve', $nonConformity);

        return NonConformityData::fromModel($action->execute($nonConformity, $request->user(), $request->string('resolution')->toString()));
    }
}
