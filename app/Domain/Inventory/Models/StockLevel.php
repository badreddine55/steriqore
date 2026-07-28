<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Tenancy\Models\StorageLocation;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A projection over stock_movements — see the migration's docblock. Never
 * created/updated directly outside RecordStockMovementAction /
 * RebuildStockLevelsAction.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $batch_id
 * @property string $location_id
 * @property int $quantity
 */
class StockLevel extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    protected $fillable = ['tenant_id', 'batch_id', 'location_id', 'quantity'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
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
}
