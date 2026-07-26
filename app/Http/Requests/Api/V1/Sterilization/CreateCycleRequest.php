<?php

namespace App\Http\Requests\Api\V1\Sterilization;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCycleRequest extends FormRequest
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
        $tenantId = Tenant::current()?->id;

        return [
            'device_id' => ['required', 'uuid', Rule::exists('devices', 'id')->where('tenant_id', $tenantId)],
            'device_program_id' => ['nullable', 'uuid', Rule::exists('device_programs', 'id')->where('tenant_id', $tenantId)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
