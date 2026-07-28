<?php

namespace App\Domain\Labeling\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only (UI-28) — never call ->update()/->delete(); the DB refuses
 * it regardless via a trigger, same as label_prints/cycle_releases. One
 * row per change to a (packaging_type, storage_condition) rule; DluRule
 * itself stays the fast "current" projection every existing query reads.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $packaging_type
 * @property string $storage_condition
 * @property int $shelf_life_days
 * @property string $actor_id
 * @property string $reason
 * @property Carbon $effective_from
 */
class DluRuleVersion extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'packaging_type', 'storage_condition', 'shelf_life_days',
        'actor_id', 'reason', 'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'shelf_life_days' => 'integer',
            'effective_from' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
