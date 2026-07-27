import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';
import {
    addDemoCycleItem,
    completeDemoCycle,
    createDemoCycle,
    createDemoDevice,
    createDemoDluRule,
    mintDemoApiToken,
    releaseDemoCycleAsCompliant,
    submitDemoCycleForRelease,
} from './support/demo';

test.describe('Label sheet (UI-06)', () => {
    test('generate labels, print the first one, then reprint with a reason', async ({
        page,
        request,
    }) => {
        const token = await mintDemoApiToken(request);
        const deviceId = await createDemoDevice(request, token);
        const cycleId = await createDemoCycle(request, token, deviceId);
        await addDemoCycleItem(request, token, cycleId, 'Scaler kit, pouch 1');
        await completeDemoCycle(request, token, cycleId);
        await submitDemoCycleForRelease(request, token, cycleId);
        await releaseDemoCycleAsCompliant(request, token, cycleId);
        await createDemoDluRule(request, token, 'Sachet', 'Armoire', 180);

        await loginAsDemoOwner(page);
        await page.goto(`/cycles/${cycleId}/labels`);

        await expect(
            page.getByRole('heading', { name: /Labels — Cycle/ }),
        ).toBeVisible();

        await page.getByLabel('Packaging').click();
        await page.getByRole('option', { name: 'Sachet', exact: true }).click();
        await page.getByLabel('Storage').click();
        await page.getByRole('option', { name: 'Armoire', exact: true }).click();
        await page.getByRole('button', { name: 'Generate labels' }).click();

        await expect(page.getByText('Labels generated.')).toBeVisible();
        await expect(
            page.getByText('Scaler kit, pouch 1', { exact: false }),
        ).toBeVisible();
        await expect(
            page.getByText('Created', { exact: true }),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Print', exact: true }).click();
        await expect(page.getByText('Label printed.')).toBeVisible();
        await expect(
            page.getByText('Printed', { exact: true }),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Reprint' }).click();
        const dialog = page.getByRole('dialog');
        await expect(
            dialog.getByRole('button', { name: 'Reprint' }),
        ).toBeDisabled();
        await dialog
            .getByLabel('Reason')
            .fill('Original label was damaged.');
        await dialog.getByRole('button', { name: 'Reprint' }).click();

        await expect(page.getByText('Label reprinted.')).toBeVisible();
    });
});
