<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Inventory\Enums\AlertState;
use App\Domain\Inventory\Models\Alert;
use App\Models\User;
use App\Support\Api\ApiException;
use App\Support\Dashboard\DashboardCache;
use Illuminate\Http\Request;

/**
 * Manual acknowledgement — resolving here doesn't require the underlying
 * condition to have changed (a manager can dismiss a low-stock alert while
 * a reorder is already in transit). DetectAlertsAction will re-open it on
 * its own if the condition is still true next time it runs.
 */
class ResolveAlertAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Alert $alert, User $actor): Alert
    {
        if ($alert->state === AlertState::Resolved) {
            throw new ApiException('ALERT_ALREADY_RESOLVED', 'This alert has already been resolved.', 409);
        }

        $alert->update(['state' => AlertState::Resolved, 'resolved_by' => $actor->id, 'resolved_at' => now()]);

        $this->recordAuditEvent->execute(
            tenant: $alert->tenant,
            actor: $actor,
            action: 'alert.resolved',
            subjectType: Alert::class,
            subjectId: $alert->id,
            request: $this->request,
        );

        DashboardCache::forget($alert->tenant);

        return $alert;
    }
}
