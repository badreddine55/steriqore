<?php

namespace App\Http\Requests\Api\V1\Compliance;

use Illuminate\Foundation\Http\FormRequest;

class ListAuditEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Nested under filter[...] — that's what QueryBuilder::allowedFilters
     * actually reads (its own convention, not this app's usual top-level
     * search params), so the validation has to match that shape or it
     * silently validates fields the request never sends.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'filter.actor_id' => ['nullable', 'uuid'],
            'filter.action' => ['nullable', 'string', 'max:255'],
            'filter.subject_type' => ['nullable', 'string', 'max:255'],
            'filter.from' => ['nullable', 'date'],
            'filter.to' => ['nullable', 'date', 'after_or_equal:filter.from'],
        ];
    }
}
