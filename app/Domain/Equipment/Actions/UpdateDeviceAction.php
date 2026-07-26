<?php

namespace App\Domain\Equipment\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Equipment\Models\Device;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class UpdateDeviceAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Device $device, User $actor, array $attributes): Device
    {
        if (
            isset($attributes['serial_number'])
            && Device::where('serial_number', $attributes['serial_number'])->where('id', '!=', $device->id)->exists()
        ) {
            throw new ApiException('DEVICE_SERIAL_NUMBER_TAKEN', 'A device with this serial number already exists.', 409);
        }

        $oldValues = $device->only(array_keys($attributes));
        $device->update($attributes);

        $this->recordAuditEvent->execute(
            tenant: $device->tenant,
            actor: $actor,
            action: 'device.updated',
            subjectType: Device::class,
            subjectId: $device->id,
            oldValues: $oldValues,
            newValues: $attributes,
            request: $this->request,
        );

        return $device;
    }
}
