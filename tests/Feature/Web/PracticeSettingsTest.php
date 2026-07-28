<?php

use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Inventory\Models\AlertSettings;
use App\Domain\Inventory\Models\DigestSubscription;
use App\Domain\Labeling\Actions\CreateDluRuleAction;
use App\Domain\Labeling\Models\DluRule;
use App\Domain\Labeling\Models\DluRuleVersion;
use App\Domain\Labeling\Models\LabelFormatVersion;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mirrors tests/Feature/Api/V1/CycleTest.php's sterilizationTenantWithMember,
 * renamed to avoid a global-function clash when the whole suite loads.
 */
function practiceSettingsTenantWithMember(TenantRole $role = TenantRole::Owner): array
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

test('an owner can view the settings page with defaults for every section', function () {
    [, $owner] = practiceSettingsTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)
        ->get('/practice-settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/practice-settings/index')
            ->where('identity.name', 'Cabinet Test')
            ->where('labelFormat', null)
            ->has('dluRules', 0)
            ->where('alertSettings.near_expiry_days', 30)
            ->has('digestSubscriptions', 6)
            ->where('canManageEvidence', true)
        );
});

test('an owner can update the practice identity with a reason, audited', function () {
    [$tenant, $owner] = practiceSettingsTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)
        ->patch('/practice-settings/identity', [
            'name' => 'Cabinet Test',
            'legal_name' => 'Cabinet Test SARL',
            'city' => 'Lyon',
            'timezone' => 'Europe/Paris',
            'locale' => 'fr',
            'reason' => 'Correcting the legal name.',
        ])
        ->assertRedirect();

    expect($tenant->fresh()->legal_name)->toBe('Cabinet Test SARL');
});

test('updating the identity without a reason is rejected', function () {
    [, $owner] = practiceSettingsTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)
        ->patch('/practice-settings/identity', ['name' => 'New Name'])
        ->assertSessionHasErrors('reason');
});

test('an owner can upload a logo', function () {
    [$tenant, $owner] = practiceSettingsTenantWithMember(TenantRole::Owner);
    Storage::fake('media');

    $this->actingAs($owner)
        ->post('/practice-settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png'),
            'reason' => 'New branding.',
        ])
        ->assertRedirect();

    // App\Support\Media\Media is itself BelongsToTenant (RLS + a global
    // scope) — reading it back outside the request needs the same tenant
    // context the request had, exactly like resolving an archived Site
    // (UI-27) does.
    TenantContext::run($tenant, function () use ($tenant) {
        expect($tenant->fresh()->getFirstMedia('logo'))->not->toBeNull();
    });
});

test('an owner can add and update a dlu rule, versioned with a required reason', function () {
    [$tenant, $owner] = practiceSettingsTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)
        ->post('/practice-settings/dlu-rules', [
            'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire', 'shelf_life_days' => 180,
            'reason' => 'EN 868 starting point for pouches.',
        ])
        ->assertRedirect();

    $rule = TenantContext::run($tenant, fn () => DluRule::firstOrFail());
    expect($rule->shelf_life_days)->toBe(180);
    expect($rule->current_version_id)->not->toBeNull();

    $this->actingAs($owner)
        ->patch("/practice-settings/dlu-rules/{$rule->id}", [
            'shelf_life_days' => 365,
            'reason' => 'Extended after review.',
        ])
        ->assertRedirect();

    // The old version is never overwritten — it's a new, immutable row.
    TenantContext::run($tenant, function () use ($rule) {
        expect($rule->fresh()->shelf_life_days)->toBe(365);
        expect(DluRuleVersion::where('tenant_id', $rule->tenant_id)->count())->toBe(2);
    });
});

test('creating a dlu rule without a reason is rejected', function () {
    [, $owner] = practiceSettingsTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)
        ->post('/practice-settings/dlu-rules', [
            'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire', 'shelf_life_days' => 180,
        ])
        ->assertSessionHasErrors('reason');
});

