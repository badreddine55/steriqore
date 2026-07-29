<?php

namespace App\Domain\Compliance\Models;

use App\Domain\Compliance\Enums\SecurityEventType;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Deliberately NOT BelongsToTenant — a row here can reference two tenants
 * (claimed vs actual) or none at all, so it doesn't "belong" to one the
 * way every other tenant-owned table does. Deliberately not queried or
 * written through the app's normal connection either: this pins its own
 * $connection to `pgsql_admin`, the only connection the migration grants
 * any access to at all (see the migration's own docblock) — a plain
 * `SecurityEvent::create()` from request-serving code works exactly like
 * any other Eloquent model, it's just always talking to the admin
 * connection under the hood.
 *
 * Append-only (no update/delete route here, and the DB itself refuses
 * regardless of role — see the migration).
 *
 * @property string $id
 * @property SecurityEventType $type
 * @property string|null $actor_id
 * @property string|null $actor_label_snapshot
 * @property string|null $tenant_claimed_id
 * @property string|null $tenant_actual_id
 * @property string|null $route_name
 * @property string $method
 * @property string $path
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string $request_id
 * @property Carbon $occurred_at
 */
class SecurityEvent extends Model
{
    use HasUuidv7;

    protected $connection = 'pgsql_admin';

    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected $fillable = [
        'type', 'actor_id', 'actor_label_snapshot', 'tenant_claimed_id', 'tenant_actual_id',
        'route_name', 'method', 'path', 'subject_type', 'subject_id', 'ip', 'user_agent',
        'request_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => SecurityEventType::class,
            'occurred_at' => 'datetime',
        ];
    }
}
