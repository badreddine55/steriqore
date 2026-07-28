<?php

namespace App\Http\Requests\Api\V1\Inventory;

use App\Domain\Identity\Enums\TenantRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateDigestSubscriptionsRequest extends FormRequest
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
            'subscriptions' => ['required', 'array'],
            'subscriptions.*.role' => ['required', new Enum(TenantRole::class)],
            'subscriptions.*.frequency' => ['required', 'in:daily,weekly'],
            'subscriptions.*.enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
