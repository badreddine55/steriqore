<?php

namespace App\Domain\Compliance\Actions;

use App\Domain\Compliance\Models\AuditEvent;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The one place that writes to audit_events. Everything sensitive is
 * logged here: auth events, permission changes, releases, reprints,
 * recalls, adjustments, exports (CLAUDE.md's cross-cutting requirements).
 *
 * actor_label_snapshot is captured now, not read live off the user later —
 * a disabled or renamed user must not rewrite what past evidence says about
 * who did it (invariant #6).
 */
class RecordAuditEventAction
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function execute(
        ?Tenant $tenant,
        ?User $actor,
        string $action,
        string $subjectType,
        string $subjectId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?Request $request = null,
    ): AuditEvent {
        return AuditEvent::create([
            'tenant_id' => $tenant?->id,
            'actor_id' => $actor?->id,
            'actor_label_snapshot' => $actor ? "{$actor->name} <{$actor->email}>" : null,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => $request?->attributes->get('request_id') ?? (string) Str::uuid7(),
            'occurred_at' => now(),
        ]);
    }
}
