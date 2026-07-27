import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';

test.describe('Purchase orders (UI-13)', () => {
    test('the Purchase orders nav link works; create an order, mark it ordered, then cancel it', async ({
        page,
    }) => {
        await loginAsDemoOwner(page);

        // Fixtures: a product and a supplier to order from.
        await page.goto('/products');
        const productName = `E2E PO Product ${Date.now()}`;
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
        const supplierName = `E2E PO Supplier ${Date.now()}`;
        await page.getByRole('button', { name: 'New supplier' }).click();
        const supplierDialog = page.getByRole('dialog');
        await supplierDialog.getByLabel('Name').fill(supplierName);
        await supplierDialog
            .getByRole('button', { name: 'Add supplier' })
            .click();
        await expect(
            page.getByRole('heading', { name: supplierName }),
        ).toBeVisible();

        // The nav item used to be a disabled "Soon" stub — this proves it
        // now links to a real, working screen.
        await page.getByRole('link', { name: 'Purchase orders' }).click();
        await expect(page).toHaveURL(/\/purchase-orders$/);
        await expect(
            page.getByRole('heading', { name: 'Purchase orders' }),
        ).toBeVisible();

        await page.getByRole('link', { name: 'New purchase order' }).click();
        await expect(page).toHaveURL(/\/purchase-orders\/create$/);

        await page.getByLabel('Supplier').click();
        await page
            .getByRole('option', { name: supplierName, exact: true })
            .click();
        await page.getByLabel(/Product for line 1/).click();
        await page
            .getByRole('option', { name: productName, exact: true })
            .click();
        await page.getByLabel(/Quantity for line 1/).fill('50');
        await page.getByLabel(/Unit price for line 1/).fill('12.50');
        await page
            .getByRole('button', { name: 'Create purchase order' })
            .click();

        await expect(
            page.getByText('Purchase order created.'),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', { name: supplierName }),
        ).toBeVisible();
        await expect(page.getByText('Draft', { exact: true })).toBeVisible();
        const row = page.getByRole('row').filter({ hasText: productName });
        await expect(row).toBeVisible();
        await expect(row.getByText('50', { exact: true })).toBeVisible();

        await page.getByRole('button', { name: 'Mark as ordered' }).click();
        await expect(
            page.getByText('Purchase order marked as ordered.'),
        ).toBeVisible();
        // .first() — the status badge in the page header (DOM order: first)
        // vs. the lines table's own "Ordered" column header, both matching
        // a bare exact-text query.
        await expect(
            page.getByText('Ordered', { exact: true }).first(),
        ).toBeVisible();

        await page
            .getByRole('button', { name: 'Cancel purchase order' })
            .click();
        const cancelDialog = page.getByRole('dialog');
        await cancelDialog
            .getByLabel('Reason (optional)')
            .fill('Supplier out of stock.');
        await cancelDialog
            .getByRole('button', { name: 'Cancel purchase order' })
            .click();

        await expect(
            page.getByText('Purchase order cancelled.'),
        ).toBeVisible();
        await expect(
            page.getByText('Cancelled', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Mark as ordered' }),
        ).toHaveCount(0);
        await expect(
            page.getByRole('button', { name: 'Cancel purchase order' }),
        ).toHaveCount(0);
    });
});
