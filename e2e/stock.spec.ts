import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';
import { mintDemoApiToken } from './support/demo';

/**
 * Stock levels + the movement ledger (UI-16) are both read-only — there's
 * no web form to issue/transfer/adjust stock (mobile-only for now), so
 * this fixture is built the same way a real receipt + issue would happen:
 * through the mobile API, exactly like a real client would call it.
 * There's no API to create a storage location either (same gap UI-14/15
 * already flagged), so this reuses whichever location the demo tenant's
 * prior stock already sits at.
 */
test.describe('Stock levels + movement ledger (UI-16)', () => {
    test('receive stock, then issue some of it, and see both reflected on the web', async ({
        page,
        request,
    }) => {
        const token = await mintDemoApiToken(request);

        const existingLevels = await request.get('/api/v1/stock-levels', {
            headers: { Authorization: `Bearer ${token}` },
        });
        const locationId = (await existingLevels.json()).data[0]
            .location_id as string;

        const productName = `E2E Stock Product ${Date.now()}`;
        const productCreate = await request.post('/api/v1/products', {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: { name: productName, reference: `E2E-STOCK-${Date.now()}` },
        });
        const productId = (await productCreate.json()).id as string;

        const supplierCreate = await request.post('/api/v1/suppliers', {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: { name: `E2E Stock Supplier ${Date.now()}` },
        });
        const supplierId = (await supplierCreate.json()).id as string;

        const poCreate = await request.post('/api/v1/purchase-orders', {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: {
                supplier_id: supplierId,
                lines: [{ product_id: productId, qty_ordered: 20 }],
            },
        });
        const po = await poCreate.json();
        const purchaseOrderId = po.id as string;
        const lineId = po.lines[0].id as string;

        await request.post(
            `/api/v1/purchase-orders/${purchaseOrderId}/order`,
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                    'Idempotency-Key': crypto.randomUUID(),
                },
                data: {},
            },
        );

        const batchNumber = `E2E-STOCK-BATCH-${Date.now()}`;
        const receiptCreate = await request.post(
            `/api/v1/purchase-orders/${purchaseOrderId}/receipts`,
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                    'Idempotency-Key': crypto.randomUUID(),
                },
                data: {
                    location_id: locationId,
                    lines: [
                        {
                            purchase_order_line_id: lineId,
                            batch_number: batchNumber,
                            qty: 20,
                        },
                    ],
                },
            },
        );
        const batchId = (await receiptCreate.json()).lines[0]
            .batch_id as string;

        const issueReason = `E2E issued at ${Date.now()}.`;
        await request.post('/api/v1/stock-movements/issue', {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: {
                batch_id: batchId,
                location_id: locationId,
                qty: 8,
                reason: issueReason,
            },
        });

        await loginAsDemoOwner(page);

        // The nav item used to be a disabled "Soon" stub.
        await page.getByRole('link', { name: 'Stock' }).click();
        await expect(page).toHaveURL(/\/stock$/);
        await expect(
            page.getByRole('heading', { name: 'Stock' }),
        ).toBeVisible();

        await page
            .getByRole('combobox')
            .filter({ hasText: 'All products' })
            .click();
        await page
            .getByRole('option', { name: productName, exact: true })
            .click();

        const levelRow = page
            .getByRole('row')
            .filter({ hasText: batchNumber });
        await expect(levelRow).toBeVisible();
        await expect(
            levelRow.getByText('12', { exact: true }),
        ).toBeVisible();

        await page.getByRole('link', { name: 'Movement ledger' }).click();
        await expect(page).toHaveURL(/\/stock\/movements$/);
        await expect(
            page.getByText(
                'Append-only — every row here is permanent.',
                { exact: false },
            ),
        ).toBeVisible();

        const receiptRow = page
            .getByRole('row')
            .filter({ hasText: batchNumber })
            .filter({ hasText: 'Receipt' });
        await expect(receiptRow).toBeVisible();
        await expect(
            receiptRow.getByText('+20', { exact: true }),
        ).toBeVisible();

        const issueRow = page
            .getByRole('row')
            .filter({ hasText: issueReason });
        await expect(issueRow).toBeVisible();
        await expect(issueRow.getByText('-8', { exact: true })).toBeVisible();
        await expect(
            issueRow.getByText('Issue', { exact: true }),
        ).toBeVisible();
    });
});
