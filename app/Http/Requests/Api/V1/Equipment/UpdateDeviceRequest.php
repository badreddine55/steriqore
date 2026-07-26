<?php

namespace App\Http\Requests\Api\V1\Equipment;

use App\Domain\Equipment\Enums\DeviceKind;
use App\Domain\Equipment\Enums\DeviceStatus;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateDeviceRequest extends FormRequest
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
            'site_id' => ['sometimes', 'uuid', Rule::exists('sites', 'id')->where('tenant_id', Tenant::current()?->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'serial_number' => ['sometimes', 'string', 'max:100'],
            'kind' => ['sometimes', new Enum(DeviceKind::class)],
            'status' => ['sometimes', new Enum(DeviceStatus::class)],
            'manufacturer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'commissioned_at' => ['sometimes', 'nullable', 'date'],
            'decommissioned_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
