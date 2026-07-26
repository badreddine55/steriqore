<?php

namespace App\Http\Requests\Api\V1\Sterilization;

use Illuminate\Foundation\Http\FormRequest;

/**
 * mimes: is an allowlist, not just a size cap — a cycle attachment is a
 * photo of a printed ticket or a scanned document, never an arbitrary
 * file type (S5-ANA-01). Medialibrary's own denylist (config/media
 * -library.php's disallowed_extensions) is defense-in-depth on top of
 * this, not a substitute for it.
 */
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
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }
}
