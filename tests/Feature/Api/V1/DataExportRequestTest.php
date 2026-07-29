<?php

use App\Domain\Compliance\Models\AuditEvent;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Reporting\Actions\RequestDataExportAction;
use App\Domain\Reporting\Enums\DataExportStatus;
use App\Domain\Reporting\Jobs\GenerateDataExportJob;
use App\Domain\Reporting\Mail\DataExportRequestedMail;
use App\Domain\Reporting\Models\DataExportRequest;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('an owner can request a full data export, which dispatches the generation job and notifies owners', function () {
    Queue::fake();
    Mail::fake();
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);

    $response = $this->withToken($token)->postJson('/api/v1/data-export-requests', [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ]);

    $response->assertCreated()->assertJsonPath('status', 'pending');
    $requestId = $response->json('id');

    Queue::assertPushed(GenerateDataExportJob::class, fn ($job) => $job->dataExportRequestId === $requestId);

    // Out-of-band control: the requester (an owner) is notified too, not
    // just some other admin — a compromised owner account must still leave
    // a visible trace.
    Mail::assertQueued(DataExportRequestedMail::class, fn ($mail) => $mail->recipient->is($owner));

    expect(
        AuditEvent::withoutGlobalScope(TenantScope::class)
            ->where('action', 'data_export.requested')
            ->where('subject_id', $requestId)
            ->exists()
    )->toBeTrue();
});

test('a request status can be read back once the job updates it', function () {
    Queue::fake();
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);

    $requestId = $this->withToken($token)->postJson('/api/v1/data-export-requests', [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->json('id');

    $this->withToken($token)->getJson("/api/v1/data-export-requests/{$requestId}")
        ->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('requested_by_name', $owner->name);
});

test('a practitioner (not owner/admin) cannot request a full account data export', function () {
    [$tenant, $practitioner] = sterilizationTenantWithMember(TenantRole::Practitioner);
    $token = sterilizationTokenFor($tenant, $practitioner);

    $this->withToken($token)->postJson('/api/v1/data-export-requests', [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertStatus(403);
});

test('a practice never reaches another practice export request (IDOR)', function () {
    Queue::fake();
    [$tenantA, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenA = sterilizationTokenFor($tenantA, $ownerA);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);

    $requestIdB = $this->withToken($tokenB)->postJson('/api/v1/data-export-requests', [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->json('id');

    $this->withToken($tokenA)->getJson("/api/v1/data-export-requests/{$requestIdB}")->assertStatus(404);
});

test('only one export can be in flight per tenant at a time', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);

    TenantContext::run($tenant, fn () => DataExportRequest::create([
        'tenant_id' => $tenant->id,
        'requested_by_user_id' => $owner->id,
        'status' => DataExportStatus::Processing,
    ]));

    $this->withToken($token)->postJson('/api/v1/data-export-requests', [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertStatus(409)->assertJsonPath('error.code', 'EXPORT_ALREADY_RUNNING');
});

test('no more than the weekly cap of exports can be requested per tenant', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);

    TenantContext::run($tenant, function () use ($tenant, $owner) {
        for ($i = 0; $i < RequestDataExportAction::WEEKLY_LIMIT; $i++) {
            DataExportRequest::create([
                'tenant_id' => $tenant->id,
                'requested_by_user_id' => $owner->id,
                'status' => DataExportStatus::Completed,
            ]);
        }
    });

    $this->withToken($token)->postJson('/api/v1/data-export-requests', [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertStatus(429)->assertJsonPath('error.code', 'EXPORT_WEEKLY_LIMIT_REACHED');
});

test('the export history can be listed, newest first', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);

    TenantContext::run($tenant, function () use ($tenant, $owner) {
        DataExportRequest::create(['tenant_id' => $tenant->id, 'requested_by_user_id' => $owner->id, 'status' => DataExportStatus::Completed]);
        DataExportRequest::create(['tenant_id' => $tenant->id, 'requested_by_user_id' => $owner->id, 'status' => DataExportStatus::Failed]);
    });

    $this->withToken($token)->getJson('/api/v1/data-export-requests')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('a completed export can be downloaded, minting a fresh URL and auditing the click', function () {
    Storage::fake('backups');
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);

    $requestId = TenantContext::run($tenant, fn () => DataExportRequest::create([
        'tenant_id' => $tenant->id,
        'requested_by_user_id' => $owner->id,
        'status' => DataExportStatus::Completed,
        'file_path' => "data-exports/{$tenant->id}/export.zip",
        'expires_at' => now()->addDays(7),
    ])->id);

    $this->withToken($token)->postJson("/api/v1/data-export-requests/{$requestId}/download")
        ->assertOk()
        ->assertJsonStructure(['download_url', 'url_expires_at']);

    expect(
        AuditEvent::withoutGlobalScope(TenantScope::class)
            ->where('action', 'data_export.downloaded')
            ->where('subject_id', $requestId)
            ->exists()
    )->toBeTrue();
});

test('downloading an export that is not ready yet is blocked', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);

    $requestId = TenantContext::run($tenant, fn () => DataExportRequest::create([
        'tenant_id' => $tenant->id,
        'requested_by_user_id' => $owner->id,
        'status' => DataExportStatus::Pending,
    ])->id);

    $this->withToken($token)->postJson("/api/v1/data-export-requests/{$requestId}/download")
        ->assertStatus(409)->assertJsonPath('error.code', 'EXPORT_NOT_READY');
});

test('downloading an expired export is blocked', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);

    $requestId = TenantContext::run($tenant, fn () => DataExportRequest::create([
        'tenant_id' => $tenant->id,
        'requested_by_user_id' => $owner->id,
        'status' => DataExportStatus::Completed,
        'file_path' => "data-exports/{$tenant->id}/export.zip",
        'expires_at' => now()->subMinute(),
    ])->id);

    $this->withToken($token)->postJson("/api/v1/data-export-requests/{$requestId}/download")
        ->assertStatus(410)->assertJsonPath('error.code', 'EXPORT_EXPIRED');
});

test('a practice never downloads another practice export (IDOR)', function () {
    Storage::fake('backups');
    [$tenantA, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenA = sterilizationTokenFor($tenantA, $ownerA);

    $requestIdB = TenantContext::run($tenantB, fn () => DataExportRequest::create([
        'tenant_id' => $tenantB->id,
        'requested_by_user_id' => $ownerB->id,
        'status' => DataExportStatus::Completed,
        'file_path' => "data-exports/{$tenantB->id}/export.zip",
        'expires_at' => now()->addDays(7),
    ])->id);

    $this->withToken($tokenA)->postJson("/api/v1/data-export-requests/{$requestIdB}/download")->assertStatus(404);
});

/**
 * The job's success path (dumpTables/copyMediaFiles) deliberately reads
 * through the 'pgsql_admin' connection — a genuinely separate DB session
 * from the default connection RefreshDatabase wraps in an uncommitted,
 * rolled-back transaction per test (see TenantIsolationTest's docblock:
 * "a genuinely separate connection could never see the fixture rows
 * anyway"). So the full round-trip can't be exercised under Pest the way
 * it is for every other feature here — it's verified manually against the
 * real running container instead, same as the S5-ANA-03 backup/restore
 * proof and Browsershot PDF rendering.
 */
test('the job fails loudly for a request id that does not exist rather than silently no-op', function () {
    expect(fn () => (new GenerateDataExportJob((string) Str::uuid()))->handle())
        ->toThrow(ModelNotFoundException::class);
});
