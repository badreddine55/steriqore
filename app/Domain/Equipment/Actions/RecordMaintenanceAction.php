<?php

namespace App\Domain\Equipment\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Equipment\Models\Device;
use App\Domain\Equipment\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Http\Request;

class RecordMaintenanceAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Device $device, User $actor, array $attributes): MaintenanceRecord
    {
        $record = $device->maintenanceRecords()->create($attributes + [
            'tenant_id' => $device->tenant_id,
            'recorded_by_user_id' => $actor->id,
        ]);

        $this->recordAuditEvent->execute(
            tenant: $device->tenant,
            actor: $actor,
            action: 'maintenance_record.created',
            subjectType: MaintenanceRecord::class,
            subjectId: $record->id,
            newValues: $attributes,
            request: $this->request,
        );

        return $record;
    }
}
