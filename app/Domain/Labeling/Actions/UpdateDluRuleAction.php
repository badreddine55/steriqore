<?php

namespace App\Domain\Labeling\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Labeling\Models\DluRule;
use App\Domain\Labeling\Models\DluRuleVersion;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * UI-28: versioned — see CreateDluRuleAction's docblock. Changing
 * shelf_life_days here never rewrites a label already printed: labels
 * freeze their own copy of it (and now a real FK to the version that
 * produced it) at generation time (CLAUDE.md).
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
    public function execute(DluRule $rule, User $actor, array $attributes, string $reason): DluRule
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

        return DB::transaction(function () use ($rule, $actor, $attributes, $reason) {
            $oldValues = $rule->only(array_keys($attributes));

            $version = DluRuleVersion::create([
                'tenant_id' => $rule->tenant_id,
                'packaging_type' => $attributes['packaging_type'] ?? $rule->packaging_type,
                'storage_condition' => $attributes['storage_condition'] ?? $rule->storage_condition,
                'shelf_life_days' => $attributes['shelf_life_days'] ?? $rule->shelf_life_days,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'effective_from' => now(),
            ]);

            $rule->update($attributes + ['current_version_id' => $version->id]);

            $this->recordAuditEvent->execute(
                tenant: $rule->tenant,
                actor: $actor,
                action: 'dlu_rule.updated',
                subjectType: DluRule::class,
                subjectId: $rule->id,
                oldValues: $oldValues,
                newValues: $attributes,
                reason: $reason,
                request: $this->request,
            );

            return $rule;
        });
    }
}
