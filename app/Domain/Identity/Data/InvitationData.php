<?php

namespace App\Domain\Identity\Data;

use App\Domain\Identity\Models\Invitation;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

class InvitationData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $role,
        public readonly CarbonInterface $expires_at,
        public readonly CarbonInterface $created_at,
        public readonly ?CarbonInterface $accepted_at,
        public readonly ?CarbonInterface $revoked_at,
        public readonly ?string $invited_by_name,
    ) {}

    public static function fromModel(Invitation $invitation): self
    {
        return new self(
            $invitation->id,
            $invitation->email,
            $invitation->role,
            $invitation->expires_at,
            $invitation->created_at,
            $invitation->accepted_at,
            $invitation->revoked_at,
            $invitation->invitedBy?->name,
        );
    }
}
