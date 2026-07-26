<?php

namespace App\Http\Requests\Api\V1\Equipment;

use App\Domain\Equipment\Enums\DeviceKind;
use App\Domain\Equipment\Enums\DeviceStatus;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CreateDeviceRequest extends FormRequest
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
            'site_id' => ['required', 'uuid', Rule::exists('sites', 'id')->where('tenant_id', Tenant::current()?->id)],
            'name' => ['required', 'string', 'max:255'],
            'serial_number' => ['required', 'string', 'max:100'],
            'kind' => ['sometimes', new Enum(DeviceKind::class)],
            'status' => ['sometimes', new Enum(DeviceStatus::class)],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'commissioned_at' => ['nullable', 'date'],
            'decommissioned_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
