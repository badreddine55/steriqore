<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Inventory\Models\DigestSubscription;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Role-based, not address-based — see DigestSubscription's docblock.
 * Replaces the tenant's whole subscription set in one save (there are
 * only six roles, this is never a paginated list), one audit event per
 * save rather than per row.
 */
class UpdateDigestSubscriptionsAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<int, array{role: string, frequency: string, enabled: bool}>  $subscriptions
     * @return Collection<int, DigestSubscription>
     */
    public function execute(Tenant $tenant, User $actor, array $subscriptions, string $reason): Collection
    {
        return DB::transaction(function () use ($tenant, $actor, $subscriptions, $reason) {
            $rows = collect($subscriptions)->map(
                fn (array $subscription) => DigestSubscription::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'role' => $subscription['role']],
                    ['frequency' => $subscription['frequency'], 'enabled' => $subscription['enabled']],
                )
            );

            $this->recordAuditEvent->execute(
                tenant: $tenant,
                actor: $actor,
                action: 'digest_subscriptions.updated',
                subjectType: DigestSubscription::class,
                subjectId: $tenant->id,
                newValues: ['subscriptions' => $subscriptions],
                reason: $reason,
                request: $this->request,
            );

            return $rows;
        });
    }
}
