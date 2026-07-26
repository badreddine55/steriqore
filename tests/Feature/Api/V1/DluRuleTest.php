<?php

use App\Domain\Identity\Enums\TenantRole;
use Illuminate\Support\Str;

test('a stock manager can create, update, and delete a dlu rule', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);

    $create = $this->withToken($token)->postJson('/api/v1/dlu-rules', [
        'packaging_type' => 'Sachet papier-plastique', 'storage_condition' => 'Armoire fermée', 'shelf_life_days' => 180,
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $create->assertCreated()->assertJsonPath('shelf_life_days', 180);
    $ruleId = $create->json('id');

    $this->withToken($token)->patchJson("/api/v1/dlu-rules/{$ruleId}", ['shelf_life_days' => 365])
        ->assertOk()->assertJsonPath('shelf_life_days', 365);

    $this->withToken($token)->deleteJson("/api/v1/dlu-rules/{$ruleId}")->assertNoContent();
    $this->withToken($token)->getJson('/api/v1/dlu-rules')->assertOk()->assertJsonCount(0);
});

test('creating a duplicate packaging/storage combination is rejected', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);

    $this->withToken($token)->postJson('/api/v1/dlu-rules', [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire', 'shelf_life_days' => 180,
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $this->withToken($token)->postJson('/api/v1/dlu-rules', [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire', 'shelf_life_days' => 90,
    ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409)->assertJsonPath('error.code', 'DLU_RULE_ALREADY_EXISTS');
});

test('a viewer can read dlu rules but cannot create one (vertical escalation)', function () {
    [$tenant, $user] = sterilizationTenantWithMember(TenantRole::Viewer);
    $token = sterilizationTokenFor($tenant, $user);

    $this->withToken($token)->getJson('/api/v1/dlu-rules')->assertOk();
    $this->withToken($token)->postJson('/api/v1/dlu-rules', [
        'packaging_type' => 'X', 'storage_condition' => 'Y', 'shelf_life_days' => 30,
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);
});
