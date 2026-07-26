<?php

namespace App\Domain\Equipment\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Equipment\Models\DeviceProgram;
use App\Models\User;
use Illuminate\Http\Request;

class DeleteDeviceProgramAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(DeviceProgram $program, User $actor): void
    {
        $program->delete();

        $this->recordAuditEvent->execute(
            tenant: $program->tenant,
            actor: $actor,
            action: 'device_program.deleted',
            subjectType: DeviceProgram::class,
            subjectId: $program->id,
            request: $this->request,
        );
    }
}
