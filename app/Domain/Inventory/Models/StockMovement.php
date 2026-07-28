<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only (see the migration's docblock) — never call ->update()/
 * ->delete() on this model; the DB refuses it regardless via a trigger.
 *
 * @property string $id
 * @property string $tenant_id
 * @property StockMovementType $type
 * @property string $batch_id
 * @property string $location_id
 * @property int $qty
 * @property string|null $group_id
 * @property string|null $reason
 * @property string|null $actor_id
 * @property string|null $idempotency_key
 * @property Carbon $occurred_at
 */
class StockMovement extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'type', 'batch_id', 'location_id', 'qty',
        'group_id', 'reason', 'actor_id', 'idempotency_key', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'qty' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return BelongsTo<StorageLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
