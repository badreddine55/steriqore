<?php

namespace App\Domain\Equipment\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Equipment\Models\Device;
use App\Domain\Equipment\Models\DeviceProgram;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class CreateDeviceProgramAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Device $device, User $actor, array $attributes): DeviceProgram
    {
        if ($device->programs()->where('name', $attributes['name'])->exists()) {
            throw new ApiException('DEVICE_PROGRAM_NAME_TAKEN', 'This device already has a program with this name.', 409);
        }

        $program = $device->programs()->create($attributes + ['tenant_id' => $device->tenant_id]);
        // is_active isn't always in $attributes; the in-memory model won't
        // see its DB-level default unless re-read (same pitfall as Product).
        $program->refresh();

        $this->recordAuditEvent->execute(
            tenant: $device->tenant,
            actor: $actor,
            action: 'device_program.created',
            subjectType: DeviceProgram::class,
            subjectId: $program->id,
            newValues: $attributes,
            request: $this->request,
        );

        return $program;
    }
}
