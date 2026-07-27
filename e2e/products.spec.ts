import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';

test.describe('Products (UI-11)', () => {
    test('the Products nav link works; add a category, add a product, view it, edit it, then delete it', async ({
        page,
    }) => {
        await loginAsDemoOwner(page);

        // The nav item used to be a disabled "Soon" stub — this proves it
        // now links to a real, working screen.
        await page.getByRole('link', { name: 'Products' }).click();
        await expect(page).toHaveURL(/\/products$/);
        await expect(
            page.getByRole('heading', { name: 'Products' }),
        ).toBeVisible();

        const categoryName = `E2E Category ${Date.now()}`;
        await page.getByRole('button', { name: 'New category' }).click();
        const categoryDialog = page.getByRole('dialog');
        await categoryDialog.getByLabel('Name').fill(categoryName);
        await categoryDialog
            .getByRole('button', { name: 'Add category' })
            .click();
        await expect(
            page.getByText(`Category "${categoryName}" created.`),
        ).toBeVisible();

        const productName = `E2E Product ${Date.now()}`;
        await page.getByRole('button', { name: 'New product' }).click();
        const productDialog = page.getByRole('dialog');
        await productDialog.getByLabel('Name').fill(productName);
        await productDialog
            .getByLabel('Reference')
            .fill(`E2E-SKU-${Date.now()}`);
        await productDialog.getByLabel('Category').click();
        await page
            .getByRole('option', { name: categoryName, exact: true })
            .click();
        await productDialog
            .getByRole('button', { name: 'Add product' })
            .click();

        await expect(
            page.getByText(`Product "${productName}" created.`),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', { name: productName }),
        ).toBeVisible();
        // exact: true — an earlier "Category ... created." toast may still
        // be lingering on screen and also contains this substring.
        await expect(
            page.getByText(categoryName, { exact: true }),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Edit' }).click();
        const editDialog = page.getByRole('dialog');
        await editDialog.getByLabel('Unit').fill('box');
        await editDialog
            .getByRole('button', { name: 'Save changes' })
            .click();
        await expect(page.getByText('Product updated.')).toBeVisible();
        await expect(editDialog).not.toBeVisible();
        await expect(page.getByText('box', { exact: true })).toBeVisible();

        await page.getByRole('button', { name: 'Delete' }).click();
        const deleteDialog = page.getByRole('dialog');
        await deleteDialog
            .getByRole('button', { name: 'Delete product' })
            .click();

        await expect(
            page.getByText(`Product "${productName}" removed.`),
        ).toBeVisible();
        await expect(page).toHaveURL(/\/products$/);
    });
});
