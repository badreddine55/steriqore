<?php

use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Reporting\Enums\DataExportStatus;
use App\Domain\Reporting\Models\DataExportRequest;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mirrors tests/Feature/Api/V1/DataExportRequestTest.php's
 * sterilizationTenantWithMember, renamed to avoid a global-function clash
 * when the whole suite loads.
 */
function dataExportTenantWithMember(TenantRole $role = TenantRole::Owner): array
{
    $tenant = Tenant::create(['name' => 'Cabinet Test', 'slug' => 'cabinet-'.Str::lower(Str::random(10))]);

    $user = User::create([
        'name' => 'Alice Dupont',
        'email' => 'alice-'.Str::lower(Str::random(10)).'@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);

    app(SeedTenantRolesAction::class)->execute($tenant);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $user->assignRole($role->value);
    $registrar->setPermissionsTeamId(null);

    return [$tenant, $user];
}

test('an owner can view the data export history page', function () {
    [$tenant, $owner] = dataExportTenantWithMember(TenantRole::Owner);

    TenantContext::run($tenant, fn () => DataExportRequest::create([
        'tenant_id' => $tenant->id,
        'requested_by_user_id' => $owner->id,
        'status' => DataExportStatus::Completed,
    ]));

    $this->actingAs($owner)
        ->get('/data-exports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reporting/data-exports/index')
            ->has('exports.data', 1)
            ->where('hasRunningExport', false)
            ->where('weeklyLimit', 5)
        );
});

test('an owner can request an export, which is emailed to owners and audited', function () {
    Queue::fake();
    Mail::fake();
    [$tenant, $owner] = dataExportTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)->post('/data-exports')->assertRedirect();

    expect(
        TenantContext::run($tenant, fn () => DataExportRequest::where('tenant_id', $tenant->id)->count())
    )->toBe(1);
});

test('requesting an export while one is already running is blocked with a real message, not a broken page', function () {
    [$tenant, $owner] = dataExportTenantWithMember(TenantRole::Owner);

    TenantContext::run($tenant, fn () => DataExportRequest::create([
        'tenant_id' => $tenant->id,
        'requested_by_user_id' => $owner->id,
        'status' => DataExportStatus::Processing,
    ]));

    $response = $this->actingAs($owner)->post('/data-exports');

    // ApiExceptionWebRenderer converts the domain's EXPORT_ALREADY_RUNNING
    // into a normal redirect-back, not a raw 500.
    $response->assertRedirect();
    $response->assertSessionHas('_flash');
});

test('a practitioner (not owner/admin) cannot reach the data export page', function () {
    [, $practitioner] = dataExportTenantWithMember(TenantRole::Practitioner);

    $this->actingAs($practitioner)->get('/data-exports')->assertStatus(403);
    $this->actingAs($practitioner)->post('/data-exports')->assertStatus(403);
});

test('a completed export can be downloaded from the web UI, minting a fresh URL', function () {
    Storage::fake('backups');
    [$tenant, $owner] = dataExportTenantWithMember(TenantRole::Owner);

    $requestId = TenantContext::run($tenant, fn () => DataExportRequest::create([
        'tenant_id' => $tenant->id,
        'requested_by_user_id' => $owner->id,
        'status' => DataExportStatus::Completed,
        'file_path' => "data-exports/{$tenant->id}/export.zip",
        'expires_at' => now()->addDays(7),
    ])->id);

    $this->actingAs($owner)
        ->postJson("/data-exports/{$requestId}/download")
        ->assertOk()
        ->assertJsonStructure(['download_url', 'url_expires_at']);
});

test('a practice never reaches another practice export from the web UI (IDOR)', function () {
    [$tenantA, $ownerA] = dataExportTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = dataExportTenantWithMember(TenantRole::Owner);

    $requestIdB = TenantContext::run($tenantB, fn () => DataExportRequest::create([
        'tenant_id' => $tenantB->id,
        'requested_by_user_id' => $ownerB->id,
        'status' => DataExportStatus::Completed,
        'file_path' => "data-exports/{$tenantB->id}/export.zip",
        'expires_at' => now()->addDays(7),
    ])->id);

    $this->actingAs($ownerA)->postJson("/data-exports/{$requestIdB}/download")->assertStatus(404);
});
