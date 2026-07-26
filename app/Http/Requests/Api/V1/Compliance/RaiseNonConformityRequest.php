<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RaiseNonConformityRequest extends FormRequest
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
        $table = $this->input('subject_type') === 'cycle' ? 'cycles' : 'labels';

        return [
            'subject_type' => ['required', Rule::in(['cycle', 'label'])],
            'subject_id' => ['required', 'uuid', Rule::exists($table, 'id')->where('tenant_id', $tenantId)],
            'description' => ['required', 'string', 'max:2000'],
        ];
    }
}
