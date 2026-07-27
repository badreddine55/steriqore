import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';
import {
    addDemoCycleItem,
    completeDemoCycle,
    createDemoCycle,
    createDemoDevice,
    createDemoDluRule,
    generateDemoLabels,
    mintDemoApiToken,
    printDemoLabel,
    releaseDemoCycleAsCompliant,
    submitDemoCycleForRelease,
} from './support/demo';

/**
 * Evidence dossier + exports (UI-22). The dossier PDF is inline (same
 * pattern as the label PDF, UI-06/07) — one link is both the preview and
 * the download. Exports reuse the exact same EvidenceExport the mobile
 * API downloads.
 */
test.describe('Evidence dossier + exports (UI-22)', () => {
    test('a printed but unused label has no dossier link; after linking a procedure, the dossier opens', async ({
        page,
        request,
    }) => {
        const token = await mintDemoApiToken(request);
        const deviceId = await createDemoDevice(request, token);
        const cycleId = await createDemoCycle(request, token, deviceId);
        await addDemoCycleItem(request, token, cycleId, 'Scaler kit, pouch 1');
        await completeDemoCycle(request, token, cycleId);
        await submitDemoCycleForRelease(request, token, cycleId);
        await releaseDemoCycleAsCompliant(request, token, cycleId);
        await createDemoDluRule(request, token, 'Sachet', 'Armoire', 180);
        const [labelId] = await generateDemoLabels(
            request,
            token,
            cycleId,
            'Sachet',
            'Armoire',
        );
        await printDemoLabel(request, token, labelId);

        await loginAsDemoOwner(page);

        await page.goto(`/labels/${labelId}`);
        await expect(
            page.getByText('Point-of-care usage'),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Evidence dossier' }),
        ).not.toBeVisible();

        const patientCreate = await request.post('/api/v1/patients', {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: {},
        });
        const patient = await patientCreate.json();

        await page.getByLabel('Patient', { exact: true }).click();
        await page.keyboard.type(patient.reference);
        await page.keyboard.press('Enter');
        await page.getByLabel('Practitioner', { exact: true }).click();
        await page.keyboard.type('Admin');
        await page.keyboard.press('Enter');
        await page.getByLabel('Procedure').fill('Détartrage');
        await page
            .getByRole('button', { name: 'Link label to procedure' })
            .click();
        await expect(page.getByText('Label linked to procedure.')).toBeVisible();

        const dossierLink = page.getByRole('link', {
            name: 'Evidence dossier',
        });
        await expect(dossierLink).toBeVisible();
        const dossierHref = await dossierLink.getAttribute('href');
        expect(dossierHref).toContain(`/labels/${labelId}/usage/dossier`);

        // Headless Chromium's PDF viewer isn't reliably introspectable as
        // a popup page — verify the link actually resolves instead.
        const dossierResponse = await page.request.get(dossierHref!);
        expect(dossierResponse.ok()).toBeTruthy();
        expect(dossierResponse.headers()['content-type']).toContain(
            'application/pdf',
        );
    });

    test('evidence search exports CSV and XLSX for a user with exports.manage', async ({
        page,
    }) => {
        await loginAsDemoOwner(page);
        await page.goto('/evidence-search');
        await expect(
            page.getByRole('heading', { name: 'Evidence search' }),
        ).toBeVisible();

        const [csvDownload] = await Promise.all([
            page.waitForEvent('download'),
            page.getByRole('link', { name: 'Export CSV' }).click(),
        ]);
        expect(csvDownload.suggestedFilename()).toBe('evidence-export.csv');

        const [xlsxDownload] = await Promise.all([
            page.waitForEvent('download'),
            page.getByRole('link', { name: 'Export XLSX' }).click(),
        ]);
        expect(xlsxDownload.suggestedFilename()).toBe('evidence-export.xlsx');
    });
});
