<?php

use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Mail\InvitationMail;
use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mirrors tests/Feature/Api/V1/InvitationTest.php's tenantWithActiveMember,
 * renamed to avoid a global-function clash when the whole suite loads.
 */
function teamTenantWithMember(TenantRole $role = TenantRole::Owner): array
{
    $tenant = Tenant::create(['name' => 'Cabinet Test', 'slug' => 'cabinet-'.Str::lower(Str::random(10))]);

    $user = User::create([
        'name' => 'Alice Dupont',
        'email' => 'alice-'.Str::lower(Str::random(10)).'@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);

    app(SeedTenantRolesAction::class)->execute($tenant);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $user->assignRole($role->value);
    $registrar->setPermissionsTeamId(null);

    return [$tenant, $user];
}

test('the team page lists members and invitations for an owner', function () {
    [$tenant, $owner] = teamTenantWithMember(TenantRole::Owner);

    Invitation::create([
        'tenant_id' => $tenant->id,
        'email' => 'pending@example.com',
        'role' => 'viewer',
        'token' => Str::random(64),
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($owner)
        ->get('/team')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('team/index')
            ->has('members', 1)
            ->where('members.0.email', $owner->email)
            ->has('invitations', 1)
            ->where('invitations.0.email', 'pending@example.com')
        );
});

test('an owner can invite a new member and an email is queued', function () {
    Mail::fake();
    [$tenant, $owner] = teamTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)
        ->post('/team/invitations', [
            'email' => 'new-person@example.com',
            'role' => 'practitioner',
        ])
        ->assertRedirect();

    expect(Invitation::where('email', 'new-person@example.com')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
    Mail::assertQueued(InvitationMail::class);
});

test('inviting an existing member is rejected with a flashed error, not a 500', function () {
    [, $owner] = teamTenantWithMember(TenantRole::Owner);

    $response = $this->actingAs($owner)->post('/team/invitations', [
        'email' => $owner->email,
        'role' => 'viewer',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('_flash');
});

test('an owner can revoke a pending invitation', function () {
    [$tenant, $owner] = teamTenantWithMember(TenantRole::Owner);
    $invitation = Invitation::create([
        'tenant_id' => $tenant->id,
        'email' => 'revoke-me@example.com',
        'role' => 'viewer',
        'token' => Str::random(64),
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($owner)
        ->delete("/team/invitations/{$invitation->id}")
        ->assertRedirect();

    expect($invitation->fresh()->revoked_at)->not->toBeNull();
});

test('an owner can disable another member but the membership row and identity survive (invariant #6)', function () {
    [$tenant, $owner] = teamTenantWithMember(TenantRole::Owner);
    $colleague = User::create([
        'name' => 'Bob Martin',
        'email' => 'bob-'.Str::lower(Str::random(10)).'@example.com',
        'password' => Hash::make('correct-password'),
    ]);
    $membership = TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $colleague->id, 'status' => 'active']);
    TenantContext::run($tenant, fn () => $colleague->assignRole(TenantRole::Viewer->value));

    $this->actingAs($owner)
        ->delete("/team/members/{$membership->id}")
        ->assertRedirect();

    $membership->refresh();
    expect($membership->status)->toBe('disabled');
    expect($membership->disabled_at)->not->toBeNull();
    expect(User::find($colleague->id))->not->toBeNull();
});

test('a viewer cannot see or manage the team page (vertical escalation)', function () {
    [, $viewer] = teamTenantWithMember(TenantRole::Viewer);

    $this->actingAs($viewer)->get('/team')->assertForbidden();
    $this->actingAs($viewer)->post('/team/invitations', [
        'email' => 'x@example.com', 'role' => 'viewer',
    ])->assertForbidden();
});

test('a practice never reaches another practice invitation or membership row (IDOR)', function () {
    [$tenantA, $ownerA] = teamTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = teamTenantWithMember(TenantRole::Owner);

    $foreignInvitation = Invitation::create([
        'tenant_id' => $tenantB->id,
        'email' => 'someone@example.com',
        'role' => 'viewer',
        'token' => Str::random(64),
        'invited_by' => $ownerB->id,
        'expires_at' => now()->addDays(7),
    ]);
    $foreignMembership = TenantUser::where('tenant_id', $tenantB->id)->where('user_id', $ownerB->id)->first();

    $this->actingAs($ownerA)->delete("/team/invitations/{$foreignInvitation->id}")->assertNotFound();
    $this->actingAs($ownerA)->delete("/team/members/{$foreignMembership->id}")->assertNotFound();

    expect($foreignInvitation->fresh()->revoked_at)->toBeNull();
    expect($foreignMembership->fresh()->status)->toBe('active');

    $this->actingAs($ownerA)
        ->get('/team')
        ->assertInertia(fn (Assert $page) => $page->has('invitations', 0));
});
