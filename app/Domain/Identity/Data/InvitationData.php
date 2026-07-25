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
    ) {}

    public static function fromModel(Invitation $invitation): self
    {
        return new self($invitation->id, $invitation->email, $invitation->role, $invitation->expires_at);
    }
}
