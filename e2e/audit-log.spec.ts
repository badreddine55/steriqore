import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';
import { createDemoDevice, mintDemoApiToken } from './support/demo';

/**
 * Audit log viewer (UI-25) — old/new value diffing, filter by actor,
 * action, subject, period. Every write throughout this whole app already
 * writes to audit_events (RecordAuditEventAction) — this just makes that
 * existing, append-only trail visible and filterable.
 */
test.describe('Audit log (UI-25)', () => {
    test('an update shows up with a before/after diff, and the action filter narrows the list', async ({
        page,
        request,
    }) => {
        const token = await mintDemoApiToken(request);
        const deviceId = await createDemoDevice(request, token);

        const renamedTo = `E2E Audit Device ${Date.now()} (renamed)`;
        await request.patch(`/api/v1/devices/${deviceId}`, {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: { name: renamedTo },
        });

        await loginAsDemoOwner(page);

        // The nav item used to be a disabled "Soon" stub.
        await page.getByRole('link', { name: 'Audit log' }).click();
        await expect(page).toHaveURL(/\/audit-log$/);
        await expect(
            page.getByRole('heading', { name: 'Audit log' }),
        ).toBeVisible();

        await page
            .getByRole('combobox')
            .filter({ hasText: 'All actions' })
            .click();
        await page
            .getByRole('option', { name: 'device.updated', exact: true })
            .click();

        const row = page.getByRole('row').filter({ hasText: 'device.updated' }).first();
        await expect(row).toBeVisible();
        await row.getByRole('button', { name: 'Details' }).click();

        const dialog = page.getByRole('dialog');
        await expect(dialog.getByText('device.updated')).toBeVisible();
        await expect(dialog.getByText('name', { exact: true })).toBeVisible();
        await expect(dialog.getByText(renamedTo)).toBeVisible();
    });
});
