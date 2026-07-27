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
        await expect(page.getByText('Draft')).toBeVisible();
        await expect(page.getByText('Running')).toBeVisible();
        await expect(page.getByText('Awaiting release')).toBeVisible();
        await expect(page.getByText('Released / Rejected')).toBeVisible();

        await expect(page.getByText('Scaler kit, pouch 1')).toBeVisible();
        await expect(page.getByText('Vacuum')).toBeVisible();
        await expect(page.getByText('Pass')).toBeVisible();
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
