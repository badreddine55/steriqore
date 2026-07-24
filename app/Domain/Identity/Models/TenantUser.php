<?php

namespace App\Domain\Identity\Models;

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Membership pivot: which tenants a user belongs to, and their status within
 * each one. Role assignment itself lives in spatie/laravel-permission's teams
 * tables (added in S1-ANA-03) — this is membership lifecycle only.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property string $status
 * @property Carbon|null $invited_at
 * @property Carbon|null $joined_at
 * @property Carbon|null $disabled_at
 */
class TenantUser extends Pivot
{
    use HasUuidv7;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'tenant_user';

    protected $fillable = ['tenant_id', 'user_id', 'status', 'invited_at', 'joined_at', 'disabled_at'];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'joined_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
