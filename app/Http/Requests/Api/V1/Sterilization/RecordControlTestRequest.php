<?php

namespace App\Http\Requests\Api\V1\Sterilization;

use App\Domain\Sterilization\Enums\ControlTestResult;
use App\Domain\Sterilization\Enums\ControlTestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RecordControlTestRequest extends FormRequest
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
            'type' => ['required', new Enum(ControlTestType::class)],
            'result' => ['required', new Enum(ControlTestResult::class)],
            'performed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
