<?php

namespace App\Domain\Identity\Data;

use App\Models\User;
use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self($user->id, $user->name, $user->email);
    }
}
