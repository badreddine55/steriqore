import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';
import {
    completeDemoCycle,
    createDemoCycle,
    createDemoDevice,
    createDemoDluRule,
    generateDemoLabels,
    mintDemoApiToken,
    printDemoLabel,
    releaseDemoCycleAsCompliant,
    submitDemoCycleForRelease,
} from './support/demo';

/**
 * Quarantine (UI-24) — read-only. The only writer is
 * RaiseNonConformityAction (UI-23's recall flow), so this fixture goes
 * through the real web recall flow rather than setting Batch::status
 * directly. A batch only gets quarantined as a side effect of recalling
 * a label that actually references it, so this builds a full
 * receive -> cycle item (with that batch) -> release -> label chain,
 * not just a bare cycle item.
 */
test.describe('Quarantine (UI-24)', () => {
    test('recalling a cycle with a batch surfaces it in the quarantine list with who, why, and when', async ({
        page,
        request,
    }) => {
        const token = await mintDemoApiToken(request);
        const deviceId = await createDemoDevice(request, token);
        const cycleId = await createDemoCycle(request, token, deviceId);

        const existingLevels = await request.get('/api/v1/stock-levels', {
            headers: { Authorization: `Bearer ${token}` },
        });
        const locationId = (await existingLevels.json()).data[0]
            .location_id as string;
        const productName = `E2E Quarantine Product ${Date.now()}`;
        const productCreate = await request.post('/api/v1/products', {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: { name: productName, reference: `E2E-QRT-${Date.now()}` },
        });
        const productId = (await productCreate.json()).id as string;
        const supplierCreate = await request.post('/api/v1/suppliers', {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: { name: `E2E Quarantine Supplier ${Date.now()}` },
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
        await request.post(`/api/v1/purchase-orders/${po.id}/order`, {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: {},
        });
        const batchNumber = `E2E-QRT-BATCH-${Date.now()}`;
        const receiptCreate = await request.post(
            `/api/v1/purchase-orders/${po.id}/receipts`,
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                    'Idempotency-Key': crypto.randomUUID(),
                },
                data: {
                    location_id: locationId,
                    lines: [
                        {
                            purchase_order_line_id: po.lines[0].id,
                            batch_number: batchNumber,
                            qty: 5,
                        },
                    ],
                },
            },
        );
        const batchId = (await receiptCreate.json()).lines[0]
            .batch_id as string;

        await request.post(`/api/v1/cycles/${cycleId}/items`, {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: { description: 'Kit with batch', batch_id: batchId },
        });

        await completeDemoCycle(request, token, cycleId);
        await submitDemoCycleForRelease(request, token, cycleId);
        await releaseDemoCycleAsCompliant(request, token, cycleId);
        await createDemoDluRule(request, token, 'Sachet', 'Armoire', 180);
        const [labelId] = await generateDemoLabels(
            request,
            token,
            cycleId,
            'Sachet',
            'Armoire',
        );
        await printDemoLabel(request, token, labelId);

        await loginAsDemoOwner(page);

        const reason = `E2E recall reason ${Date.now()}`;
        await page.goto(`/cycles/${cycleId}`);
        await page.getByRole('button', { name: 'Recall cycle' }).click();
        const dialog = page.getByRole('dialog');
        await dialog.getByLabel('Reason').fill(reason);
        await dialog
            .getByRole('button', { name: /Recall \d|Raise non-conformity/ })
            .click();
        await expect(page.getByText('Non-conformity raised.')).toBeVisible();

        await page.getByRole('link', { name: 'Quarantine' }).click();
        await expect(page).toHaveURL(/\/quarantine$/);
        await expect(
            page.getByRole('heading', { name: 'Quarantine' }),
        ).toBeVisible();

        const row = page.getByRole('row').filter({ hasText: batchNumber });
        await expect(row).toBeVisible();
        await expect(row.getByText(productName)).toBeVisible();
        await expect(row.getByText(reason)).toBeVisible();
        await expect(row.getByText('Admin', { exact: false })).toBeVisible();
    });
});
