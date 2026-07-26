<?php

namespace App\Http\Requests\Api\V1\Traceability;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordLabelUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'uuid', Rule::exists('patients', 'id')->where('tenant_id', Tenant::current()?->id)],
            // users aren't tenant-scoped directly (BelongsToTenant would
            // be wrong for a global identity table) — membership is via
            // tenant_user, so that's what has to be checked here.
            'practitioner_id' => ['required', 'uuid', Rule::exists('tenant_user', 'user_id')->where('tenant_id', Tenant::current()?->id)],
            'procedure' => ['required', 'string', 'max:255'],
            'used_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
