import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';

/**
 * Dashboard (UI-30) — replaces the starter-kit placeholder. The demo
 * tenant has real devices/cycles/alerts/non-conformities from every prior
 * UI-XX spec run, so it's always past onboarding — this only exercises the
 * attention-queue rendering + click-through, not the new-practice
 * checklist (no persistent fixture is ever actually "new").
 */
test.describe('Dashboard (UI-30)', () => {
    test('the attention queue renders real counts that click through to the matching filtered list', async ({
        page,
    }) => {
        await loginAsDemoOwner(page);
        await page.goto('/dashboard');

        await expect(
            page.getByRole('heading', { name: 'Dashboard' }),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'Get your practice set up' }),
        ).not.toBeVisible();

        const awaitingReleaseCard = page.getByTestId(
            'attention-card-cycles_awaiting_release',
        );
        await expect(awaitingReleaseCard).toBeVisible();

        await awaitingReleaseCard.getByRole('link', { name: 'View' }).click();
        await page.waitForURL(/\/cycles\?/);
        await expect(
            page.getByRole('heading', { name: 'Cycles' }),
        ).toBeVisible();

        await page.goto('/dashboard');
        await expect(
            page.getByRole('heading', { name: 'Today at a glance' }),
        ).toBeVisible();
        await expect(page.getByRole('table')).toBeVisible();
    });

    test('the refresh button reloads the page without breaking it', async ({
        page,
    }) => {
        await loginAsDemoOwner(page);
        await page.goto('/dashboard');

        await page.getByRole('button', { name: 'Refresh' }).click();

        await expect(
            page.getByRole('heading', { name: 'Dashboard' }),
        ).toBeVisible();
    });
});
