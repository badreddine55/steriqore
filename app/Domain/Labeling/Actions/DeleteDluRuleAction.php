<?php

namespace App\Domain\Labeling\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Labeling\Models\DluRule;
use App\Models\User;
use Illuminate\Http\Request;

class DeleteDluRuleAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(DluRule $rule, User $actor): void
    {
        $rule->delete();

        $this->recordAuditEvent->execute(
            tenant: $rule->tenant,
            actor: $actor,
            action: 'dlu_rule.deleted',
            subjectType: DluRule::class,
            subjectId: $rule->id,
            request: $this->request,
        );
    }
}
