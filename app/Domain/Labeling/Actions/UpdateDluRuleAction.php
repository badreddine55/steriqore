<?php

namespace App\Domain\Labeling\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Labeling\Models\DluRule;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

/**
 * Changing shelf_life_days here never rewrites a label already printed —
 * labels freeze their own copy of it at creation time (CLAUDE.md).
 */
class UpdateDluRuleAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(DluRule $rule, User $actor, array $attributes): DluRule
    {
        if (
            (isset($attributes['packaging_type']) || isset($attributes['storage_condition']))
            && DluRule::where('packaging_type', $attributes['packaging_type'] ?? $rule->packaging_type)
                ->where('storage_condition', $attributes['storage_condition'] ?? $rule->storage_condition)
                ->where('id', '!=', $rule->id)
                ->exists()
        ) {
            throw new ApiException('DLU_RULE_ALREADY_EXISTS', 'A rule for this packaging/storage combination already exists.', 409);
        }

        $oldValues = $rule->only(array_keys($attributes));
        $rule->update($attributes);

        $this->recordAuditEvent->execute(
            tenant: $rule->tenant,
            actor: $actor,
            action: 'dlu_rule.updated',
            subjectType: DluRule::class,
            subjectId: $rule->id,
            oldValues: $oldValues,
            newValues: $attributes,
            request: $this->request,
        );

        return $rule;
    }
}
