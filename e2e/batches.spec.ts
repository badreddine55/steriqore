import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';

/**
 * Batches have no create route anywhere in the domain — they only ever
 * come into existence as a side effect of receiving a purchase order
 * (UI-14). This spec drives that same real flow, then checks the batch
 * shows up in the tenant-wide batches list (UI-15) with the right
 * on-hand quantity and an "Expiring soon" badge.
 */
test.describe('Batches (UI-15)', () => {
    test('receive a purchase order, then find the resulting batch in the batches list', async ({
        page,
    }) => {
        await loginAsDemoOwner(page);

        await page.goto('/products');
        const productName = `E2E Batch Product ${Date.now()}`;
        await page.getByRole('button', { name: 'New product' }).click();
        const productDialog = page.getByRole('dialog');
        await productDialog.getByLabel('Name').fill(productName);
        await productDialog
            .getByLabel('Reference')
            .fill(`E2E-BATCH-SKU-${Date.now()}`);
        await productDialog
            .getByRole('button', { name: 'Add product' })
            .click();
        await expect(
            page.getByRole('heading', { name: productName }),
        ).toBeVisible();

        await page.goto('/suppliers');
        const supplierName = `E2E Batch Supplier ${Date.now()}`;
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

        const batchNumber = `E2E-BATCH-${Date.now()}`;
        const expiry = new Date(Date.now() + 20 * 86_400_000)
            .toISOString()
            .slice(0, 10);

        await expect(page.getByLabel('Storage location')).toBeVisible();
        await page.getByLabel('Storage location').click();
        await page.getByRole('option').first().click();
        await page.getByLabel(/Batch number for/).fill(batchNumber);
        await page.getByLabel(/Expiry date for/).fill(expiry);
        await page.getByLabel(/Quantity received for/).fill('10');
        await page.getByRole('button', { name: 'Record receipt' }).click();
        await expect(page.getByText('Goods received.')).toBeVisible();

        await page.getByRole('link', { name: 'Batches' }).click();
        await expect(page).toHaveURL(/\/batches$/);
        await expect(
            page.getByRole('heading', { name: 'Batches' }),
        ).toBeVisible();

        await page
            .getByRole('combobox')
            .filter({ hasText: 'All products' })
            .click();
        await page
            .getByRole('option', { name: productName, exact: true })
            .click();

        const row = page.getByRole('row').filter({ hasText: batchNumber });
        await expect(row).toBeVisible();
        await expect(row.getByText(productName)).toBeVisible();
        await expect(row.getByText('10', { exact: true })).toBeVisible();
        await expect(row.getByText('Expiring soon')).toBeVisible();
        await expect(row.getByText('Active', { exact: true })).toBeVisible();
    });
});
