<?php

namespace App\Domain\Tenancy\Models;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $timezone
 * @property bool $is_primary
 */
class Site extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    protected $fillable = [
        'tenant_id', 'name', 'address_line1', 'address_line2',
        'postal_code', 'city', 'country_code', 'timezone', 'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * @return HasMany<StorageLocation, $this>
     */
    public function storageLocations(): HasMany
    {
        return $this->hasMany(StorageLocation::class);
    }
}
