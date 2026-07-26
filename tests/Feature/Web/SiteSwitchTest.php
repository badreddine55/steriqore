<?php

use App\Domain\Identity\Enums\TenantRole;

test('switching to a site belonging to the current tenant is persisted in the session', function () {
    [$tenant, $user] = sterilizationTenantWithMember(TenantRole::Owner);
    $siteId = $tenant->sites()->create(['name' => 'Cabinet Principal'])->id;

    $this->actingAs($user)->post('/site/switch', ['site_id' => $siteId])
        ->assertRedirect();

    expect(session('current_site_id'))->toBe($siteId);
});

test('a site belonging to another tenant cannot be switched to', function () {
    [, $user] = sterilizationTenantWithMember(TenantRole::Owner);
    [$otherTenant] = sterilizationTenantWithMember(TenantRole::Owner);
    $otherSiteId = $otherTenant->sites()->create(['name' => 'Other'])->id;

    $this->actingAs($user)->post('/site/switch', ['site_id' => $otherSiteId])
        ->assertSessionHasErrors('site_id');
});
