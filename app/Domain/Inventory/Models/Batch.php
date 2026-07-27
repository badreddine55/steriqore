<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Enums\BatchStatus;
use App\Domain\Purchasing\Models\Supplier;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $product_id
 * @property string|null $supplier_id
 * @property string $batch_number
 * @property Carbon|null $expiry_date
 * @property Carbon $received_at
 * @property BatchStatus $status
 */
class Batch extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    protected $fillable = ['tenant_id', 'product_id', 'supplier_id', 'batch_number', 'expiry_date', 'received_at', 'status'];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'received_at' => 'datetime',
            'status' => BatchStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<StockLevel, $this>
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }
}
