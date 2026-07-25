<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Purchasing\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Soft delete only — deletion is soft everywhere (CLAUDE.md invariant #8).
 */
class DeleteSupplierAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Supplier $supplier, User $actor): void
    {
        $supplier->delete();

        $this->recordAuditEvent->execute(
            tenant: $supplier->tenant,
            actor: $actor,
            action: 'supplier.deleted',
            subjectType: Supplier::class,
            subjectId: $supplier->id,
            request: $this->request,
        );
    }
}
