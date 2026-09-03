<?php

namespace App\Http\Resources;

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof User) {
            $user = $this->resource;
            $tenant = $user->tenants()->first() ?? Tenant::current();

            $role = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_uuid', $user->id)
                ->value('roles.name')
                ?? $user->roles()->first()?->name
                ?? 'staff';

            $address = $tenant?->address_line1
                ? trim($tenant->address_line1 . ($tenant->city ? ', ' . $tenant->city : ''))
                : '12 Boulevard Saint-Germain, Paris';

            return [
                'id' => 1,
                'uuid' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '+33 1 42 68 55 00',
                'role' => $role,
                'cabinet_id' => $tenant?->id,
                'cabinet_name' => $tenant?->name ?? 'Demo Practice',
                'cabinet_code' => $tenant?->slug ?? 'demo',
                'cabinet_address' => $address,
                'cabinet_phone' => $tenant?->contact_phone ?? '+33 1 42 68 55 00',
                'is_active' => is_null($user->disabled_at),
                'created_at' => $user->created_at,
                'last_login_at' => $user->created_at,
            ];
        }

        $item = (object) $this->resource;

        return [
            'id' => $item->id ?? 1,
            'uuid' => $item->uuid ?? $item->id ?? null,
            'name' => $item->name,
            'email' => $item->email,
            'phone' => $item->phone ?? '+33 1 42 68 55 00',
            'role' => $item->role ?? 'admin',
            'cabinet_id' => $item->cabinet_id ?? null,
            'cabinet_name' => $item->cabinet_name ?? null,
            'cabinet_code' => $item->cabinet_code ?? null,
            'cabinet_address' => $item->cabinet_address ?? '12 Boulevard Saint-Germain, Paris',
            'cabinet_phone' => $item->cabinet_phone ?? '+33 1 42 68 55 00',
            'is_active' => isset($item->is_active) ? (bool) $item->is_active : is_null($item->disabled_at ?? null),
            'created_at' => $item->created_at ?? null,
            'last_login_at' => $item->last_login_at ?? $item->created_at ?? null,
        ];
    }
}
