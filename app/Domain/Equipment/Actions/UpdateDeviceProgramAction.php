<?php

namespace App\Domain\Equipment\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Equipment\Models\DeviceProgram;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class UpdateDeviceProgramAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(DeviceProgram $program, User $actor, array $attributes): DeviceProgram
    {
        if (
            isset($attributes['name'])
            && $program->device->programs()->where('name', $attributes['name'])->where('id', '!=', $program->id)->exists()
        ) {
            throw new ApiException('DEVICE_PROGRAM_NAME_TAKEN', 'This device already has a program with this name.', 409);
        }

        $oldValues = $program->only(array_keys($attributes));
        $program->update($attributes);

        $this->recordAuditEvent->execute(
            tenant: $program->tenant,
            actor: $actor,
            action: 'device_program.updated',
            subjectType: DeviceProgram::class,
            subjectId: $program->id,
            oldValues: $oldValues,
            newValues: $attributes,
            request: $this->request,
        );

        return $program;
    }
}
