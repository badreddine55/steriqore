<?php

namespace App\Domain\Sterilization\Models;

use App\Domain\Sterilization\Enums\CycleReleaseDecision;
use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable (CLAUDE.md invariant #3) — never call ->update()/->delete();
 * the DB refuses it regardless via a trigger. One row per cycle, ever.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $cycle_id
 * @property CycleReleaseDecision $decision
 * @property string $released_by_user_id
 * @property Carbon $released_at
 * @property string|null $reason
 */
class CycleRelease extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'cycle_id', 'decision', 'released_by_user_id', 'released_at', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'decision' => CycleReleaseDecision::class,
            'released_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Cycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }
}
