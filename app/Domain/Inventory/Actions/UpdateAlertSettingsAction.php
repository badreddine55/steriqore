<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Inventory\Enums\AlertState;
use App\Domain\Inventory\Enums\AlertType;
use App\Domain\Inventory\Models\Alert;
use App\Domain\Inventory\Models\AlertSettings;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateAlertSettingsAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Tenant $tenant, User $actor, array $attributes, string $reason): AlertSettings
    {
        $settings = AlertSettings::forTenant($tenant);
        $oldValues = $settings->only(array_keys($attributes));
        $settings->update($attributes);

        $this->recordAuditEvent->execute(
            tenant: $tenant,
            actor: $actor,
            action: 'alert_settings.updated',
            subjectType: AlertSettings::class,
            subjectId: $settings->id,
            oldValues: $oldValues,
            newValues: $attributes,
            reason: $reason,
            request: $this->request,
        );

        return $settings;
    }

    /**
     * Read-only — the confirmation dialog shows this BEFORE saving a
     * near_expiry_days change, same pattern as ArchiveSiteAction's blast
     * radius. Mirrors DetectAlertsAction's own batch-with-stock query so
     * the numbers shown can never drift from what actually happens once
     * :command:`alerts:detect` next runs.
     *
     * @return array{appearing: int, disappearing: int}
     */
    public function previewNearExpiryImpact(Tenant $tenant, int $newNearExpiryDays): array
    {
        $batchIdsWithStock = DB::table('stock_levels')
            ->where('tenant_id', $tenant->id)
            ->groupBy('batch_id')
            ->havingRaw('SUM(quantity) > 0')
            ->pluck('batch_id');

        $wouldBeNearExpiry = Batch::where('tenant_id', $tenant->id)
            ->whereIn('id', $batchIdsWithStock)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>', now())
            ->where('expiry_date', '<=', now()->addDays($newNearExpiryDays))
            ->pluck('id');

        $currentlyOpen = Alert::where('tenant_id', $tenant->id)
            ->where('type', AlertType::NearExpiry)
            ->where('state', AlertState::Open)
            ->pluck('subject_id');

        return [
            'appearing' => $wouldBeNearExpiry->diff($currentlyOpen)->count(),
            'disappearing' => $currentlyOpen->diff($wouldBeNearExpiry)->count(),
        ];
    }
}