test('an owner can update the label format, versioned, applying to future prints only', function () {
    [$tenant, $owner] = practiceSettingsTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)
        ->post('/practice-settings/label-format', [
            'primary_code' => 'qr',
            'sheet_layout' => 'a4_65',
            'show_batch_number' => true,
            'show_product_name' => false,
            'reason' => 'Practice prefers QR as primary.',
        ])
        ->assertRedirect();

    $version = TenantContext::run($tenant, fn () => LabelFormatVersion::where('tenant_id', $tenant->id)->latest('effective_from')->first());
    expect($version->primary_code->value)->toBe('qr');
    expect($version->sheet_layout->value)->toBe('a4_65');
});

test('the test-print sample sheet resolves without touching any real label', function () {
    Pdf::fake();
    [, $owner] = practiceSettingsTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)->get('/practice-settings/label-format/sample?'.http_build_query([
        'primary_code' => 'datamatrix',
        'sheet_layout' => 'a4_24',
        'show_batch_number' => '1',
        'show_product_name' => '0',
    ]))->assertOk();
});

test('an owner can update alert settings with a reason, and preview near-expiry impact', function () {
    [$tenant, $owner] = practiceSettingsTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)
        ->patch('/practice-settings/alerts', [
            'near_expiry_days' => 45,
            'reason' => 'Practice wants more lead time.',
        ])
        ->assertRedirect();

    TenantContext::run($tenant, function () use ($tenant) {
        expect(AlertSettings::forTenant($tenant)->near_expiry_days)->toBe(45);
    });

    $this->actingAs($owner)
        ->postJson('/practice-settings/alerts/preview-near-expiry', ['near_expiry_days' => 60])
        ->assertOk()
        ->assertJsonStructure(['appearing', 'disappearing']);
});

test('an owner can update digest subscriptions', function () {
    [$tenant, $owner] = practiceSettingsTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)
        ->put('/practice-settings/digest-subscriptions', [
            'subscriptions' => [
                ['role' => 'owner', 'frequency' => 'weekly', 'enabled' => true],
                ['role' => 'admin', 'frequency' => 'daily', 'enabled' => false],
            ],
            'reason' => 'Owner wants a weekly summary.',
        ])
        ->assertRedirect();

    $subscription = TenantContext::run($tenant, fn () => DigestSubscription::where('tenant_id', $tenant->id)->where('role', 'owner')->first());
    expect($subscription->frequency)->toBe('weekly');
});

test('an admin can manage identity and alerts but not label format or dlu rules', function () {
    [$tenant, $admin] = practiceSettingsTenantWithMember(TenantRole::Admin);

    $this->actingAs($admin)
        ->get('/practice-settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManageEvidence', false));

    $this->actingAs($admin)
        ->patch('/practice-settings/identity', ['name' => 'New Name', 'reason' => 'Rename.'])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post('/practice-settings/dlu-rules', [
            'packaging_type' => 'X', 'storage_condition' => 'Y', 'shelf_life_days' => 30, 'reason' => 'Attempt.',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->post('/practice-settings/label-format', [
            'primary_code' => 'qr', 'sheet_layout' => 'a4_24', 'reason' => 'Attempt.',
        ])
        ->assertForbidden();
});

test('a viewer cannot reach the settings page at all', function () {
    [, $viewer] = practiceSettingsTenantWithMember(TenantRole::Viewer);

    $this->actingAs($viewer)->get('/practice-settings')->assertForbidden();
});

test('a practice never reaches or writes another practice dlu rule from the settings page (IDOR)', function () {
    [, $ownerA] = practiceSettingsTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = practiceSettingsTenantWithMember(TenantRole::Owner);

    $ruleB = TenantContext::run($tenantB, function () use ($tenantB, $ownerB) {
        return app(CreateDluRuleAction::class)->execute(
            $tenantB, $ownerB, ['packaging_type' => 'Sachet', 'storage_condition' => 'Armoire', 'shelf_life_days' => 180], 'Setup.',
        );
    });

    $this->actingAs($ownerA)
        ->patch("/practice-settings/dlu-rules/{$ruleB->id}", ['shelf_life_days' => 1, 'reason' => 'Attempted foreign update.'])
        ->assertNotFound();
});

test('an unauthenticated visitor is redirected to login', function () {
    $this->get('/practice-settings')->assertRedirect('/login');
});
