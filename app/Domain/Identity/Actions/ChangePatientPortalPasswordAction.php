<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Identity\Models\PatientPortalAccount;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePatientPortalPasswordAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function execute(
        PatientPortalAccount $account,
        string $currentPassword,
        string $newPassword,
        ?Request $request = null,
    ): void {
        if (! Hash::check($currentPassword, $account->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The provided current password does not match.',
            ]);
        }

        $tenant = $account->tenant ?? Tenant::find($account->tenant_id);

        if ($tenant) {
            TenantContext::run($tenant, function () use ($tenant, $account, $newPassword, $request) {
                $account->update([
                    'password' => Hash::make($newPassword),
                ]);

                $this->recordAuditEvent->execute(
                    tenant: $tenant,
                    actor: null,
                    action: 'patient_portal.password_changed',
                    subjectType: 'patient_portal_account',
                    subjectId: $account->id,
                    oldValues: null,
                    newValues: ['status' => $account->status],
                    reason: 'Patient portal password updated by patient.',
                    request: $request,
                );
            });
        } else {
            $account->update([
                'password' => Hash::make($newPassword),
            ]);
        }
    }
}
