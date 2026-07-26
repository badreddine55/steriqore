<?php

namespace App\Domain\Equipment\Models;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $device_id
 * @property string $name
 * @property int $target_temperature_celsius
 * @property int $plateau_minutes
 * @property bool $is_active
 */
class DeviceProgram extends Model
{
    use BelongsToTenant;
    use HasUuidv7;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'device_id', 'name', 'target_temperature_celsius', 'plateau_minutes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target_temperature_celsius' => 'integer',
            'plateau_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
