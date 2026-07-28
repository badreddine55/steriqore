import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';

/**
 * Practice settings (UI-28) — one page, four sections. Owner-only sections
 * (label format, DLU rule table) are evidence-affecting and versioned;
 * identity and alerts are plain audited updates. Everything here is
 * driven through the UI itself — there's no API to pre-seed fixtures
 * with for most of this (practice identity/label format/alerts are
 * web-only settings).
 */
test.describe('Practice settings (UI-28)', () => {
    test('an owner updates identity, label format, a DLU rule, and alert settings', async ({
        page,
    }) => {
        await loginAsDemoOwner(page);
        await page.goto('/practice-settings');

        // Identity section.
        await page.getByLabel('Legal name (optional)').fill('Cabinet Demo SARL');
        await page.getByLabel('City').fill('Lyon');
        await page
            .getByLabel('Reason', { exact: true })
            .first()
            .fill('Correcting the legal name.');
        await page.getByRole('button', { name: 'Save identity' }).click();
        await expect(
            page.getByText('Practice identity updated.'),
        ).toBeVisible();

        // Label format section — confirm dialog shows the consequence and
        // requires its own reason. The demo tenant is shared across runs,
        // so pick whichever option isn't already selected rather than a
        // fixed one, or the "Save changes" button never becomes enabled.
        await page.getByRole('tab', { name: 'Label format' }).click();
        const primaryCodeField = page.getByLabel('Primary code');
        const currentPrimaryCode = await primaryCodeField.textContent();
        const nextPrimaryCode = currentPrimaryCode?.trim() === 'QR' ? 'DataMatrix' : 'QR';
        await primaryCodeField.click();
        await page.getByRole('option', { name: nextPrimaryCode }).click();
        await page.getByRole('button', { name: 'Save changes' }).click();
        const formatDialog = page.getByRole('dialog');
        await expect(
            formatDialog.getByText('Save the new label format?'),
        ).toBeVisible();
        await formatDialog
            .getByLabel('Reason')
            .fill('Practice prefers QR as primary.');
        await formatDialog
            .getByRole('button', { name: 'Save label format' })
            .click();
        await expect(
            page.getByText(
                'Label format updated. Applies to labels printed from now on.',
            ),
        ).toBeVisible();

        // DLU rule table section — add then edit a rule, both requiring a
        // reason, and the edit dialog states the never-retroactive rule.
        await page.getByRole('tab', { name: 'DLU rule table' }).click();
        const packagingType = `E2E Pouch ${Date.now()}`;
        await page.getByRole('button', { name: 'Add rule' }).click();
        const addRuleDialog = page.getByRole('dialog');
        await addRuleDialog
            .getByLabel('Packaging type')
            .fill(packagingType);
        await addRuleDialog
            .getByLabel('Storage condition')
            .fill('Armoire fermée');
        await addRuleDialog.getByLabel('Shelf life (days)').fill('180');
        await addRuleDialog
            .getByLabel('Reason')
            .fill('EN 868 starting point for pouches.');
        await addRuleDialog.getByRole('button', { name: 'Add rule' }).click();

        const ruleRow = page.getByRole('row').filter({ hasText: packagingType });
        await expect(ruleRow).toBeVisible();
        await expect(ruleRow.getByText('180 days')).toBeVisible();

        await ruleRow.getByRole('button', { name: /^Edit/ }).click();
        const editRuleDialog = page.getByRole('dialog');
        await expect(
            editRuleDialog.getByText('No labels have used this rule yet.'),
        ).toBeVisible();
        await editRuleDialog.getByLabel('Shelf life (days)').fill('365');
        await editRuleDialog
            .getByLabel('Reason')
            .fill('Extended after review.');
        await editRuleDialog
            .getByRole('button', { name: 'Save changes' })
            .click();
        await expect(ruleRow.getByText('365 days')).toBeVisible();

        // Alerts & notifications section.
        await page.getByRole('tab', { name: 'Alerts & notifications' }).click();
        await page.getByLabel('Near-expiry window (days)').fill('45');
        await page
            .getByLabel('Reason', { exact: true })
            .first()
            .fill('Practice wants more lead time.');
        await page.getByRole('button', { name: 'Save thresholds' }).click();
        await expect(page.getByText('Alert settings updated.')).toBeVisible();
    });
});
