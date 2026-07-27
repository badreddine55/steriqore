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

test.describe('Labels index and detail (UI-07)', () => {
    test('the Labels nav link works and opens a real label detail page', async ({
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

        // The nav item used to be a disabled "Soon" stub — this proves it
        // now links to a real, working screen.
        await page.getByRole('link', { name: 'Labels' }).click();
        await expect(page).toHaveURL(/\/labels$/);
        await expect(
            page.getByRole('heading', { name: 'Labels' }),
        ).toBeVisible();

        const row = page
            .getByRole('row')
            .filter({ hasText: 'Scaler kit, pouch 1' })
            .first();
        await expect(row).toBeVisible();
        await row.getByRole('link', { name: /#\d+/ }).click();

        await expect(page).toHaveURL(new RegExp(`/labels/${labelId}$`));
        await expect(
            page.getByRole('heading', { name: 'Scaler kit, pouch 1' }),
        ).toBeVisible();
        await expect(page.getByText('Chain')).toBeVisible();
        await expect(page.getByText('Print history')).toBeVisible();
        // Not yet used — the demo owner has usages.manage, so this is now
        // the "link to a procedure" form (UI-20's web scan fallback),
        // not a static "not yet scanned" message.
        await expect(page.getByText('Point-of-care usage')).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Link label to procedure' }),
        ).toBeVisible();

        // Already printed once (via the API fixture above) — the detail
        // page's action is a reprint, requiring a reason, same dialog the
        // label sheet uses.
        await page.getByRole('button', { name: 'Reprint' }).click();
        const dialog = page.getByRole('dialog');
        await dialog.getByLabel('Reason').fill('Original label was damaged.');
        await dialog.getByRole('button', { name: 'Reprint' }).click();
        await expect(page.getByText('Label reprinted.')).toBeVisible();
    });
});
