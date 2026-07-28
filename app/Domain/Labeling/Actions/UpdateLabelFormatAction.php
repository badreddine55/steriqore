<?php

namespace App\Domain\Labeling\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Labeling\Models\LabelFormatVersion;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * UI-28: versioned — see the 2026_08_07_000003 migration's docblock and
 * CreateDluRuleAction's. Applies to future prints only; every existing
 * label_prints row already recorded which version rendered it.
 */
class UpdateLabelFormatAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Tenant $tenant, User $actor, array $attributes, string $reason): LabelFormatVersion
    {
        $version = LabelFormatVersion::create($attributes + [
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'reason' => $reason,
            'effective_from' => now(),
        ]);

        $this->recordAuditEvent->execute(
            tenant: $tenant,
            actor: $actor,
            action: 'label_format.updated',
            subjectType: LabelFormatVersion::class,
            subjectId: $version->id,
            newValues: $attributes,
            reason: $reason,
            request: $this->request,
        );

        return $version;
    }

    public static function current(Tenant $tenant): ?LabelFormatVersion
    {
        return LabelFormatVersion::where('tenant_id', $tenant->id)
            ->latest('effective_from')
            ->first();
    }
}
