<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Catalog\Models\Product;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $purchase_order_id
 * @property string $product_id
 * @property int $qty_ordered
 * @property int $qty_received
 * @property string|null $unit_price
 */
class PurchaseOrderLine extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    protected $fillable = ['tenant_id', 'purchase_order_id', 'product_id', 'qty_ordered', 'qty_received', 'unit_price'];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'integer',
            'qty_received' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
