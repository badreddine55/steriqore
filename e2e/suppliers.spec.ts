import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';

test.describe('Suppliers (UI-12)', () => {
    test('the Suppliers nav link works; add a supplier, link a product, edit it, then delete it', async ({
        page,
    }) => {
        await loginAsDemoOwner(page);

        // Create a product first so there's something to link.
        await page.goto('/products');
        const productName = `E2E Supplier Product ${Date.now()}`;
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

        // The nav item used to be a disabled "Soon" stub — this proves it
        // now links to a real, working screen.
        await page.getByRole('link', { name: 'Suppliers' }).click();
        await expect(page).toHaveURL(/\/suppliers$/);
        await expect(
            page.getByRole('heading', { name: 'Suppliers' }),
        ).toBeVisible();

        const supplierName = `E2E Supplier ${Date.now()}`;
        await page.getByRole('button', { name: 'New supplier' }).click();
        const supplierDialog = page.getByRole('dialog');
        await supplierDialog.getByLabel('Name').fill(supplierName);
        await supplierDialog
            .getByRole('button', { name: 'Add supplier' })
            .click();

        await expect(
            page.getByText(`Supplier "${supplierName}" created.`),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', { name: supplierName }),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Edit' }).click();
        const editDialog = page.getByRole('dialog');
        await editDialog.getByLabel('Phone').fill('+33 1 23 45 67 89');
        await editDialog
            .getByRole('button', { name: 'Save changes' })
            .click();
        await expect(page.getByText('Supplier updated.')).toBeVisible();
        await expect(editDialog).not.toBeVisible();

        await page.getByLabel('Product').click();
        await page
            .getByRole('option', { name: productName, exact: true })
            .click();
        await page.getByLabel('Supplier reference').fill('ACME-001');
        await page.getByLabel('Pack size').fill('200');
        await page.getByRole('button', { name: 'Link product' }).click();

        await expect(
            page.getByText('Product linked to supplier.'),
        ).toBeVisible();
        const row = page.getByRole('row').filter({ hasText: productName });
        await expect(row).toBeVisible();
        await expect(row.getByText('ACME-001')).toBeVisible();

        await page.getByRole('button', { name: 'Delete' }).click();
        const deleteDialog = page.getByRole('dialog');
        await deleteDialog
            .getByRole('button', { name: 'Delete supplier' })
            .click();

        await expect(
            page.getByText(`Supplier "${supplierName}" removed.`),
        ).toBeVisible();
        await expect(page).toHaveURL(/\/suppliers$/);
    });
});
