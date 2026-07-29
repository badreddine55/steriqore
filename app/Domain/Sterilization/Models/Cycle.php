<?php

namespace App\Domain\Sterilization\Models;

use App\Domain\Compliance\Models\NonConformity;
use App\Domain\Equipment\Models\Device;
use App\Domain\Equipment\Models\DeviceProgram;
use App\Domain\Sterilization\Enums\CycleStatus;
use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Evidence table (CLAUDE.md invariant #7): no delete route, no SoftDeletes.
 * A cycle only ever moves forward through CycleStatus; mistakes past
 * Released/Rejected are corrected by a non-conformity + recall (S4), never
 * by editing this row.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $device_id
 * @property string|null $device_program_id
 * @property string $operator_id
 * @property int $cycle_number
 * @property CycleStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property string|null $notes
 */
class Cycle extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasUuidv7;
    use InteractsWithMedia;

    protected $fillable = [
        'tenant_id', 'device_id', 'device_program_id', 'operator_id',
        'cycle_number', 'status', 'started_at', 'completed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => CycleStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<DeviceProgram, $this>
     */
    public function deviceProgram(): BelongsTo
    {
        return $this->belongsTo(DeviceProgram::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CycleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CycleItem::class);
    }

    /**
     * @return HasMany<ControlTest, $this>
     */
    public function controlTests(): HasMany
    {
        return $this->hasMany(ControlTest::class);
    }

    /**
     * @return HasOne<CycleRelease, $this>
     */
    public function release(): HasOne
    {
        return $this->hasOne(CycleRelease::class);
    }

    /**
     * Standard Eloquent morph convention (subject_type/subject_id) matches
     * exactly what RaiseNonConformityAction already writes via
     * `$subject::class` — the dashboard's "rejected cycle with no
     * non-conformity yet" card is the first reader of this inverse.
     *
     * @return MorphMany<NonConformity, $this>
     */
    public function nonConformities(): MorphMany
    {
        return $this->morphMany(NonConformity::class, 'subject');
    }
}
