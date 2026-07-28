<?php

use App\Domain\Identity\Enums\TenantRole;
use Illuminate\Support\Str;

test('a stock manager can create, update, and delete a dlu rule', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);

    $create = $this->withToken($token)->postJson('/api/v1/dlu-rules', [
        'packaging_type' => 'Sachet papier-plastique', 'storage_condition' => 'Armoire fermée', 'shelf_life_days' => 180,
        'reason' => 'Initial rule.',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $create->assertCreated()->assertJsonPath('shelf_life_days', 180);
    $ruleId = $create->json('id');

    $this->withToken($token)->patchJson("/api/v1/dlu-rules/{$ruleId}", ['shelf_life_days' => 365, 'reason' => 'Extended per updated guidance.'])
        ->assertOk()->assertJsonPath('shelf_life_days', 365);

    $this->withToken($token)->deleteJson("/api/v1/dlu-rules/{$ruleId}")->assertNoContent();
    $this->withToken($token)->getJson('/api/v1/dlu-rules')->assertOk()->assertJsonCount(0);
});

test('creating a duplicate packaging/storage combination is rejected', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);

    $this->withToken($token)->postJson('/api/v1/dlu-rules', [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire', 'shelf_life_days' => 180,
        'reason' => 'Initial rule.',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $this->withToken($token)->postJson('/api/v1/dlu-rules', [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire', 'shelf_life_days' => 90,
        'reason' => 'Attempted duplicate.',
    ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409)->assertJsonPath('error.code', 'DLU_RULE_ALREADY_EXISTS');
});

test('a viewer can read dlu rules but cannot create one (vertical escalation)', function () {
    [$tenant, $user] = sterilizationTenantWithMember(TenantRole::Viewer);
    $token = sterilizationTokenFor($tenant, $user);

    $this->withToken($token)->getJson('/api/v1/dlu-rules')->assertOk();
    $this->withToken($token)->postJson('/api/v1/dlu-rules', [
        'packaging_type' => 'X', 'storage_condition' => 'Y', 'shelf_life_days' => 30,
        'reason' => 'Attempted create.',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);
});

test('a practice never reaches another practice dlu rule, even by guessing an id (IDOR)', function () {
    [$tenantA, $userA] = sterilizationTenantWithMember();
    [$tenantB, $userB] = sterilizationTenantWithMember();
    $tokenA = sterilizationTokenFor($tenantA, $userA);
    $tokenB = sterilizationTokenFor($tenantB, $userB);

    $foreignRuleId = $this->withToken($tokenB)->postJson('/api/v1/dlu-rules', [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire', 'shelf_life_days' => 180,
        'reason' => 'Initial rule.',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($tokenA)->patchJson("/api/v1/dlu-rules/{$foreignRuleId}", ['shelf_life_days' => 1, 'reason' => 'Attempted foreign update.'])
        ->assertStatus(404);
    $this->withToken($tokenA)->deleteJson("/api/v1/dlu-rules/{$foreignRuleId}")
        ->assertStatus(404);
});

test('creating or updating a dlu rule without a reason is rejected', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);

    $this->withToken($token)->postJson('/api/v1/dlu-rules', [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire', 'shelf_life_days' => 180,
    ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonPath('error.details.reason.0', 'The reason field is required.');

    $rule = $this->withToken($token)->postJson('/api/v1/dlu-rules', [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire', 'shelf_life_days' => 180, 'reason' => 'Initial rule.',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($token)->patchJson("/api/v1/dlu-rules/{$rule}", ['shelf_life_days' => 200])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonPath('error.details.reason.0', 'The reason field is required.');
});
