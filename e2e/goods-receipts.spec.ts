import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';

/**
 * Receives into whichever storage location the shared demo tenant already
 * has (seeded once via the real Sites/Rooms/Storage locations screen,
 * UI-27 — CreateStorageLocationAction has existed since then). A genuinely
 * fresh tenant with none yet sees an honest "no storage locations
 * configured yet" empty state on this same form.
 */
test.describe('Goods receipts (UI-14)', () => {
    test('order a purchase order, then partially receive it with a batch, expiry, and discrepancy reason', async ({
        page,
    }) => {
        await loginAsDemoOwner(page);

        await page.goto('/products');
        const productName = `E2E Receipt Product ${Date.now()}`;
        await page.getByRole('button', { name: 'New product' }).click();
        const productDialog = page.getByRole('dialog');
        await productDialog.getByLabel('Name').fill(productName);
        await productDialog
            .getByLabel('Reference')
            .fill(`E2E-SKU-${Date.now()}`);
        await productDialog
            .getByRole('button', { name: 'Add product' })
            .click();
        await expect(
            page.getByRole('heading', { name: productName }),
        ).toBeVisible();

        await page.goto('/suppliers');
        const supplierName = `E2E Receipt Supplier ${Date.now()}`;
        await page.getByRole('button', { name: 'New supplier' }).click();
        const supplierDialog = page.getByRole('dialog');
        await supplierDialog.getByLabel('Name').fill(supplierName);
        await supplierDialog
            .getByRole('button', { name: 'Add supplier' })
            .click();
        await expect(
            page.getByRole('heading', { name: supplierName }),
        ).toBeVisible();

        await page.goto('/purchase-orders/create');
        await page.getByLabel('Supplier').click();
        await page
            .getByRole('option', { name: supplierName, exact: true })
            .click();
        await page.getByLabel(/Product for line 1/).click();
        await page
            .getByRole('option', { name: productName, exact: true })
            .click();
        await page.getByLabel(/Quantity for line 1/).fill('10');
        await page
            .getByRole('button', { name: 'Create purchase order' })
            .click();
        await expect(
            page.getByText('Purchase order created.'),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Mark as ordered' }).click();
        await expect(
            page.getByText('Purchase order marked as ordered.'),
        ).toBeVisible();

        await expect(page.getByLabel('Storage location')).toBeVisible();
        await page.getByLabel('Storage location').click();
        await page.getByRole('option').first().click();
        await page.getByLabel(/Batch number for/).fill('E2E-BATCH-001');
        await page
            .getByLabel(/Expiry date for/)
            .fill('2027-01-01');
        await page.getByLabel(/Quantity received for/).fill('6');
        await page
            .getByLabel(/Discrepancy reason for/)
            .fill('4 units damaged in transit.');
        await page.getByRole('button', { name: 'Record receipt' }).click();

        await expect(page.getByText('Goods received.')).toBeVisible();
        await expect(
            page.getByText('Partially received', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByText('Receipt history', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('row').filter({ hasText: productName }).last(),
        ).toBeVisible();
        await expect(
            page.getByText('4 units damaged in transit.'),
        ).toBeVisible();
    });
});
