<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class CreateSupplierAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Tenant $tenant, User $actor, array $attributes): Supplier
    {
        if (Supplier::where('name', $attributes['name'])->exists()) {
            throw new ApiException('SUPPLIER_NAME_TAKEN', 'A supplier with this name already exists.', 409);
        }

        $supplier = Supplier::create($attributes + ['tenant_id' => $tenant->id]);

        $this->recordAuditEvent->execute(
            tenant: $tenant,
            actor: $actor,
            action: 'supplier.created',
            subjectType: Supplier::class,
            subjectId: $supplier->id,
            newValues: $attributes,
            request: $this->request,
        );

        return $supplier;
    }
}
