<?php

namespace App\Http\Requests\Api\V1\Identity;

use App\Domain\Identity\Enums\TenantRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateInvitationRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'role' => ['required', new Enum(TenantRole::class)],
        ];
    }
}
