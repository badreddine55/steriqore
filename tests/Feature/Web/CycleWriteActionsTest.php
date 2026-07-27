<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Sterilization\Models\Cycle;
use App\Domain\Sterilization\Models\CycleItem;
use App\Support\Media\Media;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner can create a cycle for an active device', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);

    $response = $this->actingAs($owner)->post('/cycles', ['device_id' => $deviceId]);

    $response->assertRedirect();
    $cycleId = Cycle::withoutGlobalScope(TenantScope::class)
        ->where('device_id', $deviceId)->firstOrFail()->id;
    expect($response->headers->get('Location'))->toContain($cycleId);
});

test('a cycle cannot be created without cycles.manage', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Practitioner->value);
    });

    $this->actingAs($owner->fresh())->post('/cycles', ['device_id' => $deviceId])->assertForbidden();
});

test('an item can be added to a draft cycle and then removed', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);

    $this->actingAs($owner)
        ->post("/cycles/{$cycleId}/items", ['description' => 'Scaler kit'])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}")
        ->assertInertia(fn (Assert $page) => $page->has('items', 1));

    $itemId = CycleItem::withoutGlobalScope(TenantScope::class)
        ->where('cycle_id', $cycleId)->firstOrFail()->id;

    $this->actingAs($owner)->delete("/cycles/{$cycleId}/items/{$itemId}")->assertRedirect();

    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}")
        ->assertInertia(fn (Assert $page) => $page->has('items', 0));
});

test('starting a cycle with an empty load is blocked with a real message, not a broken page', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);

    $response = $this->actingAs($owner)->post("/cycles/{$cycleId}/start");

    // ApiExceptionWebRenderer converts the domain's CYCLE_LOAD_EMPTY into a
    // normal redirect-back, not the API's JSON envelope or a raw 500.
    $response->assertRedirect();
    $response->assertSessionHas('_flash');

    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}")
        ->assertInertia(fn (Assert $page) => $page->where('cycle.status', 'draft'));
});

test('the full draft -> running -> completed -> awaiting_release arc works', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);

    $this->actingAs($owner)->post("/cycles/{$cycleId}/items", ['description' => 'Scaler kit'])->assertRedirect();
    $this->actingAs($owner)->post("/cycles/{$cycleId}/start")->assertRedirect();

    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}")
        ->assertInertia(fn (Assert $page) => $page->where('cycle.status', 'running'));

    $this->actingAs($owner)->post("/cycles/{$cycleId}/complete")->assertRedirect();

    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}")
        ->assertInertia(fn (Assert $page) => $page->where('cycle.status', 'completed'));

    $this->actingAs($owner)->post("/cycles/{$cycleId}/submit-for-release")->assertRedirect();

    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}")
        ->assertInertia(fn (Assert $page) => $page->where('cycle.status', 'awaiting_release'));
});

test('a file can be attached to a cycle and then removed', function () {
    Storage::fake('media');

    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);

    $file = UploadedFile::fake()->image('ticket.jpg');

    $this->actingAs($owner)
        ->post("/cycles/{$cycleId}/attachments", ['file' => $file])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}")
        ->assertInertia(fn (Assert $page) => $page->has('attachments', 1));

    $mediaId = Media::withoutGlobalScope(TenantScope::class)
        ->where('model_id', $cycleId)->firstOrFail()->id;

    $this->actingAs($owner)->delete("/cycles/{$cycleId}/attachments/{$mediaId}")->assertRedirect();

    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}")
        ->assertInertia(fn (Assert $page) => $page->has('attachments', 0));
});

test('a disallowed file type is rejected', function () {
    Storage::fake('media');

    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);

    $file = UploadedFile::fake()->create('script.exe', 10);

    $this->actingAs($owner)
        ->post("/cycles/{$cycleId}/attachments", ['file' => $file])
        ->assertSessionHasErrors('file');
});

test('a practice cannot add an item to another practice cycle (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $deviceIdB = sterilizationDeviceFor($tokenB, $tenantB);
    $cycleIdB = cycleFor($tokenB, $deviceIdB);

    $this->actingAs($ownerA)
        ->post("/cycles/{$cycleIdB}/items", ['description' => 'x'])
        ->assertNotFound();
});
