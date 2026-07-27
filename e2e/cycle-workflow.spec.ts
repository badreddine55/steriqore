import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';
import { createDemoDevice, mintDemoApiToken } from './support/demo';

test.describe('Cycle workflow (UI-03)', () => {
    test('create a cycle, build the load, and run it through to awaiting release', async ({
        page,
        request,
    }) => {
        const token = await mintDemoApiToken(request);
        await createDemoDevice(request, token);

        await loginAsDemoOwner(page);
        await page.goto('/cycles');

        await page.getByRole('button', { name: 'New cycle' }).click();
        await page.getByLabel('Device').click();
        await page
            .getByRole('option', { name: /E2E Autoclave/ })
            .first()
            .click();
        await page.getByRole('button', { name: 'Create cycle' }).click();

        // Redirected to the new cycle's detail page, in draft. "Draft" also
        // labels a station on the timeline further down the page — the
        // status badge (the first match, right next to the heading) is
        // what's actually under test here.
        await expect(page).toHaveURL(/\/cycles\/[0-9a-f-]+$/);
        await expect(
            page.getByText('Draft', { exact: true }).first(),
        ).toBeVisible();

        // Starting an empty load is blocked, with a real reason shown.
        const startButton = page.getByRole('button', { name: 'Start cycle' });
        await expect(startButton).toBeDisabled();

        // Build the load.
        await page
            .getByLabel('New item description')
            .fill('Scaler kit, pouch 1');
        await page.getByRole('button', { name: 'Add item' }).click();
        await expect(page.getByText('Scaler kit, pouch 1')).toBeVisible();

        // Now the load is non-empty, so start is enabled.
        await expect(startButton).toBeEnabled();
        await startButton.click();
        await expect(
            page.getByText('Running', { exact: true }).first(),
        ).toBeVisible();
        await expect(
            page.getByText('Locked once the cycle is running.'),
        ).toBeVisible();

        await page
            .getByRole('button', { name: 'Complete cycle — record result' })
            .click();
        await expect(
            page.getByText('Completed', { exact: true }).first(),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Submit for release' }).click();
        await expect(
            page.getByText('Pending release', { exact: true }),
        ).toBeVisible();

        // No further operator action once awaiting release — that's UI-05's territory.
        await expect(
            page.getByRole('button', { name: 'Start cycle' }),
        ).toHaveCount(0);
        await expect(
            page.getByRole('button', { name: 'Submit for release' }),
        ).toHaveCount(0);
    });
});
