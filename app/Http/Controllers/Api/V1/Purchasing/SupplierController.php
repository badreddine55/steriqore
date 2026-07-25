<?php

namespace App\Http\Controllers\Api\V1\Purchasing;

use App\Domain\Purchasing\Actions\CreateSupplierAction;
use App\Domain\Purchasing\Actions\DeleteSupplierAction;
use App\Domain\Purchasing\Actions\UpdateSupplierAction;
use App\Domain\Purchasing\Data\SupplierData;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Purchasing\CreateSupplierRequest;
use App\Http\Requests\Api\V1\Purchasing\UpdateSupplierRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SupplierController extends Controller
{
    /**
     * @return CursorPaginatedDataCollection<int, SupplierData>
     */
    public function index(Request $request): CursorPaginatedDataCollection
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = QueryBuilder::for(Supplier::class)
            ->allowedFilters(AllowedFilter::partial('name'))
            ->allowedSorts('name', 'created_at')
            ->defaultSort('name')
            ->cursorPaginate(perPage: (int) $request->integer('limit', 20));

        return SupplierData::collect($suppliers, CursorPaginatedDataCollection::class);
    }

    public function store(CreateSupplierRequest $request, CreateSupplierAction $action): SupplierData
    {
        $this->authorize('create', Supplier::class);

        return SupplierData::fromModel($action->execute(Tenant::current(), $request->user(), $request->validated()));
    }

    public function show(Supplier $supplier): SupplierData
    {
        $this->authorize('view', $supplier);

        return SupplierData::fromModel($supplier);
    }

    public function update(Supplier $supplier, UpdateSupplierRequest $request, UpdateSupplierAction $action): SupplierData
    {
        $this->authorize('update', $supplier);

        return SupplierData::fromModel($action->execute($supplier, $request->user(), $request->validated()));
    }

    public function destroy(Supplier $supplier, Request $request, DeleteSupplierAction $action): Response
    {
        $this->authorize('delete', $supplier);

        $action->execute($supplier, $request->user());

        return response()->noContent();
    }
}
