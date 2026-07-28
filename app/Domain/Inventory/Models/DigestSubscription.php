<?php

namespace App\Domain\Inventory\Models;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Role-based, not address-based (UI-28) — addresses are resolved at send
 * time from whoever currently holds that role, so this stays correct
 * automatically as team membership (UI-26) changes instead of going
 * stale. One row per (tenant, role).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $role
 * @property string $frequency
 * @property bool $enabled
 * @property Carbon|null $last_sent_at
 */
class DigestSubscription extends Model
{
    use BelongsToTenant;
    use HasUuidv7;

    protected $fillable = ['tenant_id', 'role', 'frequency', 'enabled', 'last_sent_at'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_sent_at' => 'datetime',
        ];
    }
}
