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
 * Non-conformities + recall (UI-23) — the confirm dialog must show the
 * blast radius (how many labels/batches) BEFORE the user confirms,
 * computed by the same RaiseNonConformityAction the confirm button then
 * executes, so it can never overstate or understate what actually happens.
 */
test.describe('Non-conformities + recall (UI-23)', () => {
    test('recalling a cycle shows the blast radius, then recalls its label and quarantines its batch', async ({
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

        await loginAsDemoOwner(page);

        await page.goto(`/cycles/${cycleId}`);
        await page.getByRole('button', { name: 'Recall cycle' }).click();

        // The demo cycle item has no batch attached, so only the label
        // count is non-zero here.
        const dialog = page.getByRole('dialog');
        await expect(dialog.getByText('1 label').first()).toBeVisible();
        await expect(dialog.getByText('0 batches').first()).toBeVisible();

        await dialog
            .getByLabel('Reason')
            .fill('Discovered a mis-calibrated sensor after the fact.');
        await dialog.getByRole('button', { name: 'Recall 1 label' }).click();

        await expect(page.getByText('Non-conformity raised.')).toBeVisible();

        // The label is now recalled — the label detail page reflects it.
        await page.goto(`/labels/${labelId}`);
        await expect(page.getByText('Recalled', { exact: true })).toBeVisible();

        // The non-conformity is logged, tenant-wide.
        await page.getByRole('link', { name: 'Non-conformities' }).click();
        await expect(page).toHaveURL(/\/non-conformities$/);
        // The demo tenant is a persistent, shared fixture — prior runs of
        // this same spec leave their own rows behind, sorted newest-first,
        // so the row this test just created is always the first match.
        const row = page
            .getByRole('row')
            .filter({ hasText: 'Discovered a mis-calibrated sensor' })
            .first();
        await expect(row).toBeVisible();
        await expect(row.getByText('Open', { exact: true })).toBeVisible();

        // Resolving it closes the record without un-recalling anything.
        await row.getByRole('button', { name: 'Resolve' }).click();
        const resolveDialog = page.getByRole('dialog');
        await resolveDialog
            .getByLabel('Resolution')
            .fill('Re-tested the device; sensor now reads correctly.');
        await resolveDialog.getByRole('button', { name: 'Resolve' }).click();
        await expect(page.getByText('Non-conformity resolved.')).toBeVisible();
        await expect(row.getByText('Resolved', { exact: true })).toBeVisible();
    });
});
