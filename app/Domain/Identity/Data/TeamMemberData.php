<?php

namespace App\Domain\Identity\Data;

use App\Domain\Identity\Models\TenantUser;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * A tenant_user row (membership lifecycle) joined with the user's
 * identity and their single role for this tenant (spatie/permission
 * teams mode — one role per user per team, same as everywhere else this
 * session assigns one). Users are soft-disabled, never deleted
 * (invariant #6) — status is 'active' | 'disabled', nothing removes the
 * row or the user record itself.
 */
class TeamMemberData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $user_id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $role,
        public readonly string $status,
        public readonly ?CarbonInterface $joined_at,
        public readonly ?CarbonInterface $disabled_at,
    ) {}

    public static function fromModel(TenantUser $membership, ?string $role): self
    {
        return new self(
            $membership->id,
            $membership->user_id,
            $membership->user->name,
            $membership->user->email,
            $role,
            $membership->status,
            $membership->joined_at,
            $membership->disabled_at,
        );
    }
}
