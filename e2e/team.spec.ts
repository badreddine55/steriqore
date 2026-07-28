import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';
import { DEMO_EMAIL } from './support/demo';

/**
 * Team (UI-26) — users, invitations, role assignment, disable (never
 * delete). The demo owner is a real, persistent member of the shared demo
 * tenant, so this spec exercises the invite/revoke lifecycle rather than
 * disabling a member (there's no in-browser way to accept an invitation
 * and create a second real member without email delivery); the disable
 * mutation itself is covered by tests/Feature/Web/TeamTest.php.
 */
test.describe('Team (UI-26)', () => {
    test('inviting and then revoking a member updates the team page', async ({
        page,
    }) => {
        await loginAsDemoOwner(page);
        await page.goto('/team');

        // The demo owner is listed as an active member, and can't disable
        // their own access (self-lockout guard).
        const ownerRow = page.getByRole('row').filter({ hasText: DEMO_EMAIL });
        await expect(ownerRow).toBeVisible();
        await expect(ownerRow.getByText('Active', { exact: true })).toBeVisible();
        await expect(
            ownerRow.getByRole('button', { name: 'Disable access' }),
        ).toHaveCount(0);

        const email = `e2e-invite-${Date.now()}@example.com`;
        await page.getByRole('button', { name: 'Invite member' }).click();
        const inviteDialog = page.getByRole('dialog');
        await inviteDialog.getByLabel('Email').fill(email);
        await inviteDialog.getByLabel('Role').click();
        await page.getByRole('option', { name: 'Practitioner' }).click();
        await inviteDialog
            .getByRole('button', { name: 'Send invitation' })
            .click();

        await expect(page.getByText('Invitation sent.')).toBeVisible();

        const invitationRow = page.getByRole('row').filter({ hasText: email });
        await expect(invitationRow).toBeVisible();
        await expect(
            invitationRow.getByText('Practitioner', { exact: true }),
        ).toBeVisible();
        await expect(
            invitationRow.getByText('Pending', { exact: true }),
        ).toBeVisible();

        await invitationRow.getByRole('button', { name: 'Revoke' }).click();
        await page
            .getByRole('alertdialog')
            .getByRole('button', { name: 'Revoke invitation' })
            .click();

        await expect(page.getByText('Invitation revoked.')).toBeVisible();
        await expect(
            invitationRow.getByText('Revoked', { exact: true }),
        ).toBeVisible();
        await expect(
            invitationRow.getByRole('button', { name: 'Revoke' }),
        ).toHaveCount(0);
    });
});
