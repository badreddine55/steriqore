<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Purchasing\Models\Supplier;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class UpdateSupplierAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Supplier $supplier, User $actor, array $attributes): Supplier
    {
        if (
            isset($attributes['name'])
            && Supplier::where('name', $attributes['name'])->where('id', '!=', $supplier->id)->exists()
        ) {
            throw new ApiException('SUPPLIER_NAME_TAKEN', 'A supplier with this name already exists.', 409);
        }

        $oldValues = $supplier->only(array_keys($attributes));
        $supplier->update($attributes);

        $this->recordAuditEvent->execute(
            tenant: $supplier->tenant,
            actor: $actor,
            action: 'supplier.updated',
            subjectType: Supplier::class,
            subjectId: $supplier->id,
            oldValues: $oldValues,
            newValues: $attributes,
            request: $this->request,
        );

        return $supplier;
    }
}
