<?php

namespace App\Domain\Sterilization\Models;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per pouch/package in the load — each will get its own label in
 * S3-ANA-04 ("One label per pouch", CLAUDE.md).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $cycle_id
 * @property string $description
 * @property int $sequence_in_cycle
 */
class CycleItem extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    protected $fillable = ['tenant_id', 'cycle_id', 'description', 'sequence_in_cycle'];

    protected function casts(): array
    {
        return [
            'sequence_in_cycle' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Cycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }
}
