import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';
import { mintDemoApiToken } from './support/demo';

/**
 * Alerts are opened by DetectAlertsAction (low_stock/near_expiry/expired)
 * and RecordControlTestAction (failed_cycle) — never by a web form — so
 * this fixture is built by receiving stock below a threshold, then
 * calling the real detection endpoint, exactly as the daily scheduled job
 * would. There's no API to create a storage location (same known gap as
 * UI-14/15/16), so this reuses whichever location the demo tenant's prior
 * stock already sits at.
 */
test.describe('Alerts (UI-18)', () => {
    test('detect a low-stock alert via the API, resolve it from the web inbox', async ({
        page,
        request,
    }) => {
        const token = await mintDemoApiToken(request);

        const existingLevels = await request.get('/api/v1/stock-levels', {
            headers: { Authorization: `Bearer ${token}` },
        });
        const locationId = (await existingLevels.json()).data[0]
            .location_id as string;

        const productName = `E2E Alert Product ${Date.now()}`;
        const productCreate = await request.post('/api/v1/products', {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: {
                name: productName,
                reference: `E2E-ALERT-${Date.now()}`,
                min_threshold: 50,
            },
        });
        const productId = (await productCreate.json()).id as string;

        const supplierCreate = await request.post('/api/v1/suppliers', {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: { name: `E2E Alert Supplier ${Date.now()}` },
        });
        const supplierId = (await supplierCreate.json()).id as string;

        const poCreate = await request.post('/api/v1/purchase-orders', {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: {
                supplier_id: supplierId,
                lines: [{ product_id: productId, qty_ordered: 5 }],
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

        await request.post(
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
                            batch_number: `E2E-ALERT-BATCH-${Date.now()}`,
                            qty: 5,
                        },
                    ],
                },
            },
        );

        // Only 5 units received against a threshold of 50 — opens a
        // low_stock alert (DetectAlertsAction), same as the scheduled job.
        await request.post('/api/v1/alerts/detect', {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: {},
        });

        await loginAsDemoOwner(page);

        // The nav item used to be a disabled "Soon" stub.
        await page.getByRole('link', { name: 'Alerts' }).click();
        await expect(page).toHaveURL(/\/alerts$/);
        await expect(
            page.getByRole('heading', { name: 'Alerts' }),
        ).toBeVisible();

        const row = page
            .getByRole('row')
            .filter({ hasText: productName });
        await expect(row).toBeVisible();
        await expect(row.getByText('Low stock')).toBeVisible();
        await expect(row.getByText('Open', { exact: true })).toBeVisible();

        await row.getByRole('button', { name: 'Resolve' }).click();
        await expect(page.getByText('Alert resolved.')).toBeVisible();
        await expect(
            row.getByText('Resolved', { exact: true }),
        ).toBeVisible();
        await expect(row.getByText('Admin', { exact: false })).toBeVisible();
    });
});
