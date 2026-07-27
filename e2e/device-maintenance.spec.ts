import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';

test.describe('Device maintenance (UI-10)', () => {
    test('record maintenance with a past next-due date, and see it surfaced as overdue', async ({
        page,
    }) => {
        await loginAsDemoOwner(page);
        await page.goto('/devices');

        const deviceName = `E2E Maintenance Device ${Date.now()}`;
        await page.getByRole('button', { name: 'New device' }).click();
        const newDeviceDialog = page.getByRole('dialog');
        await newDeviceDialog.getByLabel('Site').click();
        await page.getByRole('option').first().click();
        await newDeviceDialog.getByLabel('Name').fill(deviceName);
        await newDeviceDialog
            .getByLabel('Serial number')
            .fill(`E2E-MAINT-${Date.now()}`);
        await newDeviceDialog
            .getByRole('button', { name: 'Add device' })
            .click();
        await expect(
            page.getByRole('heading', { name: deviceName }),
        ).toBeVisible();

        await expect(
            page.getByText('No maintenance has been recorded for this device yet.'),
        ).toBeVisible();

        // Performed 60 days ago, was due for a follow-up 1 day ago but
        // hasn't happened yet — the device header should surface this as
        // overdue immediately after recording. next_due_at must be after
        // performed_at (a real validation rule), so both are backdated.
        const performedAt = new Date(Date.now() - 60 * 24 * 60 * 60 * 1000)
            .toISOString()
            .slice(0, 16);
        const pastDue = new Date(Date.now() - 24 * 60 * 60 * 1000)
            .toISOString()
            .slice(0, 16);

        await page.getByLabel('Technician').fill('Jane Tech');
        await page.getByLabel('Performed at').fill(performedAt);
        await page.getByLabel('Next due (optional)').fill(pastDue);
        await page.getByRole('button', { name: 'Record maintenance' }).click();

        await expect(page.getByText('Maintenance recorded.')).toBeVisible();
        // Scoped to the history row — the record form's own Kind select
        // still shows "Preventive" as its default value, which would
        // otherwise collide with a bare page-wide getByText.
        const row = page.getByRole('row').filter({ hasText: 'Jane Tech' });
        await expect(row).toBeVisible();
        await expect(row.getByText('Preventive')).toBeVisible();
        await expect(page.getByText(/Overdue since/)).toBeVisible();
    });
});
