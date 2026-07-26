<?php

namespace App\Domain\Equipment\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Equipment\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Soft delete only — deletion is soft everywhere (CLAUDE.md invariant #6).
 * Past cycles keep referencing this device row.
 */
class DeleteDeviceAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Device $device, User $actor): void
    {
        $device->delete();

        $this->recordAuditEvent->execute(
            tenant: $device->tenant,
            actor: $actor,
            action: 'device.deleted',
            subjectType: Device::class,
            subjectId: $device->id,
            request: $this->request,
        );
    }
}
