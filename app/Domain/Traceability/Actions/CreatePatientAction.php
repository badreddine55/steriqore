<?php

namespace App\Domain\Traceability\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Traceability\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The only input is "create a new pseudonym" — there is nothing else to
 * capture (see the patients migration's docblock on why). `reference` is
 * generated here, never client-supplied, so it can never accidentally
 * carry real patient data typed into a free-text field.
 */
class CreatePatientAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Tenant $tenant, User $actor): Patient
    {
        return DB::transaction(function () use ($tenant, $actor) {
            DB::table('tenants')->where('id', $tenant->id)->lockForUpdate()->first();

            $nextNumber = (int) Patient::withTrashed()->where('tenant_id', $tenant->id)
                ->selectRaw('max(cast(substring(reference from 5) as integer)) as max_number')
                ->value('max_number') + 1;

            $patient = Patient::create([
                'tenant_id' => $tenant->id,
                'reference' => 'PAT-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT),
            ]);

            $this->recordAuditEvent->execute(
                tenant: $tenant,
                actor: $actor,
                action: 'patient.created',
                subjectType: Patient::class,
                subjectId: $patient->id,
                newValues: ['reference' => $patient->reference],
                request: $this->request,
            );

            return $patient;
        });
    }
}
