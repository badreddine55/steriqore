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

    /**
     * @param array<string, mixed> $data
     */
    public function execute(Tenant $tenant, User $actor, array $data = []): Patient
    {
        return DB::transaction(function () use ($tenant, $actor, $data) {
            DB::table('tenants')->where('id', $tenant->id)->lockForUpdate()->first();

            $nextNumber = (int) Patient::withTrashed()->where('tenant_id', $tenant->id)
                ->selectRaw('max(cast(substring(reference from 5) as integer)) as max_number')
                ->value('max_number') + 1;

            $attributes = [
                'tenant_id' => $tenant->id,
                'reference' => 'PAT-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT),
            ];

            if (!empty($data['first_name'])) {
                $attributes['first_name'] = trim((string) $data['first_name']);
            }
            if (!empty($data['last_name'])) {
                $attributes['last_name'] = trim((string) $data['last_name']);
            }
            if (!empty($data['birth_date'])) {
                $attributes['birth_date'] = $data['birth_date'];
            }
            if (!empty($data['email'])) {
                $attributes['email'] = trim((string) $data['email']);
            }
            if (!empty($data['phone'])) {
                $attributes['phone'] = trim((string) $data['phone']);
            }
            if (!empty($data['nir'])) {
                $attributes['nir'] = trim((string) $data['nir']);
            }
            if (!empty($data['notes'])) {
                $attributes['notes'] = trim((string) $data['notes']);
            }
            if (isset($data['allergies']) && is_array($data['allergies'])) {
                $attributes['allergies'] = $data['allergies'];
            }

            $patient = Patient::create($attributes);

            $this->recordAuditEvent->execute(
                tenant: $tenant,
                actor: $actor,
                action: 'patient.created',
                subjectType: Patient::class,
                subjectId: $patient->id,
                newValues: [
                    'reference' => $patient->reference,
                    'first_name' => $patient->first_name,
                    'last_name' => $patient->last_name,
                ],
                request: $this->request,
            );

            return $patient;
        });
    }
}
