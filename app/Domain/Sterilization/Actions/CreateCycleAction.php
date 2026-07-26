<?php

namespace App\Domain\Sterilization\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Equipment\Models\Device;
use App\Domain\Sterilization\Models\Cycle;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreateCycleAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Tenant $tenant, Device $device, User $actor, array $attributes): Cycle
    {
        if ($device->status->value !== 'active') {
            throw new ApiException('DEVICE_NOT_ACTIVE', 'This device is not active and cannot start a new cycle.', 409);
        }

        return DB::transaction(function () use ($tenant, $device, $actor, $attributes) {
            // Row lock on the device so two concurrent cycle creations on
            // the same autoclave can never compute the same next number
            // (CLAUDE.md's "Transactions with SELECT ... FOR UPDATE").
            Device::where('id', $device->id)->lockForUpdate()->first();

            $nextNumber = (Cycle::where('device_id', $device->id)->max('cycle_number') ?? 0) + 1;

            $cycle = Cycle::create($attributes + [
                'tenant_id' => $tenant->id,
                'device_id' => $device->id,
                'operator_id' => $actor->id,
                'cycle_number' => $nextNumber,
                'status' => 'draft',
            ]);
            $cycle->refresh();

            $this->recordAuditEvent->execute(
                tenant: $tenant,
                actor: $actor,
                action: 'cycle.created',
                subjectType: Cycle::class,
                subjectId: $cycle->id,
                newValues: $attributes + ['cycle_number' => $nextNumber],
                request: $this->request,
            );

            return $cycle;
        });
    }
}
