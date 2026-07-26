<?php

namespace Database\Seeders;

use App\Domain\Identity\Actions\RegisterTenantAction;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * A bare `User::create()` (the Laravel starter-kit default) has no
     * tenant membership at all, so it can't get past Web\SetCurrentTenant
     * on any real page (S7) — going through the same
     * RegisterTenantAction the "create a practice" flow actually uses
     * means this seeded account is a real owner of a real practice, not
     * a login that only satisfies auth but nothing downstream of it.
     */
    public function run(): void
    {
        if (Tenant::where('slug', 'demo')->exists()) {
            return;
        }

        $result = app(RegisterTenantAction::class)->execute(
            tenantName: 'Demo Practice',
            tenantSlug: 'demo',
            ownerName: 'Admin',
            ownerEmail: 'admin@steriqore.local',
            password: 'password',
        );

        TenantContext::run($result['tenant'], function () use ($result) {
            $result['tenant']->sites()->create(['name' => 'Cabinet Principal']);
        });
    }
}
