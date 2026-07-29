import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';

/**
 * Data export (UI-29) — the most sensitive artifact the product can emit.
 * The demo tenant is shared and persistent across runs, and requests are
 * rate-limited (one in flight, a weekly cap) — this spec only requests a
 * fresh export when none is already running, same tolerance-for-prior-runs
 * approach as other specs against this shared fixture.
 */
test.describe('Data export (UI-29)', () => {
    test('an owner requests an export, watches it complete, and inspects its contents', async ({
        page,
    }) => {
        await loginAsDemoOwner(page);
        await page.goto('/data-exports');
        await expect(
            page.getByRole('heading', { name: 'Data export' }),
        ).toBeVisible();

        const requestButton = page.getByRole('button', {
            name: 'Request export',
        });

        if (await requestButton.isEnabled()) {
            await requestButton.click();
            const confirmDialog = page.getByRole('alertdialog');
            await expect(
                confirmDialog.getByText('Request a full data export?'),
            ).toBeVisible();
            await confirmDialog
                .getByRole('button', { name: 'Request export' })
                .click();
            await expect(
                page.getByText(/Export requested\./),
            ).toBeVisible();
        }

        // The real Horizon worker in this environment actually processes
        // the job — poll for it to reach Ready like a real user would.
        const readyRow = page
            .getByRole('row')
            .filter({ has: page.getByText('Ready') })
            .first();
        await expect(readyRow).toBeVisible({ timeout: 30_000 });

        await readyRow.getByRole('button', { name: 'View contents' }).click();
        const manifestDialog = page.getByRole('dialog');
        await expect(
            manifestDialog.getByText("What's inside this export"),
        ).toBeVisible();
        await expect(manifestDialog.getByText('Total size')).toBeVisible();
        await expect(
            manifestDialog.getByRole('row').nth(1),
        ).toBeVisible();
        await page.keyboard.press('Escape');

        const [downloadResponse] = await Promise.all([
            page.waitForResponse(
                (response) =>
                    response.url().includes('/download') &&
                    response.request().method() === 'POST',
            ),
            readyRow.getByRole('button', { name: 'Download' }).click(),
        ]);
        expect(downloadResponse.ok()).toBeTruthy();
        const body = await downloadResponse.json();
        expect(body.download_url).toContain('data-exports/');
        expect(body.url_expires_at).toBeTruthy();
    });
});
