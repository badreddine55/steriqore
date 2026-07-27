import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';
import {
    addDemoCycleItem,
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
 * Evidence search (UI-21, REC-09) — reuses SearchEvidenceAction exactly
 * as the mobile API does. Builds a full pouch -> cycle -> device ->
 * practitioner -> procedure -> patient chain via the API, then finds it
 * by each filter dimension on the web: patient, cycle, batch, device,
 * operator, period.
 */
test.describe('Evidence search (UI-21)', () => {
    test('find a full evidence chain by patient reference, then narrow by cycle and operator', async ({
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
        const [labelId] = await generateDemoLabels(
            request,
            token,
            cycleId,
            'Sachet',
            'Armoire',
        );
        await printDemoLabel(request, token, labelId);
        // The scan resolution endpoint — transitions printed -> used.
        await request.get(`/api/v1/labels/${labelId}`, {
            headers: { Authorization: `Bearer ${token}` },
        });

        const me = await request.get('/api/v1/me', {
            headers: { Authorization: `Bearer ${token}` },
        });
        const operatorId = (await me.json()).user.id as string;

        const patientCreate = await request.post('/api/v1/patients', {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: {},
        });
        const patient = await patientCreate.json();

        const procedure = `E2E Evidence Procedure ${Date.now()}`;
        await request.post(`/api/v1/labels/${labelId}/usage`, {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: {
                patient_id: patient.id,
                practitioner_id: operatorId,
                procedure,
            },
        });

        const cycleResponse = await request.get(
            `/api/v1/cycles/${cycleId}`,
            { headers: { Authorization: `Bearer ${token}` } },
        );
        const cycleNumber = (await cycleResponse.json()).cycle_number as number;

        await loginAsDemoOwner(page);

        // The nav item used to be a disabled "Soon" stub.
        await page.getByRole('link', { name: 'Evidence search' }).click();
        await expect(page).toHaveURL(/\/evidence-search$/);
        await expect(
            page.getByRole('heading', { name: 'Evidence search' }),
        ).toBeVisible();

        await page
            .getByLabel('Patient reference')
            .fill(patient.reference);
        const row = page.getByRole('row').filter({ hasText: procedure });
        await expect(row).toBeVisible();
        await expect(row.getByText(patient.reference)).toBeVisible();
        await expect(row.getByText(`#${cycleNumber}`)).toBeVisible();

        // Narrowing further, by cycle number, still finds it.
        await page.getByLabel('Patient reference').fill('');
        await page.getByLabel('Cycle number').fill(String(cycleNumber));
        await expect(
            page.getByRole('row').filter({ hasText: procedure }),
        ).toBeVisible();

        // A cycle number with no match returns the honest empty state.
        await page.getByLabel('Cycle number').fill('999999');
        await expect(
            page.getByText('No evidence matches these filters.'),
        ).toBeVisible();
    });
});
