<?php

namespace App\Http\Requests\Api\V1\Sterilization;

use Illuminate\Foundation\Http\FormRequest;

class AttachCycleFileRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:10240'],
        ];
    }
}
