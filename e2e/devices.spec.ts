import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';

test.describe('Devices (UI-08)', () => {
    test('the Devices nav link works; add a device, view it, then edit it', async ({
        page,
    }) => {
        await loginAsDemoOwner(page);

        // The nav item used to be a disabled "Soon" stub — this proves it
        // now links to a real, working screen.
        await page.getByRole('link', { name: 'Devices' }).click();
        await expect(page).toHaveURL(/\/devices$/);
        await expect(
            page.getByRole('heading', { name: 'Devices' }),
        ).toBeVisible();

        const deviceName = `E2E Device ${Date.now()}`;
        const serialNumber = `E2E-SN-${Date.now()}`;

        await page.getByRole('button', { name: 'New device' }).click();
        // Scoped to the dialog — the list page's own "Search by name…"
        // filter input also matches a bare getByLabel('Name').
        const newDeviceDialog = page.getByRole('dialog');
        await newDeviceDialog.getByLabel('Site').click();
        await page.getByRole('option').first().click();
        await newDeviceDialog.getByLabel('Name').fill(deviceName);
        await newDeviceDialog.getByLabel('Serial number').fill(serialNumber);
        await newDeviceDialog
            .getByRole('button', { name: 'Add device' })
            .click();

        await expect(page.getByText(`Device "${deviceName}" created.`)).toBeVisible();
        await expect(
            page.getByRole('heading', { name: deviceName }),
        ).toBeVisible();
        await expect(page.getByText('Active', { exact: true })).toBeVisible();

        await page.getByRole('button', { name: 'Edit' }).click();
        const dialog = page.getByRole('dialog');
        await dialog.getByLabel('Status').click();
        await page.getByRole('option', { name: 'Maintenance' }).click();
        await dialog.getByRole('button', { name: 'Save changes' }).click();

        await expect(page.getByText('Device updated.')).toBeVisible();
        // Wait for the dialog to fully close first — its own Status select
        // still shows "Maintenance" as its selected value while animating
        // out, which would otherwise collide with the status badge below.
        await expect(dialog).not.toBeVisible();
        await expect(
            page.getByText('Maintenance', { exact: true }),
        ).toBeVisible();
    });
});
