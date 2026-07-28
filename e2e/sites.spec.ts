import { expect, test } from '@playwright/test';
import { loginAsDemoOwner } from './support/auth';

/**
 * Sites, rooms, storage locations (UI-27) — the tenant -> sites -> rooms ->
 * storage_locations hierarchy every other domain hangs off. Everything is
 * driven through the UI itself (unlike most other specs, there's no mobile
 * API to pre-seed fixtures with — Sites has no API create route at all,
 * by design: it's an admin-only web settings screen).
 */
test.describe('Sites, rooms, storage locations (UI-27)', () => {
    test('create a site, add a room and a location, then archive the location and the room', async ({
        page,
    }) => {
        await loginAsDemoOwner(page);

        const siteName = `E2E Site ${Date.now()}`;
        await page.goto('/sites');
        await page.getByRole('button', { name: 'New site' }).click();
        const newSiteDialog = page.getByRole('dialog');
        await newSiteDialog.getByLabel('Name').fill(siteName);
        await newSiteDialog
            .getByRole('button', { name: 'Add site' })
            .click();

        await expect(page).toHaveURL(/\/sites\/[0-9a-f-]+$/);
        await expect(
            page.getByRole('heading', { name: siteName }),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Add room' }).click();
        const newRoomDialog = page.getByRole('dialog');
        await newRoomDialog.getByLabel('Name').fill('Sterilization room');
        await newRoomDialog
            .getByRole('button', { name: 'Add room' })
            .click();

        const roomSection = page.getByRole('region', {
            name: 'Room: Sterilization room',
        });
        await expect(roomSection).toBeVisible();

        await roomSection.getByRole('button', { name: 'Add location' }).click();
        const newLocationDialog = page.getByRole('dialog');
        await newLocationDialog.getByLabel('Name').fill('Cupboard A');
        await newLocationDialog
            .getByRole('button', { name: 'Add and add another' })
            .click();
        // The dialog stays open (for adding several in a row) and hides
        // the rest of the page from the accessibility tree while it's up.
        await expect(
            newLocationDialog.getByRole('button', {
                name: 'Add and add another',
            }),
        ).toBeDisabled();
        await newLocationDialog
            .getByRole('button', { name: 'Finish' })
            .click();

        const locationItem = page.getByRole('listitem', {
            name: 'Storage location: Cupboard A',
        });
        await expect(locationItem).toBeVisible();

        // Rename the location.
        await locationItem
            .getByRole('button', { name: 'Edit Cupboard A' })
            .click();
        const editLocationDialog = page.getByRole('dialog');
        await editLocationDialog.getByLabel('Name').fill('Cupboard B');
        await editLocationDialog
            .getByRole('button', { name: 'Save changes' })
            .click();

        const renamedLocationItem = page.getByRole('listitem', {
            name: 'Storage location: Cupboard B',
        });
        await expect(renamedLocationItem).toBeVisible();

        // Archiving requires a reason and states the consequence.
        await renamedLocationItem
            .getByRole('button', { name: 'Archive Cupboard B' })
            .click();
        const archiveLocationDialog = page.getByRole('dialog');
        await expect(
            archiveLocationDialog.getByText('Archive Cupboard B?'),
        ).toBeVisible();
        await archiveLocationDialog
            .getByLabel('Reason')
            .fill('No longer used.');
        await archiveLocationDialog
            .getByRole('button', { name: 'Archive location' })
            .click();

        await expect(renamedLocationItem).toHaveCount(0);
        await expect(
            roomSection.getByText('No storage locations in this room yet.'),
        ).toBeVisible();

        // Archiving the room states the (now zero) cascade count.
        await roomSection
            .getByRole('button', { name: 'Archive Sterilization room' })
            .click();
        const archiveRoomDialog = page.getByRole('dialog');
        await expect(
            archiveRoomDialog.getByText(
                'This room has no storage locations under it.',
            ),
        ).toBeVisible();
        await archiveRoomDialog.getByLabel('Reason').fill('Room repurposed.');
        await archiveRoomDialog
            .getByRole('button', { name: 'Archive room' })
            .click();

        await expect(roomSection).toHaveCount(0);
        await expect(
            page.getByText(
                'No rooms yet — add one to start placing storage locations.',
            ),
        ).toBeVisible();

        // Back on the index, the site is still there (archiving a room
        // doesn't archive the site itself).
        await page.goto('/sites');
        await expect(
            page.getByRole('row').filter({ hasText: siteName }),
        ).toBeVisible();
    });
});
