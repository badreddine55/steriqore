<?php

namespace App\Domain\Tenancy\Models;

use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\TenantUser;
use App\Models\User;
use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Concerns\UsesMultitenancyConfig;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Concerns\ImplementsTenant;

/**
 * Implements spatie/laravel-multitenancy's IsTenant directly (not by
 * extending Spatie\Multitenancy\Models\Tenant, which forces a separate
 * "landlord" database connection we don't have — single shared DB + RLS
 * instead, see docs/erd.md section 4). `switch_tenant_tasks` is empty in
 * config/multitenancy.php, so makeCurrent() only binds this tenant into the
 * container; it does not switch any connection.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $plan
 * @property string $status
 * @property Carbon|null $trial_ends_at
 */
class Tenant extends Model implements IsTenant
{
    use HasUuidv7;
    use ImplementsTenant;
    use UsesMultitenancyConfig;

    protected $fillable = ['name', 'slug', 'plan', 'status', 'trial_ends_at'];

    /**
     * No per-tenant database in this architecture — required by IsTenant,
     * unused since switch_tenant_tasks never references it.
     */
    public function getDatabaseName(): string
    {
        return $this->slug;
    }

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * @return HasMany<TenantUser, $this>
     */
    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return BelongsToMany<User, $this, TenantUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->using(TenantUser::class)
            ->withPivot(['status', 'invited_at', 'joined_at', 'disabled_at'])
            ->withTimestamps();
    }
}
