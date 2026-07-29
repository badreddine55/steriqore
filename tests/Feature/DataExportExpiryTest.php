<?php

use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Reporting\Enums\DataExportStatus;
use App\Domain\Reporting\Models\DataExportRequest;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Mirrors the other tenant-fixture helpers scattered across the suite,
 * renamed to avoid a global-function clash when the whole suite loads.
 */
function dataExportExpiryTenantWithOwner(): array
{
    $tenant = Tenant::create(['name' => 'Cabinet Test', 'slug' => 'cabinet-'.Str::lower(Str::random(10))]);

    $user = User::create([
        'name' => 'Alice Dupont',
        'email' => 'alice-'.Str::lower(Str::random(10)).'@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    app(SeedTenantRolesAction::class)->execute($tenant);

    return [$tenant, $user];
}

/**
 * data_export_requests has a Postgres RLS policy (invariant #7's second
 * line of defence) — the command must bind the full tenant context
 * (TenantContext::run), not just Eloquent's TenantScope via makeCurrent(),
 * or the query fails closed and silently expires nothing.
 */
test('the scheduled command reclaims the S3 object and expires the row past retention', function () {
    Storage::fake('backups');
    [$tenant, $owner] = dataExportExpiryTenantWithOwner();

    $filePath = "data-exports/{$tenant->id}/export.zip";
    Storage::disk('backups')->put($filePath, 'zip-bytes');

    TenantContext::run($tenant, fn () => DataExportRequest::create([
        'tenant_id' => $tenant->id,
        'requested_by_user_id' => $owner->id,
        'status' => DataExportStatus::Completed,
        'file_path' => $filePath,
        'expires_at' => now()->subMinute(),
    ]));

    Artisan::call('data-exports:expire');

    TenantContext::run($tenant, function () use ($tenant) {
        $request = DataExportRequest::where('tenant_id', $tenant->id)->sole();
        expect($request->status)->toBe(DataExportStatus::Expired);
        expect($request->file_path)->toBeNull();
    });

    Storage::disk('backups')->assertMissing($filePath);
});

test('an export that has not reached its retention window is left alone', function () {
    Storage::fake('backups');
    [$tenant, $owner] = dataExportExpiryTenantWithOwner();

    $filePath = "data-exports/{$tenant->id}/export.zip";
    Storage::disk('backups')->put($filePath, 'zip-bytes');

    TenantContext::run($tenant, fn () => DataExportRequest::create([
        'tenant_id' => $tenant->id,
        'requested_by_user_id' => $owner->id,
        'status' => DataExportStatus::Completed,
        'file_path' => $filePath,
        'expires_at' => now()->addDays(7),
    ]));

    Artisan::call('data-exports:expire');

    TenantContext::run($tenant, function () use ($tenant, $filePath) {
        $request = DataExportRequest::where('tenant_id', $tenant->id)->sole();
        expect($request->status)->toBe(DataExportStatus::Completed);
        expect($request->file_path)->toBe($filePath);
    });

    Storage::disk('backups')->assertExists($filePath);
});
