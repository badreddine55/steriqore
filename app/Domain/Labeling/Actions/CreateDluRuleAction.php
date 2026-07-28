<?php

namespace App\Domain\Labeling\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Labeling\Models\DluRule;
use App\Domain\Labeling\Models\DluRuleVersion;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * UI-28: the DLU rule table is versioned — every create/update writes an
 * immutable dlu_rule_versions row (actor + required reason + effective
 * date) alongside the mutable dlu_rules projection every existing query
 * still reads. See the 2026_08_07_000002 migration's docblock.
 */
class CreateDluRuleAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Tenant $tenant, User $actor, array $attributes, string $reason): DluRule
    {
        if (
            DluRule::where('packaging_type', $attributes['packaging_type'])
                ->where('storage_condition', $attributes['storage_condition'])
                ->exists()
        ) {
            throw new ApiException('DLU_RULE_ALREADY_EXISTS', 'A rule for this packaging/storage combination already exists.', 409);
        }

        return DB::transaction(function () use ($tenant, $actor, $attributes, $reason) {
            $version = DluRuleVersion::create([
                'tenant_id' => $tenant->id,
                'packaging_type' => $attributes['packaging_type'],
                'storage_condition' => $attributes['storage_condition'],
                'shelf_life_days' => $attributes['shelf_life_days'],
                'actor_id' => $actor->id,
                'reason' => $reason,
                'effective_from' => now(),
            ]);

            $rule = DluRule::create($attributes + [
                'tenant_id' => $tenant->id,
                'current_version_id' => $version->id,
            ]);

            $this->recordAuditEvent->execute(
                tenant: $tenant,
                actor: $actor,
                action: 'dlu_rule.created',
                subjectType: DluRule::class,
                subjectId: $rule->id,
                newValues: $attributes,
                reason: $reason,
                request: $this->request,
            );

            return $rule;
        });
    }
}
