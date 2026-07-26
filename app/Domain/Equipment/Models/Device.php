<?php

namespace App\Domain\Equipment\Models;

use App\Domain\Equipment\Enums\DeviceKind;
use App\Domain\Equipment\Enums\DeviceStatus;
use App\Domain\Tenancy\Models\Site;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $site_id
 * @property string $name
 * @property string $serial_number
 * @property DeviceKind $kind
 * @property DeviceStatus $status
 * @property string|null $manufacturer
 * @property string|null $model
 * @property Carbon|null $commissioned_at
 * @property Carbon|null $decommissioned_at
 * @property string|null $notes
 */
class Device extends Model
{
    use BelongsToTenant;
    use HasUuidv7;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'site_id', 'name', 'serial_number', 'kind', 'status',
        'manufacturer', 'model', 'commissioned_at', 'decommissioned_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DeviceKind::class,
            'status' => DeviceStatus::class,
            'commissioned_at' => 'date',
            'decommissioned_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return HasMany<DeviceProgram, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(DeviceProgram::class);
    }

    /**
     * @return HasMany<MaintenanceRecord, $this>
     */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }
}
