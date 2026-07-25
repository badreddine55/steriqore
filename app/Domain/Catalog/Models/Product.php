<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Tenancy\Models\StorageLocation;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $category_id
 * @property string|null $default_location_id
 * @property string $name
 * @property string $reference
 * @property string $unit
 * @property int $min_threshold
 * @property bool $is_sterilizable
 * @property string|null $barcode
 */
class Product extends Model
{
    use BelongsToTenant;
    use HasUuidv7;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'category_id', 'default_location_id', 'name', 'reference',
        'unit', 'min_threshold', 'is_sterilizable', 'barcode',
    ];

    protected function casts(): array
    {
        return [
            'min_threshold' => 'integer',
            'is_sterilizable' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<StorageLocation, $this>
     */
    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'default_location_id');
    }
}
