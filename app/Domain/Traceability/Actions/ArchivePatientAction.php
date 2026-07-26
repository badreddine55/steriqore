<?php

namespace App\Domain\Traceability\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Traceability\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Soft delete only — past usages (S4-ANA-02) keep referencing this
 * pseudonym's identity, same reasoning as disabled users (invariant #6).
 */
class ArchivePatientAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Patient $patient, User $actor): void
    {
        $patient->delete();

        $this->recordAuditEvent->execute(
            tenant: $patient->tenant,
            actor: $actor,
            action: 'patient.archived',
            subjectType: Patient::class,
            subjectId: $patient->id,
            request: $this->request,
        );
    }
}
