<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Labeling\Models\Label;
use App\Domain\Labeling\Models\LabelPrint;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Permission\PermissionRegistrar;

/**
 * @return array{0: string, 1: string} [cycleId, releaserToken]
 */
function releasedCompliantCycle(string $managerToken, Tenant $tenant, string $deviceId): array
{
    $cycleId = sterilizationRunningCycleFor($managerToken, $deviceId);
    test()->withToken($managerToken)->postJson("/api/v1/cycles/{$cycleId}/submit-for-release", [], ['Idempotency-Key' => (string) Str::uuid()]);
    [, $releaserToken] = releaserFor($tenant);
    test()->withToken($releaserToken)->postJson("/api/v1/cycles/{$cycleId}/release", [
        'decision' => 'compliant',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    return [$cycleId, $releaserToken];
}

function dluRuleFor(string $token, string $packagingType = 'Sachet', string $storageCondition = 'Armoire', int $shelfLifeDays = 180): void
{
    test()->withToken($token)->postJson('/api/v1/dlu-rules', [
        'packaging_type' => $packagingType, 'storage_condition' => $storageCondition, 'shelf_life_days' => $shelfLifeDays,
    ], ['Idempotency-Key' => (string) Str::uuid()]);
}

test('labels can be generated for a released cycle, one per item, with the DLU frozen from the matching rule', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $managerToken = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($managerToken, $tenant);
    [$cycleId] = releasedCompliantCycle($managerToken, $tenant, $deviceId);
    dluRuleFor($managerToken, 'Sachet', 'Armoire', 180);

    $generate = $this->withToken($managerToken)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $generate->assertCreated();
    expect($generate->json())->toHaveCount(1);
    expect($generate->json('0.shelf_life_days'))->toBe(180);
    expect($generate->json('0.status'))->toBe('created');
    expect($generate->json('0.urn'))->toStartWith('urn:steriqore:label:');

    $index = $this->withToken($managerToken)->getJson("/api/v1/cycles/{$cycleId}/labels");
    $index->assertOk();
    expect($index->json())->toHaveCount(1);
});

test('label generation is blocked for a cycle that is not released (rejected cycle explicit block)', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $managerToken = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($managerToken, $tenant);
    $cycleId = sterilizationRunningCycleFor($managerToken, $deviceId);
    $this->withToken($managerToken)->postJson("/api/v1/cycles/{$cycleId}/submit-for-release", [], ['Idempotency-Key' => (string) Str::uuid()]);
    [, $releaserToken] = releaserFor($tenant);
    $this->withToken($releaserToken)->postJson("/api/v1/cycles/{$cycleId}/release", [
        'decision' => 'rejected', 'reason' => 'Failed biological test',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    dluRuleFor($managerToken);

    $response = $this->withToken($managerToken)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(409)->assertJsonPath('error.code', 'CYCLE_NOT_RELEASED');
});

test('label generation fails clearly when no matching dlu rule exists', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $managerToken = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($managerToken, $tenant);
    [$cycleId] = releasedCompliantCycle($managerToken, $tenant, $deviceId);

    $response = $this->withToken($managerToken)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Unknown', 'storage_condition' => 'Unknown',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(422)->assertJsonPath('error.code', 'DLU_RULE_NOT_FOUND');
});

test('labels cannot be generated twice for the same cycle', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $managerToken = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($managerToken, $tenant);
    [$cycleId] = releasedCompliantCycle($managerToken, $tenant, $deviceId);
    dluRuleFor($managerToken);

    $this->withToken($managerToken)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $second = $this->withToken($managerToken)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $second->assertStatus(409)->assertJsonPath('error.code', 'CYCLE_LABELS_ALREADY_GENERATED');
});

