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
 * The web fallback for the mobile scan (UI-20) — a desk without a camera
 * links a PRINTED (not yet scanned) label straight from its detail page.
 * Submitting the form performs the scan resolution itself (same
 * ResolveLabelScanAction a real scan runs), so the label goes
 * printed -> used in the same request that records the usage.
 */
test.describe('Label usages — web fallback for the scan (UI-20)', () => {
    test('link a printed label to a patient and procedure directly from its detail page', async ({
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

        await page.getByLabel('Patient', { exact: true }).click();
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('Enter');
        await page.getByLabel('Practitioner', { exact: true }).click();
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('Enter');
        const procedure = `E2E Procedure ${Date.now()}`;
        await page.getByLabel('Procedure').fill(procedure);
        await page
            .getByRole('button', { name: 'Link label to procedure' })
            .click();

        await expect(page.getByText('Label linked to procedure.')).toBeVisible();
        await expect(page.getByText(procedure)).toBeVisible();
        await expect(page.getByText('Used', { exact: true }).first()).toBeVisible();
    });
});
