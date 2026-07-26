<?php

use App\Domain\Compliance\Models\AuditEvent;
use App\Domain\Labeling\Enums\LabelStatus;
use App\Domain\Labeling\Models\Label;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Str;

function generatedLabelFor(string $token, Tenant $tenant, string $deviceId): string
{
    [$cycleId] = releasedCompliantCycle($token, $tenant, $deviceId);
    dluRuleFor($token);

    return test()->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('0.id');
}

test('scanning an unknown code returns a clean 404, not a database error', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $manager);

    $this->withToken($token)->getJson('/api/v1/labels/not-a-real-code')
        ->assertStatus(404)->assertJsonPath('error.code', 'LABEL_NOT_FOUND');

    $this->withToken($token)->getJson('/api/v1/labels/'.Str::uuid())
        ->assertStatus(404)->assertJsonPath('error.code', 'LABEL_NOT_FOUND');
});

test('scanning a printed label resolves it and marks it used', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = generatedLabelFor($token, $tenant, $deviceId);
    $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/print", [], ['Idempotency-Key' => (string) Str::uuid()]);

    $scan = $this->withToken($token)->getJson("/api/v1/labels/urn:steriqore:label:{$labelId}");
    $scan->assertOk()
        ->assertJsonPath('label_id', $labelId)
        ->assertJsonPath('status', 'used')
        ->assertJsonPath('sequence_in_cycle', 1);

    $label = Label::withoutGlobalScope(TenantScope::class)->findOrFail($labelId);
    expect($label->status)->toBe(LabelStatus::Used);
});

test('scanning the same label again after it is used is not an error (idempotent read)', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = generatedLabelFor($token, $tenant, $deviceId);
    $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/print", [], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->withToken($token)->getJson("/api/v1/labels/{$labelId}")->assertOk()->assertJsonPath('status', 'used');
    $this->withToken($token)->getJson("/api/v1/labels/{$labelId}")->assertOk()->assertJsonPath('status', 'used');
});

test('scanning a recalled label returns 410 with a blocking code and writes an audit event', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = generatedLabelFor($token, $tenant, $deviceId);
    Label::withoutGlobalScope(TenantScope::class)->findOrFail($labelId)->update(['status' => LabelStatus::Recalled]);

    $scan = $this->withToken($token)->getJson("/api/v1/labels/{$labelId}");
    $scan->assertStatus(410)->assertJsonPath('error.code', 'LABEL_RECALLED');

    expect(
        AuditEvent::withoutGlobalScope(TenantScope::class)
            ->where('action', 'label.scan_blocked_recalled')
            ->where('subject_id', $labelId)
            ->exists()
    )->toBeTrue();
});

test('scanning a voided label returns 410 with a blocking code', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = generatedLabelFor($token, $tenant, $deviceId);
    Label::withoutGlobalScope(TenantScope::class)->findOrFail($labelId)->update(['status' => LabelStatus::Voided]);

    $this->withToken($token)->getJson("/api/v1/labels/{$labelId}")
        ->assertStatus(410)->assertJsonPath('error.code', 'LABEL_VOIDED');
});

test('scanning a label past its use-by date auto-expires it, returns 410, and writes an audit event', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = generatedLabelFor($token, $tenant, $deviceId);
    $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/print", [], ['Idempotency-Key' => (string) Str::uuid()]);
    Label::withoutGlobalScope(TenantScope::class)->findOrFail($labelId)->update(['use_by_date' => now()->subDay()]);

    $scan = $this->withToken($token)->getJson("/api/v1/labels/{$labelId}");
    $scan->assertStatus(410)->assertJsonPath('error.code', 'LABEL_EXPIRED');

    $label = Label::withoutGlobalScope(TenantScope::class)->findOrFail($labelId);
    expect($label->status)->toBe(LabelStatus::Expired);
    expect(
        AuditEvent::withoutGlobalScope(TenantScope::class)
            ->where('action', 'label.scan_blocked_expired')
            ->where('subject_id', $labelId)
            ->exists()
    )->toBeTrue();
});

test('a practice never resolves another practice label by guessing its id (IDOR)', function () {
    [$tenantA, $managerA] = sterilizationTenantWithMember();
    [$tenantB, $managerB] = sterilizationTenantWithMember();
    $tokenA = sterilizationTokenFor($tenantA, $managerA);
    $tokenB = sterilizationTokenFor($tenantB, $managerB);
    $deviceBId = sterilizationDeviceFor($tokenB, $tenantB);
    $foreignLabelId = generatedLabelFor($tokenB, $tenantB, $deviceBId);

    $this->withToken($tokenA)->getJson("/api/v1/labels/{$foreignLabelId}")
        ->assertStatus(404)->assertJsonPath('error.code', 'LABEL_NOT_FOUND');
});