test('a label can be printed the first time without a reason, and a reprint requires one and increments the counter', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $managerToken = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($managerToken, $tenant);
    [$cycleId] = releasedCompliantCycle($managerToken, $tenant, $deviceId);
    dluRuleFor($managerToken);
    $labelId = $this->withToken($managerToken)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('0.id');

    $first = $this->withToken($managerToken)->postJson("/api/v1/labels/{$labelId}/print", [], ['Idempotency-Key' => (string) Str::uuid()]);
    $first->assertCreated()->assertJsonPath('status', 'printed');

    $missingReason = $this->withToken($managerToken)->postJson("/api/v1/labels/{$labelId}/print", [], ['Idempotency-Key' => (string) Str::uuid()]);
    $missingReason->assertStatus(422);

    $reprint = $this->withToken($managerToken)->postJson("/api/v1/labels/{$labelId}/print", [
        'reason' => 'Original label was damaged.',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $reprint->assertCreated();

    $label = Label::withoutGlobalScope(TenantScope::class)->findOrFail($labelId);
    expect($label->print_counter)->toBe(2);
    expect(LabelPrint::withoutGlobalScope(TenantScope::class)->where('label_id', $labelId)->count())->toBe(2);
});

test('a viewer can view labels but cannot print one (vertical escalation)', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $managerToken = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($managerToken, $tenant);
    [$cycleId] = releasedCompliantCycle($managerToken, $tenant, $deviceId);
    dluRuleFor($managerToken);
    $labelId = $this->withToken($managerToken)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('0.id');

    $viewer = User::create(['name' => 'V', 'email' => 'v-'.Str::lower(Str::random(8)).'@example.com', 'password' => Hash::make('correct-password')]);
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $viewer->id, 'status' => 'active']);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $viewer->assignRole(TenantRole::Viewer->value);
    $registrar->setPermissionsTeamId(null);
    $viewerToken = sterilizationTokenFor($tenant, $viewer);

    $this->withToken($viewerToken)->getJson("/api/v1/labels/{$labelId}/qr-code")->assertOk();
    $this->withToken($viewerToken)->postJson("/api/v1/labels/{$labelId}/print", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(403);
});

test('the qr-code, datamatrix, and pdf endpoints all resolve for a generated label', function () {
    // Real Chromium rendering is verified manually in the Docker image
    // (where Node/puppeteer actually live) — Pdf::fake() keeps this test
    // environment-independent, same idea as Mail::fake()/Http::fake().
    Pdf::fake();

    [$tenant, $manager] = sterilizationTenantWithMember();
    $managerToken = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($managerToken, $tenant);
    [$cycleId] = releasedCompliantCycle($managerToken, $tenant, $deviceId);
    dluRuleFor($managerToken);
    $labelId = $this->withToken($managerToken)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('0.id');

    $this->withToken($managerToken)->getJson("/api/v1/labels/{$labelId}/qr-code")
        ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    $this->withToken($managerToken)->getJson("/api/v1/labels/{$labelId}/datamatrix")
        ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

    $this->withToken($managerToken)->get("/api/v1/labels/{$labelId}/pdf")->assertOk();
});

test('a practice never reaches another practice label (IDOR)', function () {
    [$tenantA, $managerA] = sterilizationTenantWithMember();
    [$tenantB, $managerB] = sterilizationTenantWithMember();
    $tokenA = sterilizationTokenFor($tenantA, $managerA);
    $tokenB = sterilizationTokenFor($tenantB, $managerB);
    $deviceBId = sterilizationDeviceFor($tokenB, $tenantB);
    [$cycleBId] = releasedCompliantCycle($tokenB, $tenantB, $deviceBId);
    dluRuleFor($tokenB);
    $foreignLabelId = $this->withToken($tokenB)->postJson("/api/v1/cycles/{$cycleBId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('0.id');

    $this->withToken($tokenA)->getJson("/api/v1/labels/{$foreignLabelId}/qr-code")->assertStatus(404);
    $this->withToken($tokenA)->postJson("/api/v1/labels/{$foreignLabelId}/print", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(404);
});
