<?php

namespace App\Domain\Equipment\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Equipment\Models\Device;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class CreateDeviceAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Tenant $tenant, User $actor, array $attributes): Device
    {
        if (Device::where('serial_number', $attributes['serial_number'])->exists()) {
            throw new ApiException('DEVICE_SERIAL_NUMBER_TAKEN', 'A device with this serial number already exists.', 409);
        }

        $device = Device::create($attributes + ['tenant_id' => $tenant->id]);
        $device->refresh();

        $this->recordAuditEvent->execute(
            tenant: $tenant,
            actor: $actor,
            action: 'device.created',
            subjectType: Device::class,
            subjectId: $device->id,
            newValues: $attributes,
            request: $this->request,
        );

        return $device;
    }
}
