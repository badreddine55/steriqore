<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use Spatie\Permission\PermissionRegistrar;

test('a member of two practices can switch which one is active for their session', function () {
    [$tenantA, $user] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB] = sterilizationTenantWithMember(TenantRole::Owner);

    TenantUser::create(['tenant_id' => $tenantB->id, 'user_id' => $user->id, 'status' => 'active']);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenantB->id);
    $user->assignRole(TenantRole::Viewer->value);
    $registrar->setPermissionsTeamId(null);

    $this->actingAs($user)->get('/cycles')
        ->assertInertia(fn ($page) => $page->where('tenant.id', $tenantA->id));

    $this->actingAs($user)->post('/tenant/switch', ['tenant_id' => $tenantB->id])
        ->assertRedirect(route('dashboard'));

    // A fresh model instance, not the same $user object reused above:
    // Spatie's HasRoles caches the loaded roles/permissions relations on
    // the model instance itself, and since this is one PHP process (not a
    // new one per simulated request, unlike a real request), that cache
    // would otherwise survive the tenant switch and still report tenantA's
    // role here — a test-simulation artifact, not a real request's
    // behavior (every real request resolves $request->user() fresh).
    $this->actingAs($user->fresh())->get('/cycles')
        ->assertInertia(fn ($page) => $page->where('tenant.id', $tenantB->id)->where('auth.role', 'viewer'));
});

test('a user cannot switch into a practice they are not an active member of', function () {
    [, $user] = sterilizationTenantWithMember(TenantRole::Owner);
    [$otherTenant] = sterilizationTenantWithMember(TenantRole::Owner);

    $this->actingAs($user)->post('/tenant/switch', ['tenant_id' => $otherTenant->id])
        ->assertSessionHasErrors('tenant_id');
});

test('a disabled membership cannot be switched into', function () {
    [$tenantA, $user] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB] = sterilizationTenantWithMember(TenantRole::Owner);

    TenantUser::create(['tenant_id' => $tenantB->id, 'user_id' => $user->id, 'status' => 'disabled']);

    $this->actingAs($user)->post('/tenant/switch', ['tenant_id' => $tenantB->id])
        ->assertSessionHasErrors('tenant_id');

    $this->actingAs($user)->get('/cycles')
        ->assertInertia(fn ($page) => $page->where('tenant.id', $tenantA->id));
});
