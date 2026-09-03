<?php

namespace App\Domain\Identity\Data;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Data;

class UserData extends Data
{
    /**
     * @param  array<int, string>  $permissions
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $role = null,
        public readonly array $permissions = [],
    ) {}

    public static function fromModel(User $user): self
    {
        $roleName = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_uuid', $user->id)
            ->value('roles.name');

        if (! $roleName) {
            $roleName = $user->roles()->first()?->name ?? 'practitioner';
        }

        $permissions = [];
        try {
            $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        } catch (\Throwable $e) {
        }

        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $roleName,
            permissions: $permissions,
        );
    }
}
