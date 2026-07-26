<?php

namespace App\Domain\Equipment\Models;

use App\Domain\Equipment\Enums\MaintenanceKind;
use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A historical log entry, not a stateful record — there is deliberately no
 * update/delete route (CreateOnly + list), matching the "audited" note in
 * the backlog without inventing a full evidence-table trigger for a table
 * CLAUDE.md's invariant #7 doesn't name.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $device_id
 * @property string|null $recorded_by_user_id
 * @property MaintenanceKind $kind
 * @property string|null $technician
 * @property Carbon $performed_at
 * @property Carbon|null $next_due_at
 * @property string|null $description
 */
class MaintenanceRecord extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    protected $fillable = [
        'tenant_id', 'device_id', 'recorded_by_user_id', 'kind',
        'technician', 'performed_at', 'next_due_at', 'description',
    ];

    protected function casts(): array
    {
        return [
            'kind' => MaintenanceKind::class,
            'performed_at' => 'datetime',
            'next_due_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
