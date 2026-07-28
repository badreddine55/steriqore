<?php

namespace App\Http\Requests\Api\V1\Labeling;

use App\Domain\Labeling\Enums\LabelSheetLayout;
use App\Domain\Labeling\Enums\PrimaryLabelCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateLabelFormatRequest extends FormRequest
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
            'primary_code' => ['required', new Enum(PrimaryLabelCode::class)],
            'sheet_layout' => ['required', new Enum(LabelSheetLayout::class)],
            'show_batch_number' => ['sometimes', 'boolean'],
            'show_product_name' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
