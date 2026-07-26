<?php

namespace App\Domain\Compliance\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * CLAUDE.md invariant #3's promised mechanism: "Mistakes are corrected by
 * a non_conformity + recall, never by UPDATE." subject is either a Cycle
 * (recalls every label it produced) or a Label (recalls just that pouch).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $description
 * @property string $raised_by_user_id
 * @property Carbon $raised_at
 * @property string|null $resolved_by_user_id
 * @property Carbon|null $resolved_at
 * @property string|null $resolution
 */
class NonConformity extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    protected $fillable = [
        'tenant_id', 'subject_type', 'subject_id', 'description',
        'raised_by_user_id', 'raised_at', 'resolved_by_user_id', 'resolved_at', 'resolution',
    ];

    protected function casts(): array
    {
        return [
            'raised_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
