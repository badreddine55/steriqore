<?php

namespace App\Domain\Compliance\Actions;

use App\Domain\Compliance\Enums\SecurityEventType;
use App\Domain\Compliance\Models\SecurityEvent;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The one place that writes to security_events, mirroring
 * RecordAuditEventAction's role for audit_events — except this is the
 * operator-only channel (see the migration's docblock): authorization
 * failures, cross-tenant resolution attempts, and RLS policy rejections,
 * never a practice's own business activity. actor_label_snapshot is
 * captured now, same reasoning as RecordAuditEventAction: a disabled or
 * renamed user must not rewrite what a past security event says about who
 * triggered it.
 */
class RecordSecurityEventAction
{
    public function __construct(private Request $request) {}

    public function execute(
        SecurityEventType $type,
        ?Tenant $tenantActual = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
    ): SecurityEvent {
        $actor = $this->request->user();
        $tenantClaimed = Tenant::current();

        return SecurityEvent::create([
            'type' => $type,
            'actor_id' => $actor?->id,
            'actor_label_snapshot' => $actor ? "{$actor->name} <{$actor->email}>" : null,
            'tenant_claimed_id' => $tenantClaimed?->id,
            'tenant_actual_id' => $tenantActual?->id,
            'route_name' => $this->request->route()?->getName(),
            'method' => $this->request->method(),
            'path' => $this->request->path(),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'request_id' => $this->request->attributes->get('request_id') ?? (string) Str::uuid7(),
            'occurred_at' => now(),
        ]);
    }
}
