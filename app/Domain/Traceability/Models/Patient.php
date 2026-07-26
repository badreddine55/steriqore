<?php

namespace App\Domain\Traceability\Models;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $reference
 */
class Patient extends Model
{
    use BelongsToTenant;
    use HasUuidv7;
    use SoftDeletes;

    protected $fillable = ['tenant_id', 'reference'];
}
