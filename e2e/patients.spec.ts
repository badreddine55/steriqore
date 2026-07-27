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
 * Patients are pseudonymous references only (CLAUDE.md) — CreatePatientAction
 * takes no input at all, so "New patient" is a one-click confirm, not a
 * form. Linking a label to a procedure (UI-20) has no web UI yet, so the
 * usage fixture here is built the same way a real practitioner's phone
 * would after scanning: run a cycle through to released+compliant, print
 * a label, scan it (GET, the resolution endpoint transitions
 * printed -> used), then record the usage — all via the mobile API.
 */
test.describe('Patients (UI-19)', () => {
    test('create a patient, then see a linked procedure in its history, then archive it', async ({
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

        const packagingType = 'Sachet';
        const storageCondition = 'Armoire';
        await createDemoDluRule(request, token, packagingType, storageCondition, 180);
        const [labelId] = await generateDemoLabels(
            request,
            token,
            cycleId,
            packagingType,
            storageCondition,
        );
        await printDemoLabel(request, token, labelId);
        // The scan resolution endpoint — transitions printed -> used.
        await request.get(`/api/v1/labels/${labelId}`, {
            headers: { Authorization: `Bearer ${token}` },
        });

        const me = await request.get('/api/v1/me', {
            headers: { Authorization: `Bearer ${token}` },
        });
        const practitionerId = (await me.json()).user.id as string;

        await loginAsDemoOwner(page);

        // The nav item used to be a disabled "Soon" stub.
        await page.getByRole('link', { name: 'Patients' }).click();
        await expect(page).toHaveURL(/\/patients$/);
        await expect(
            page.getByRole('heading', { name: 'Patients' }),
        ).toBeVisible();

        await page.getByRole('button', { name: 'New patient' }).click();
        await expect(page).toHaveURL(/\/patients\/[0-9a-f-]+$/);
        await expect(
            page.getByText('No procedures recorded yet', { exact: false }),
        ).toBeVisible();

        const patientId = page.url().split('/').pop() as string;

        const procedure = `E2E Procedure ${Date.now()}`;
        await request.post(`/api/v1/labels/${labelId}/usage`, {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: {
                patient_id: patientId,
                practitioner_id: practitionerId,
                procedure,
            },
        });

        await page.reload();
        await expect(page.getByText(procedure)).toBeVisible();
        await expect(page.getByRole('link', { name: 'View label' })).toBeVisible();

        await page.getByRole('button', { name: 'Archive patient' }).click();
        await page
            .getByRole('alertdialog')
            .getByRole('button', { name: 'Archive patient' })
            .click();
        await expect(page).toHaveURL(/\/patients$/);
        await expect(page.getByText('archived', { exact: false })).toBeVisible();
    });
});
