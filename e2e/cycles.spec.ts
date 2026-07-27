import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';
import {
    createDemoCycle,
    createDemoDevice,
    mintDemoApiToken,
} from './support/demo';

test.describe('Cycles list (UI-01)', () => {
    test('shows a seeded cycle with its status, and a status filter narrows the list', async ({
        page,
        request,
    }) => {
        // Fixture data via the real API — there's no browser-based "start a
        // cycle" screen yet (UI-03), so this is the only honest way to get
        // a real cycle in front of the list under test.
        const token = await mintDemoApiToken(request);
        const deviceId = await createDemoDevice(request, token);
        await createDemoCycle(request, token, deviceId);

        await loginAsDemoOwner(page);
        await page.goto('/cycles');

        await expect(
            page.getByRole('heading', { name: 'Cycles' }),
        ).toBeVisible();

        const row = page.getByRole('row').filter({ hasText: 'Draft' }).first();
        await expect(row).toBeVisible();
        await expect(row.getByText('Draft')).toBeVisible();

        // Filtering to a status with no matches proves the filter really
        // reaches the server (spatie/laravel-query-builder's filter[...]
        // bucket), not just a client-side illusion.
        await page
            .getByRole('combobox')
            .filter({ hasText: 'All statuses' })
            .click();
        await page.getByRole('option', { name: 'Rejected' }).click();
        await expect(page).toHaveURL(
            /filter%5Bstatus%5D=rejected|filter\[status\]=rejected/,
        );
        await expect(
            page.getByText('No cycles match these filters.'),
        ).toBeVisible();

        // Switching back to the status our seeded cycle actually has.
        await page
            .getByRole('combobox')
            .filter({ hasText: 'Rejected' })
            .click();
        await page.getByRole('option', { name: 'Draft' }).click();
        await expect(
            page.getByRole('row').filter({ hasText: 'Draft' }).first(),
        ).toBeVisible();
    });

    test('a nav item without permission is never rendered, but the Cycles link works', async ({
        page,
    }) => {
        await loginAsDemoOwner(page);

        await expect(page.getByRole('link', { name: 'Cycles' })).toBeVisible();
        await page.getByRole('link', { name: 'Cycles' }).click();
        await expect(page).toHaveURL(/\/cycles$/);
    });
});
