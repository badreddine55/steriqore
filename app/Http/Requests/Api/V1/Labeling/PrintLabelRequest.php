<?php

namespace App\Http\Requests\Api\V1\Labeling;

use App\Domain\Labeling\Enums\LabelStatus;
use App\Domain\Labeling\Models\Label;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrintLabelRequest extends FormRequest
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
            // Required only on a reprint (the label is already Printed) —
            // CLAUDE.md invariant #4. Route model binding has already
            // resolved {label} by the time this runs.
            'reason' => [
                Rule::requiredIf(fn () => $this->route('label') instanceof Label && $this->route('label')->status === LabelStatus::Printed),
                'nullable', 'string', 'max:2000',
            ],
        ];
    }
}
