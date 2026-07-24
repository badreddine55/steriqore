<?php

namespace App\Domain\Identity\Models;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Extends Sanctum's default token model with a mandatory tenant_id: a token
 * authenticates for exactly one tenant, chosen at login. See docs/erd.md
 * decision #3 — this is what lets every later API request resolve "current
 * tenant" from the token alone, never from a client-supplied parameter.
 *
 * @property string $tenant_id
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = ['name', 'token', 'abilities', 'expires_at', 'tenant_id'];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
