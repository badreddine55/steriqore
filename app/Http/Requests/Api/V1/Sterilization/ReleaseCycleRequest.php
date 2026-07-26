<?php

namespace App\Http\Requests\Api\V1\Sterilization;

use App\Domain\Sterilization\Enums\CycleReleaseDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ReleaseCycleRequest extends FormRequest
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
            'decision' => ['required', new Enum(CycleReleaseDecision::class)],
            // A compliant release doesn't need a reason; a rejection
            // always needs one — this is the release evidence, it has to
            // say why.
            'reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:2000'],
        ];
    }
}
