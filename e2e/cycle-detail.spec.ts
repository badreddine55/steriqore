import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';
import {
    addDemoCycleItem,
    completeDemoCycle,
    createDemoCycle,
    createDemoDevice,
    mintDemoApiToken,
    recordDemoControlTest,
} from './support/demo';

test.describe('Cycle detail (UI-02)', () => {
    test('shows the timeline, load composition and control tests for a real cycle', async ({
        page,
        request,
    }) => {
        const token = await mintDemoApiToken(request);
        const deviceId = await createDemoDevice(request, token);
        const cycleId = await createDemoCycle(request, token, deviceId);
        await addDemoCycleItem(request, token, cycleId, 'Scaler kit, pouch 1');
        await completeDemoCycle(request, token, cycleId);
        await recordDemoControlTest(request, token, cycleId);

        await loginAsDemoOwner(page);
        await page.goto(`/cycles/${cycleId}`);

        await expect(
            page.getByRole('heading', { name: /Cycle #/ }),
        ).toBeVisible();
        // The timeline signature element: every station label is present.
        // Scoped to the timeline itself (not a plain page-wide text search)
        // since e.g. "running" also appears in the load composition card's
        // "Locked once the cycle is running" note.
        const timeline = page.getByLabel('Cycle progress');
        await expect(
            timeline.getByText('Draft', { exact: true }),
        ).toBeVisible();
        await expect(
            timeline.getByText('Running', { exact: true }),
        ).toBeVisible();
        await expect(
            timeline.getByText('Awaiting release', { exact: true }),
        ).toBeVisible();
        await expect(
            timeline.getByText('Released / Rejected', { exact: true }),
        ).toBeVisible();

        await expect(page.getByText('Scaler kit, pouch 1')).toBeVisible();
        // Scoped to the control test's own row — UI-04 added a "record a
        // test" form right below this table whose Type/Result pickers
        // default to the same "Vacuum"/"Pass" text, so a bare page-wide
        // getByText would match both.
        const testRow = page.getByRole('row').filter({ hasText: 'Vacuum' });
        await expect(testRow).toBeVisible();
        await expect(testRow.getByText('Pass')).toBeVisible();
    });

    test('clicking a row in the cycles list opens its detail page', async ({
        page,
        request,
    }) => {
        const token = await mintDemoApiToken(request);
        const deviceId = await createDemoDevice(request, token);
        const cycleId = await createDemoCycle(request, token, deviceId);

        await loginAsDemoOwner(page);
        await page.goto('/cycles');
        await page.getByRole('row', { name: /Draft/ }).first().click();

        await expect(page).toHaveURL(new RegExp(`/cycles/${cycleId}$`));
    });

    test('a cross-tenant cycle id is a 404, not a leak', async ({ page }) => {
        await loginAsDemoOwner(page);

        const response = await page.goto(`/cycles/${crypto.randomUUID()}`);
        expect(response?.status()).toBe(404);
    });
});
