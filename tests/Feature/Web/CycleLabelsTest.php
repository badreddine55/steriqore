<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Labeling\Models\Label;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;

test('the label sheet shows labels already generated for a released cycle', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    [$cycleId] = releasedCompliantCycle($token, $tenant, $deviceId);
    dluRuleFor($token, 'Sachet', 'Armoire', 180);
    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}/labels")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sterilization/cycles/labels')
            ->has('labels', 1)
            ->where('labels.0.status', 'created')
            ->where('labels.0.shelf_life_days', 180)
        );
});

test('labels can be generated from the web label sheet page', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    [$cycleId] = releasedCompliantCycle($token, $tenant, $deviceId);
    dluRuleFor($token, 'Sachet', 'Armoire', 180);

    $this->actingAs($owner)->post("/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet',
        'storage_condition' => 'Armoire',
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}/labels")
        ->assertInertia(fn (Assert $page) => $page->has('labels', 1));
});

test('generating labels without a matching DLU rule fails clearly, not with a crash', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    [$cycleId] = releasedCompliantCycle($token, $tenant, $deviceId);

    $response = $this->actingAs($owner)->post("/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Unknown',
        'storage_condition' => 'Unknown',
    ]);

    // ApiExceptionWebRenderer converts DLU_RULE_NOT_FOUND into a normal
    // redirect-back, not the API's JSON envelope or a raw 500.
    $response->assertRedirect();
    $response->assertSessionHas('_flash');
});

test('a label can be printed the first time without a reason, and a reprint requires one', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    [$cycleId] = releasedCompliantCycle($token, $tenant, $deviceId);
    dluRuleFor($token);
    $labelId = $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('0.id');

    $this->actingAs($owner)->post("/labels/{$labelId}/print")->assertRedirect();

    $missingReason = $this->actingAs($owner)->post("/labels/{$labelId}/print");
    $missingReason->assertSessionHasErrors('reason');

    $this->actingAs($owner)->post("/labels/{$labelId}/print", [
        'reason' => 'Original label was damaged.',
    ])->assertRedirect();

    $label = Label::withoutGlobalScope(TenantScope::class)->findOrFail($labelId);
    expect($label->print_counter)->toBe(2);
});

test('a viewer cannot print a label', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    [$cycleId] = releasedCompliantCycle($token, $tenant, $deviceId);
    dluRuleFor($token);
    $labelId = $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('0.id');

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });

    $this->actingAs($owner->fresh())->post("/labels/{$labelId}/print")->assertForbidden();
});

test('the qr-code, datamatrix, and pdf routes all resolve for a generated label', function () {
    Pdf::fake();

    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    [$cycleId] = releasedCompliantCycle($token, $tenant, $deviceId);
    dluRuleFor($token);
    $labelId = $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('0.id');

    $this->actingAs($owner)->get("/labels/{$labelId}/qr-code")
        ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    $this->actingAs($owner)->get("/labels/{$labelId}/datamatrix")
        ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    $this->actingAs($owner)->get("/labels/{$labelId}/pdf")->assertOk();
});

test('a practice cannot view another practice cycle label sheet (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $deviceIdB = sterilizationDeviceFor($tokenB, $tenantB);
    [$cycleIdB] = releasedCompliantCycle($tokenB, $tenantB, $deviceIdB);

    $this->actingAs($ownerA)->get("/cycles/{$cycleIdB}/labels")->assertNotFound();
});

test('a practice cannot print another practice label (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $deviceIdB = sterilizationDeviceFor($tokenB, $tenantB);
    [$cycleIdB] = releasedCompliantCycle($tokenB, $tenantB, $deviceIdB);
    dluRuleFor($tokenB);
    $labelIdB = $this->withToken($tokenB)->postJson("/api/v1/cycles/{$cycleIdB}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('0.id');

    $this->actingAs($ownerA)->post("/labels/{$labelIdB}/print")->assertNotFound();
});
