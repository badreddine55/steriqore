<?php

namespace App\Domain\Compliance\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only (invariant #7, CLAUDE.md): no update/delete route exists for
 * this model, and the DB itself refuses at the trigger level regardless —
 * see the migration. Never call ->update()/->delete() on this model; there
 * is deliberately no code path here that would let you.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string|null $actor_id
 * @property string|null $actor_label_snapshot
 * @property string $action
 * @property string $subject_type
 * @property string $subject_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $reason
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string $request_id
 * @property Carbon $occurred_at
 */
class AuditEvent extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected $fillable = [
        'tenant_id', 'actor_id', 'actor_label_snapshot', 'action', 'subject_type', 'subject_id',
        'old_values', 'new_values', 'reason', 'ip', 'user_agent', 'request_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'occurred_at' => 'datetime',
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
