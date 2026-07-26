<?php

namespace App\Domain\Labeling\Models;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $packaging_type
 * @property string $storage_condition
 * @property int $shelf_life_days
 */
class DluRule extends Model
{
    use BelongsToTenant;
    use HasUuidv7;
    use SoftDeletes;

    protected $fillable = ['tenant_id', 'packaging_type', 'storage_condition', 'shelf_life_days'];

    protected function casts(): array
    {
        return [
            'shelf_life_days' => 'integer',
        ];
    }
}
