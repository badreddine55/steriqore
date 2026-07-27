import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';
import {
    addDemoCycleItem,
    completeDemoCycle,
    createDemoCycle,
    createDemoDevice,
    mintDemoApiToken,
} from './support/demo';

test.describe('Control tests (UI-04)', () => {
    test('record a test from the cycle detail page, then find it in the logbook', async ({
        page,
        request,
    }) => {
        const token = await mintDemoApiToken(request);
        const deviceId = await createDemoDevice(request, token);
        const cycleId = await createDemoCycle(request, token, deviceId);
        await addDemoCycleItem(request, token, cycleId, 'Scaler kit, pouch 1');
        await completeDemoCycle(request, token, cycleId);

        await loginAsDemoOwner(page);
        await page.goto(`/cycles/${cycleId}`);

        await expect(
            page.getByText('No control tests recorded for this cycle yet.'),
        ).toBeVisible();

        await page.getByLabel('Type').click();
        await page.getByRole('option', { name: 'Helix' }).click();
        await page.getByLabel('Result').click();
        await page.getByRole('option', { name: 'Pass' }).click();
        await page.getByLabel('Control test notes').fill('Ran clean.');
        await page.getByRole('button', { name: 'Record test' }).click();

        await expect(page.getByText('Control test recorded.')).toBeVisible();
        await expect(page.getByRole('cell', { name: 'Helix' })).toBeVisible();

        // The logbook is a second, tenant-wide view of the same row.
        await page.getByRole('link', { name: 'Control tests' }).click();
        await expect(page).toHaveURL(/\/control-tests$/);

        // The demo tenant is a persistent, shared fixture — prior runs of
        // this same spec leave their own "Helix" rows behind, so this
        // filter can match several. Sorted newest-first, the row this test
        // just created is always the first match.
        const row = page.getByRole('row').filter({ hasText: 'Helix' }).first();
        await expect(row).toBeVisible();
        await expect(row.getByText('Pass')).toBeVisible();

        // Filtering to a result with no matches proves the filter reaches
        // the server, not just a client-side illusion.
        await page
            .getByRole('combobox')
            .filter({ hasText: 'All results' })
            .click();
        await page.getByRole('option', { name: 'Fail' }).click();
        await expect(
            page.getByText('No control tests match these filters.'),
        ).toBeVisible();
    });
});
