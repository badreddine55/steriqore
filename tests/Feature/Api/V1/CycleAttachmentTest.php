<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('a photo can be attached to a cycle, listed with a temporary url, and removed', function () {
    Storage::fake('media');
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = $this->withToken($token)->postJson('/api/v1/cycles', ['device_id' => $deviceId], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $file = UploadedFile::fake()->image('ticket.jpg');
    $upload = $this->withToken($token)->post("/api/v1/cycles/{$cycleId}/attachments", [
        'file' => $file,
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $upload->assertCreated()->assertJsonPath('file_name', 'ticket.jpg');
    expect($upload->json('url'))->toBeString();
    $mediaId = $upload->json('id');

    $index = $this->withToken($token)->getJson("/api/v1/cycles/{$cycleId}/attachments");
    $index->assertOk();
    expect($index->json())->toHaveCount(1);

    $this->withToken($token)->deleteJson("/api/v1/cycles/{$cycleId}/attachments/{$mediaId}")->assertNoContent();
    $this->withToken($token)->getJson("/api/v1/cycles/{$cycleId}/attachments")
        ->assertOk()
        ->assertJsonCount(0);
});

test('a practice never reaches another practice cycle attachments (IDOR)', function () {
    Storage::fake('media');
    [$tenantA, $userA] = sterilizationTenantWithMember();
    [$tenantB, $userB] = sterilizationTenantWithMember();
    $tokenA = sterilizationTokenFor($tenantA, $userA);
    $tokenB = sterilizationTokenFor($tenantB, $userB);
    $deviceBId = sterilizationDeviceFor($tokenB, $tenantB);
    $cycleBId = $this->withToken($tokenB)->postJson('/api/v1/cycles', ['device_id' => $deviceBId], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($tokenA)->getJson("/api/v1/cycles/{$cycleBId}/attachments")->assertStatus(404);
    $this->withToken($tokenA)->post("/api/v1/cycles/{$cycleBId}/attachments", [
        'file' => UploadedFile::fake()->image('x.jpg'),
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(404);
});
