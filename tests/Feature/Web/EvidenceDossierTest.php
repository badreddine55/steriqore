<?php

use App\Domain\Identity\Enums\TenantRole;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Same RenderEvidenceDossierAction/EvidenceExport the mobile API uses
 * (tests/Feature/Api/V1/EvidenceReportingTest.php) — the web-only
 * presentation (UI-22): one link on the label detail page for the
 * dossier PDF (inline, same pattern as the label PDF), export buttons
 * on evidence search for CSV/XLSX.
 */
test('the evidence dossier pdf resolves for a label with a recorded usage', function () {
    Pdf::fake();
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $chain = fullEvidenceChainFor($token, $tenant, $owner->id);

    $this->actingAs($owner)->get("/labels/{$chain['label_id']}/usage/dossier")->assertOk();
});

test('the evidence dossier 404s for a label with no recorded usage yet', function () {
    Pdf::fake();
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = generatedLabelFor($token, $tenant, $deviceId);

    $this->actingAs($owner)->get("/labels/{$labelId}/usage/dossier")->assertStatus(404);
});

test('the label detail page only offers the dossier link once a usage exists', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = generatedLabelFor($token, $tenant, $deviceId);

    $this->actingAs($owner)
        ->get("/labels/{$labelId}")
        ->assertInertia(fn (Assert $page) => $page->where('canViewDossier', false));
});

test('an owner can export evidence as xlsx or csv from the web', function () {
    Excel::fake();
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    fullEvidenceChainFor($token, $tenant, $owner->id);

    $this->actingAs($owner)->get('/evidence-search/export?format=xlsx')->assertOk();
    Excel::assertDownloaded('evidence-export.xlsx');

    $this->actingAs($owner)->get('/evidence-search/export?format=csv')->assertOk();
    Excel::assertDownloaded('evidence-export.csv');
});

test('the evidence search page only offers export buttons to a user with exports.manage', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)
        ->get('/evidence-search')
        ->assertInertia(fn (Assert $page) => $page->where('canExport', true));

    [$stockTenant, $stockManager] = sterilizationTenantWithMember(TenantRole::StockManager);

    $this->actingAs($stockManager)
        ->get('/evidence-search')
        ->assertInertia(fn (Assert $page) => $page->where('canExport', false));
});

test('a stock manager (who can view usages) cannot export them from the web — a separate permission is required', function () {
    Excel::fake();
    [$tenant, $stockManager] = sterilizationTenantWithMember(TenantRole::StockManager);

    $this->actingAs($stockManager)->get('/evidence-search/export')->assertForbidden();
});

test('a practice never reaches another practice evidence dossier, even by guessing a label id (IDOR)', function () {
    Pdf::fake();
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $chainB = fullEvidenceChainFor($tokenB, $tenantB, $ownerB->id);

    $this->actingAs($ownerA)->get("/labels/{$chainB['label_id']}/usage/dossier")->assertStatus(404);
});
