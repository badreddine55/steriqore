<?php

use App\Domain\Identity\Enums\TenantRole;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;

test('the evidence dossier pdf resolves for a label with a recorded usage', function () {
    Pdf::fake();
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    $chain = fullEvidenceChainFor($token, $tenant, $manager->id);

    $this->withToken($token)->get("/api/v1/labels/{$chain['label_id']}/usage/dossier")->assertOk();
});

test('the evidence dossier 404s for a label with no recorded usage yet', function () {
    Pdf::fake();
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = generatedLabelFor($token, $tenant, $deviceId);

    $this->withToken($token)->get("/api/v1/labels/{$labelId}/usage/dossier")->assertStatus(404);
});

test('an owner can export evidence as xlsx or csv', function () {
    Excel::fake();
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    fullEvidenceChainFor($token, $tenant, $manager->id);

    $this->withToken($token)->get('/api/v1/evidence-search/export?format=xlsx')->assertOk();
    Excel::assertDownloaded('evidence-export.xlsx');

    $this->withToken($token)->get('/api/v1/evidence-search/export?format=csv')->assertOk();
    Excel::assertDownloaded('evidence-export.csv');
});

test('a stock manager (who can view usages) cannot export them — a separate permission is required', function () {
    Excel::fake();
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::StockManager);
    $token = sterilizationTokenFor($tenant, $manager);

    $this->withToken($token)->get('/api/v1/evidence-search/export')->assertStatus(403);
});

test('a practice never reaches another practice evidence dossier, even by guessing a label id (IDOR)', function () {
    Pdf::fake();
    [$tenantA, $managerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $managerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenA = sterilizationTokenFor($tenantA, $managerA);
    $tokenB = sterilizationTokenFor($tenantB, $managerB);
    $chainB = fullEvidenceChainFor($tokenB, $tenantB, $managerB->id);

    $this->withToken($tokenA)->get("/api/v1/labels/{$chainB['label_id']}/usage/dossier")->assertStatus(404);
});
