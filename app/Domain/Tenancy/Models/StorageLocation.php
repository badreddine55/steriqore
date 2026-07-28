<?php

namespace App\Domain\Tenancy\Models;

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
 * @property string|null $room_id
 * @property string|null $parent_location_id
 * @property string $name
 * @property string|null $kind
 * @property Carbon|null $deleted_at
 */
class StorageLocation extends Model
{
    use BelongsToTenant;
    use HasUuidv7;
    use SoftDeletes;

    protected $fillable = ['tenant_id', 'site_id', 'room_id', 'parent_location_id', 'name', 'kind'];

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    /**
     * @return BelongsTo<StorageLocation, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'parent_location_id')->withTrashed();
    }

    /**
     * @return HasMany<StorageLocation, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(StorageLocation::class, 'parent_location_id');
    }
}
