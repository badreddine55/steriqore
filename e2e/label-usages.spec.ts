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
        // Created explicitly rather than relying on another spec having
        // left one behind — this spec must be able to run standalone
        // against a genuinely fresh demo tenant.
        const patientCreate = await request.post('/api/v1/patients', {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: {},
        });
        const patientReference = (await patientCreate.json())
            .reference as string;

        await loginAsDemoOwner(page);

        await page.goto(`/labels/${labelId}`);
        await expect(
            page.getByText('Point-of-care usage'),
        ).toBeVisible();

        // Radix's built-in typeahead jumps to the matching option — more
        // reliable than clicking a specific option in a long, possibly
        // off-screen list.
        await page.getByLabel('Patient', { exact: true }).click();
        await page.keyboard.type(patientReference);
        await page.keyboard.press('Enter');
        await page.getByLabel('Practitioner', { exact: true }).click();
        await page.keyboard.type('Admin');
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
