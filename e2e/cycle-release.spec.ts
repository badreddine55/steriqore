import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';
import {
    addDemoCycleItem,
    completeDemoCycle,
    createDemoCycle,
    createDemoDevice,
    mintDemoApiToken,
    submitDemoCycleForRelease,
} from './support/demo';

test.describe('Cycle release (UI-05)', () => {
    test('release a cycle as compliant', async ({ page, request }) => {
        const token = await mintDemoApiToken(request);
        const deviceId = await createDemoDevice(request, token);
        const cycleId = await createDemoCycle(request, token, deviceId);
        await addDemoCycleItem(request, token, cycleId, 'Scaler kit, pouch 1');
        await completeDemoCycle(request, token, cycleId);
        await submitDemoCycleForRelease(request, token, cycleId);

        await loginAsDemoOwner(page);
        await page.goto(`/cycles/${cycleId}`);

        await expect(
            page.getByText(
                'This cycle is awaiting release. Both outcomes are permanent evidence.',
            ),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Release as compliant' }).click();

        await expect(
            page.getByText(`Cycle #1 released as compliant.`),
        ).toBeVisible();
        await expect(page.getByText('Release decision')).toBeVisible();
        // exact: true — the toast "Cycle #1 released as compliant." also
        // contains this substring.
        await expect(
            page.getByText('Compliant', { exact: true }),
        ).toBeVisible();

        // A released cycle is terminal — no more decision to make.
        await expect(
            page.getByRole('button', { name: 'Release as compliant' }),
        ).toHaveCount(0);
        await expect(
            page.getByRole('button', { name: 'Reject cycle' }),
        ).toHaveCount(0);
    });

    test('reject a cycle with a required reason', async ({
        page,
        request,
    }) => {
        const token = await mintDemoApiToken(request);
        const deviceId = await createDemoDevice(request, token);
        const cycleId = await createDemoCycle(request, token, deviceId);
        await addDemoCycleItem(request, token, cycleId, 'Scaler kit, pouch 1');
        await completeDemoCycle(request, token, cycleId);
        await submitDemoCycleForRelease(request, token, cycleId);

        await loginAsDemoOwner(page);
        await page.goto(`/cycles/${cycleId}`);

        await page.getByRole('button', { name: 'Reject cycle' }).click();

        // The dialog's own submit button is disabled until a reason is typed.
        const dialog = page.getByRole('dialog');
        const submitButton = dialog.getByRole('button', {
            name: 'Reject cycle',
        });
        await expect(submitButton).toBeDisabled();

        await page
            .getByLabel('Reason')
            .fill('Bowie-Dick control test failed.');
        await expect(submitButton).toBeEnabled();
        await submitButton.click();

        await expect(page.getByText(`Cycle #1 rejected.`)).toBeVisible();
        // "Rejected" also appears inside the timeline's terminal station
        // label ("Released / Rejected") — the release decision Card's own
        // badge is the last match in DOM order, since the timeline renders
        // above it.
        await expect(page.getByText('Rejected').last()).toBeVisible();
        await expect(
            page.getByText('Bowie-Dick control test failed.', {
                exact: false,
            }),
        ).toBeVisible();
    });
});
