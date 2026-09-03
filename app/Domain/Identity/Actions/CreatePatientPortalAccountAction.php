<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Identity\Models\PatientPortalAccount;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Traceability\Models\Patient;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CreatePatientPortalAccountAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function execute(
        Tenant $tenant,
        Patient $patient,
        string $email,
        ?User $actor = null,
        ?Request $request = null,
    ): PatientPortalAccount {
        // Validate patient belongs to tenant
        if ($patient->tenant_id !== $tenant->id) {
            throw ValidationException::withMessages([
                'patient' => 'Patient does not belong to this practice.',
            ]);
        }

        // Check if patient already has a portal account
        $existingAccount = PatientPortalAccount::where('patient_id', $patient->id)->first();
        if ($existingAccount) {
            throw ValidationException::withMessages([
                'patient' => 'This patient already has a portal account.',
            ]);
        }

        // Check email uniqueness within the practice
        $emailExists = PatientPortalAccount::where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->exists();
        if ($emailExists) {
            throw ValidationException::withMessages([
                'email' => 'This email address is already associated with another portal account in this practice.',
            ]);
        }

        return TenantContext::run($tenant, function () use ($tenant, $patient, $email, $actor, $request) {
            $account = PatientPortalAccount::create([
                'tenant_id' => $tenant->id,
                'patient_id' => $patient->id,
                'email' => $email,
                'password' => Hash::make('password'),
                'status' => 'active',
            ]);

            $this->recordAuditEvent->execute(
                tenant: $tenant,
                actor: $actor,
                action: 'patient_portal.created_by_staff',
                subjectType: 'patient',
                subjectId: $patient->id,
                oldValues: null,
                newValues: ['status' => 'active', 'patient_reference' => $patient->reference],
                reason: 'Patient portal account created by staff with default initial credential.',
                request: $request,
            );

            return $account;
        });
    }
}
