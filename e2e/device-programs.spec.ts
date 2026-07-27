import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';

test.describe('Device programs (UI-09)', () => {
    test('add a program, edit it, then remove it', async ({ page }) => {
        await loginAsDemoOwner(page);
        await page.goto('/devices');

        const deviceName = `E2E Program Device ${Date.now()}`;
        await page.getByRole('button', { name: 'New device' }).click();
        const newDeviceDialog = page.getByRole('dialog');
        await newDeviceDialog.getByLabel('Site').click();
        await page.getByRole('option').first().click();
        await newDeviceDialog.getByLabel('Name').fill(deviceName);
        await newDeviceDialog
            .getByLabel('Serial number')
            .fill(`E2E-PROG-${Date.now()}`);
        await newDeviceDialog
            .getByRole('button', { name: 'Add device' })
            .click();
        await expect(
            page.getByRole('heading', { name: deviceName }),
        ).toBeVisible();

        await expect(
            page.getByText('No programs configured for this device yet.'),
        ).toBeVisible();

        await page.getByLabel('Name').fill('134°C Standard');
        await page.getByLabel('Target temp (°C)').fill('134');
        await page.getByLabel('Plateau (min)').fill('18');
        await page.getByRole('button', { name: 'Add program' }).click();

        await expect(page.getByText('Program added.')).toBeVisible();
        const row = page.getByRole('row').filter({ hasText: '134°C Standard' });
        await expect(row).toBeVisible();
        await expect(row.getByText('Active', { exact: true })).toBeVisible();

        await row.getByRole('button', { name: /Edit/ }).click();
        const editDialog = page.getByRole('dialog');
        await editDialog.getByLabel('Status').click();
        await page.getByRole('option', { name: 'Inactive' }).click();
        await editDialog
            .getByRole('button', { name: 'Save changes' })
            .click();

        await expect(page.getByText('Program updated.')).toBeVisible();
        await expect(editDialog).not.toBeVisible();
        await expect(row.getByText('Inactive', { exact: true })).toBeVisible();

        await row.getByRole('button', { name: /Remove/ }).click();
        await expect(page.getByText('Program removed.')).toBeVisible();
        await expect(
            page.getByText('No programs configured for this device yet.'),
        ).toBeVisible();
    });
});
