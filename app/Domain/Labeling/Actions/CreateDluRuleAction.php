<?php

namespace App\Domain\Labeling\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Labeling\Models\DluRule;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class CreateDluRuleAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Tenant $tenant, User $actor, array $attributes): DluRule
    {
        if (
            DluRule::where('packaging_type', $attributes['packaging_type'])
                ->where('storage_condition', $attributes['storage_condition'])
                ->exists()
        ) {
            throw new ApiException('DLU_RULE_ALREADY_EXISTS', 'A rule for this packaging/storage combination already exists.', 409);
        }

        $rule = DluRule::create($attributes + ['tenant_id' => $tenant->id]);

        $this->recordAuditEvent->execute(
            tenant: $tenant,
            actor: $actor,
            action: 'dlu_rule.created',
            subjectType: DluRule::class,
            subjectId: $rule->id,
            newValues: $attributes,
            request: $this->request,
        );

        return $rule;
    }
}
