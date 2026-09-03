<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Identity\Models\PatientPortalAccount;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Api\ApiException;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthenticatePatientPortalAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @return array{account: PatientPortalAccount, plain_text_token: string, patient_reference: string}
     */
    public function execute(string $tenantSlug, string $email, string $password): array
    {
        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if (! $tenant) {
            throw new ApiException('TENANT_NOT_FOUND', 'No practice matches this identifier.', 404);
        }

        $email = strtolower(trim($email));
        $account = PatientPortalAccount::where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first();

        if (! $account || ! Hash::check($password, $account->password)) {
            $this->logDenied($tenant, $account, 'INVALID_CREDENTIALS');

            throw new ApiException('INVALID_CREDENTIALS', 'These credentials do not match our records.', 401);
        }

        if ($account->status === 'disabled') {
            $this->logDenied($tenant, $account, 'ACCOUNT_DISABLED');

            throw new ApiException('ACCOUNT_DISABLED', 'This portal account has been disabled.', 403);
        }

        if ($account->status === 'pending_activation') {
            $this->logDenied($tenant, $account, 'ACCOUNT_PENDING_ACTIVATION');

            throw new ApiException('ACCOUNT_PENDING_ACTIVATION', 'This account has not been activated yet.', 403);
        }

        return TenantContext::run($tenant, function () use ($tenant, $account) {
            $this->recordAuditEvent->execute(
                tenant: $tenant,
                actor: null,
                action: 'patient_portal.login_succeeded',
                subjectType: PatientPortalAccount::class,
                subjectId: $account->id,
                request: $this->request,
            );

            $plainTextToken = Str::random(40);
            $sanctumToken = $account->tokens()->create([
                'name' => 'patient-portal',
                'token' => hash('sha256', $plainTextToken),
                'abilities' => ['patient-portal'],
                'tenant_id' => $tenant->id,
            ]);

            return [
                'account' => $account,
                'plain_text_token' => $sanctumToken->getKey().'|'.$plainTextToken,
                'patient_reference' => $account->patient->reference,
            ];
        });
    }

    private function logDenied(Tenant $tenant, ?PatientPortalAccount $account, string $reasonCode): void
    {
        $this->recordAuditEvent->execute(
            tenant: $tenant,
            actor: null,
            action: 'patient_portal.login_denied',
            subjectType: PatientPortalAccount::class,
            subjectId: $account instanceof PatientPortalAccount ? $account->id : $tenant->id,
            reason: $reasonCode,
            request: $this->request,
        );
    }
}
