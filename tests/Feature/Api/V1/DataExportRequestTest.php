<?php

use App\Domain\Compliance\Models\AuditEvent;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Reporting\Jobs\GenerateDataExportJob;
use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

test('an owner can request a full data export, which dispatches the generation job', function () {
    Queue::fake();
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);

    $response = $this->withToken($token)->postJson('/api/v1/data-export-requests', [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ]);

    $response->assertCreated()->assertJsonPath('status', 'pending');
    $requestId = $response->json('id');

    Queue::assertPushed(GenerateDataExportJob::class, fn ($job) => $job->dataExportRequestId === $requestId);

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
        ->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('download_url', null);
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
