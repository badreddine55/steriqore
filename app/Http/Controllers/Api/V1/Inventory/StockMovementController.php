<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Domain\Inventory\Actions\AdjustStockAction;
use App\Domain\Inventory\Actions\IssueStockAction;
use App\Domain\Inventory\Actions\TransferStockAction;
use App\Domain\Inventory\Data\StockMovementData;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Inventory\AdjustStockRequest;
use App\Http\Requests\Api\V1\Inventory\IssueStockRequest;
use App\Http\Requests\Api\V1\Inventory\TransferStockRequest;
use Illuminate\Http\JsonResponse;

class StockMovementController extends Controller
{
    public function issue(IssueStockRequest $request, IssueStockAction $action): StockMovementData
    {
        $this->authorize('manage', StockMovement::class);

        $batch = Batch::findOrFail($request->string('batch_id')->toString());
        $location = StorageLocation::findOrFail($request->string('location_id')->toString());

        $movement = $action->execute(
            $batch,
            $location,
            $request->integer('qty'),
            $request->user(),
            $request->string('reason')->toString() ?: null,
        );

        return StockMovementData::fromModel($movement);
    }

    public function transfer(TransferStockRequest $request, TransferStockAction $action): JsonResponse
    {
        $this->authorize('manage', StockMovement::class);

        $batch = Batch::findOrFail($request->string('batch_id')->toString());
        $from = StorageLocation::findOrFail($request->string('from_location_id')->toString());
        $to = StorageLocation::findOrFail($request->string('to_location_id')->toString());

        $result = $action->execute(
            $batch,
            $from,
            $to,
            $request->integer('qty'),
            $request->user(),
            $request->string('reason')->toString() ?: null,
        );

        return response()->json([
            'debit' => StockMovementData::fromModel($result['debit']),
            'credit' => StockMovementData::fromModel($result['credit']),
        ], 201);
    }

    public function adjust(AdjustStockRequest $request, AdjustStockAction $action): StockMovementData
    {
        $this->authorize('manage', StockMovement::class);

        $batch = Batch::findOrFail($request->string('batch_id')->toString());
        $location = StorageLocation::findOrFail($request->string('location_id')->toString());

        $movement = $action->execute(
            $batch,
            $location,
            $request->integer('qty'),
            $request->user(),
            $request->string('reason')->toString(),
        );

        return StockMovementData::fromModel($movement);
    }
}
