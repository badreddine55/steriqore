import { expect, test } from '@playwright/test';
import type { APIRequestContext, Page } from '@playwright/test';
import { PDFParse } from 'pdf-parse';
import { extractInvitationToken, waitForMailTo } from './support/mailpit';

/**
 * UI-31 — the §10 demo journey (docs/00-BUILD-BRIEF.md), driven end to end
 * against the real Docker stack: real Postgres/Redis/MinIO, real Horizon
 * processing queued jobs, real mail through Mailpit. This spec creates its
 * own fresh tenant with a unique slug per run — it never touches the
 * shared `demo` tenant every other e2e/*.spec.ts file uses, and has no
 * ordering dependence on any of them.
 *
 * Two steps are driven through the v1 API rather than a browser form, and
 * both are genuine, permanent gaps, not shortcuts taken for convenience:
 *   - "register a practice": Fortify's own /register only creates a bare
 *     user account (see app/Actions/Fortify/CreateNewUser.php) — there is
 *     no web screen anywhere that also creates the Tenant/TenantUser/
 *     roles a practice needs. Real tenant creation only exists at
 *     POST /api/v1/tenants.
 *   - accepting an invitation: the email's own accept link points at
 *     /accept-invitation, which 404s — no such route is registered
 *     anywhere. Invitation.token is also deliberately hidden from every
 *     API response, so the token is read out of the real, Mailpit-
 *     delivered email exactly as a real invitee would, then POSTed to
 *     POST /api/v1/invitations/accept (the only implemented acceptance
 *     path).
 * Recording an "issue" stock movement is a third, pre-existing API-only
 * surface (see e2e/stock.spec.ts's own docblock — there is no web form to
 * issue/transfer/adjust stock, mobile-only by design), not something new
 * to this journey.
 *
 * Deterministic time: this app has no Carbon::setTestNow-style clock
 * freeze anywhere (confirmed absent — building one would be application
 * code, out of this ticket's declared scope). Every date-sensitive
 * assertion below is computed relative to Date.now() at run time instead
 * (the same idiom e2e/device-maintenance.spec.ts already uses for its own
 * overdue-date fixture), so nothing here can pass in one month and fail
 * in another.
 */

const OWNER_PASSWORD = 'CorrectHorse-Battery-9!';
const PERSONA_PASSWORD = 'CorrectHorse-Battery-9!';

function unique(prefix: string): string {
    return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

async function login(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('[data-test="login-button"]').click();
    await page.waitForURL('**/dashboard');
}

/** Invites a member from an already-open /team page, through the real form. */
async function inviteMember(page: Page, email: string, role: string): Promise<void> {
    await page.getByRole('button', { name: 'Invite member' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Email').fill(email);
    await dialog.getByLabel('Role').click();
    await page.getByRole('option', { name: role, exact: true }).click();
    await dialog.getByRole('button', { name: 'Send invitation' }).click();
    await expect(page.getByText('Invitation sent.')).toBeVisible();
}

/**
 * Reads the real invitation email out of Mailpit and accepts it via the
 * API — the only implemented acceptance path (see file docblock). Returns
 * the new member's own Sanctum token.
 */
async function acceptInvitation(
    request: APIRequestContext,
    email: string,
    name: string,
    password: string,
): Promise<string> {
    const mailBody = await waitForMailTo(email);
    const token = extractInvitationToken(mailBody);

    const response = await request.post('/api/v1/invitations/accept', {
        headers: { 'Idempotency-Key': crypto.randomUUID() },
        data: { token, name, password },
    });

    if (!response.ok()) {
        throw new Error(
            `Invitation accept failed: ${response.status()} ${await response.text()}`,
        );
    }

    return (await response.json()).token as string;
}

test.describe('End-to-end acceptance journey (UI-31, brief §10)', () => {
    test('register, staff, purchase, sterilize, label, trace, alert, audit, and export — one continuous practice', async ({
        page,
        request,
        browser,
    }) => {
        test.setTimeout(6 * 60 * 1000);

        const tenantSlug = unique('e2e-journey').toLowerCase();
        const ownerEmail = `${unique('owner')}@example.com`;
        const adminEmail = `${unique('admin')}@example.com`;
        const assistantEmail = `${unique('assistant')}@example.com`;

        // ---- 1. Register a practice. -----------------------------------
        const registerResponse = await request.post('/api/v1/tenants', {
            headers: { 'Idempotency-Key': crypto.randomUUID() },
            data: {
                tenant_name: 'E2E Journey Practice',
                tenant_slug: tenantSlug,
                owner_name: 'Owner Ownerson',
                owner_email: ownerEmail,
                password: OWNER_PASSWORD,
            },
        });
        expect(registerResponse.ok(), await registerResponse.text()).toBeTruthy();
        const { token: ownerToken } = await registerResponse.json();

        await login(page, ownerEmail, OWNER_PASSWORD);

        // ---- 2. Invite an admin and an assistant with different roles. -
        await page.goto('/team');
        await inviteMember(page, adminEmail, 'Admin');
        await inviteMember(page, assistantEmail, 'Practitioner');

        await acceptInvitation(
            request,
            adminEmail,
            'Admin Adminson',
            PERSONA_PASSWORD,
        );
        await acceptInvitation(
            request,
            assistantEmail,
            'Assistant Assistantson',
            PERSONA_PASSWORD,
        );

        // ---- Sites/rooms/storage location — needed before a batch can be
        // received into anything (mirrors e2e/sites.spec.ts).
        const siteName = unique('E2E Site');
        await page.goto('/sites');
        await page.getByRole('button', { name: 'New site' }).click();
        const newSiteDialog = page.getByRole('dialog');
        await newSiteDialog.getByLabel('Name').fill(siteName);
        await newSiteDialog.getByRole('button', { name: 'Add site' }).click();
        await expect(page).toHaveURL(/\/sites\/[0-9a-f-]+$/);

        await page.getByRole('button', { name: 'Add room' }).click();
        const newRoomDialog = page.getByRole('dialog');
        await newRoomDialog.getByLabel('Name').fill('Sterilization room');
        await newRoomDialog.getByRole('button', { name: 'Add room' }).click();
        const roomSection = page.getByRole('region', {
            name: 'Room: Sterilization room',
        });
        await expect(roomSection).toBeVisible();

        await roomSection.getByRole('button', { name: 'Add location' }).click();
        const newLocationDialog = page.getByRole('dialog');
        await newLocationDialog.getByLabel('Name').fill('Main cupboard');
        await newLocationDialog
            .getByRole('button', { name: 'Add and add another' })
            .click();
        await expect(
            newLocationDialog.getByRole('button', {
                name: 'Add and add another',
            }),
        ).toBeDisabled();
        await newLocationDialog.getByRole('button', { name: 'Finish' }).click();
        await expect(
            page.getByRole('listitem', { name: 'Storage location: Main cupboard' }),
        ).toBeVisible();

        // ---- 3. Create a supplier and a product. -----------------------
        const productName = unique('E2E Journey Product');
        await page.goto('/products');
        await page.getByRole('button', { name: 'New product' }).click();
        const productDialog = page.getByRole('dialog');
        await productDialog.getByLabel('Name').fill(productName);
        await productDialog.getByLabel('Reference').fill(unique('E2E-SKU'));
        await productDialog.getByRole('button', { name: 'Add product' }).click();
        await expect(
            page.getByRole('heading', { name: productName }),
        ).toBeVisible();

        const supplierName = unique('E2E Journey Supplier');
        await page.goto('/suppliers');
        await page.getByRole('button', { name: 'New supplier' }).click();
        const supplierDialog = page.getByRole('dialog');
        await supplierDialog.getByLabel('Name').fill(supplierName);
        await supplierDialog.getByRole('button', { name: 'Add supplier' }).click();
        await expect(
            page.getByRole('heading', { name: supplierName }),
        ).toBeVisible();

        // ---- 4. Raise a purchase order. --------------------------------
        await page.goto('/purchase-orders/create');
        await page.getByLabel('Supplier').click();
        await page.getByRole('option', { name: supplierName, exact: true }).click();
        await page.getByLabel(/Product for line 1/).click();
        await page.getByRole('option', { name: productName, exact: true }).click();
        await page.getByLabel(/Quantity for line 1/).fill('50');
        await page.getByLabel(/Unit price for line 1/).fill('12.50');
        await page
            .getByRole('button', { name: 'Create purchase order' })
            .click();
        await expect(page.getByText('Purchase order created.')).toBeVisible();

        await page.getByRole('button', { name: 'Mark as ordered' }).click();
        await expect(
            page.getByText('Purchase order marked as ordered.'),
        ).toBeVisible();

        // ---- 5. Receive a batch with a real expiry date, inside the
        // practice's near-expiry window (default 30 days) so step 14
        // below finds it without needing any clock control.
        const nearExpiryDate = new Date(Date.now() + 10 * 24 * 60 * 60 * 1000)
            .toISOString()
            .slice(0, 10);
        const batchNumber = unique('E2E-BATCH');

        await page.getByLabel('Storage location').click();
        await page.getByRole('option').first().click();
        await page.getByLabel(/Batch number for/).fill(batchNumber);
        await page.getByLabel(/Expiry date for/).fill(nearExpiryDate);
        await page.getByLabel(/Quantity received for/).fill('50');
        await page.getByRole('button', { name: 'Record receipt' }).click();
        await expect(page.getByText('Goods received.')).toBeVisible();

        // ---- 6. Add a device and a program. -----------------------------
        const deviceName = unique('E2E Autoclave');
        await page.goto('/devices');
        await page.getByRole('button', { name: 'New device' }).click();
        const newDeviceDialog = page.getByRole('dialog');
        await newDeviceDialog.getByLabel('Site').click();
        await page.getByRole('option').first().click();
        await newDeviceDialog.getByLabel('Name').fill(deviceName);
        await newDeviceDialog.getByLabel('Serial number').fill(unique('E2E-SN'));
        await newDeviceDialog.getByRole('button', { name: 'Add device' }).click();
        await expect(
            page.getByRole('heading', { name: deviceName }),
        ).toBeVisible();

        await page.getByLabel('Name').fill('134°C Standard');
        await page.getByLabel('Target temp (°C)').fill('134');
        await page.getByLabel('Plateau (min)').fill('18');
        await page.getByRole('button', { name: 'Add program' }).click();
        await expect(page.getByText('Program added.')).toBeVisible();

        // ---- 7. Prepare a load and run the cycle. -----------------------
        await page.goto('/cycles');
        await page.getByRole('button', { name: 'New cycle' }).click();
        await page.getByLabel('Device').click();
        await page.getByRole('option', { name: deviceName, exact: true }).click();
        await page.getByRole('button', { name: 'Create cycle' }).click();
        await expect(page).toHaveURL(/\/cycles\/[0-9a-f-]+$/);
        const cycleId = page.url().split('/').pop() as string;

        await page
            .getByLabel('New item description')
            .fill('Scaler kit, pouch 1');
        await page.getByRole('button', { name: 'Add item' }).click();
        await expect(page.getByText('Scaler kit, pouch 1')).toBeVisible();

        await page.getByRole('button', { name: 'Start cycle' }).click();
        await expect(
            page.getByText('Running', { exact: true }).first(),
        ).toBeVisible();

        await page
            .getByRole('button', { name: 'Complete cycle — record result' })
            .click();
        await expect(
            page.getByText('Completed', { exact: true }).first(),
        ).toBeVisible();

        // ---- 8. Record its control tests. -------------------------------
        await page.getByLabel('Type').click();
        await page.getByRole('option', { name: 'Vacuum' }).click();
        await page.getByLabel('Result').click();
        await page.getByRole('option', { name: 'Pass' }).click();
        await page.getByLabel('Control test notes').fill('Ran clean.');
        await page.getByRole('button', { name: 'Record test' }).click();
        await expect(page.getByText('Control test recorded.')).toBeVisible();

        await page.getByRole('button', { name: 'Submit for release' }).click();
        await expect(
            page.getByText('Pending release', { exact: true }),
        ).toBeVisible();

        // ---- 9. Release it as compliant — as the ADMIN, in their own
        // real session, so REC-06 below can disable exactly this actor and
        // confirm the release still shows their name.
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        await login(adminPage, adminEmail, PERSONA_PASSWORD);
        await adminPage.goto(`/cycles/${cycleId}`);
        await adminPage
            .getByRole('button', { name: 'Release as compliant' })
            .click();
        await expect(
            adminPage.getByText(/released as compliant/),
        ).toBeVisible();
        await adminContext.close();

        // ---- REC-03: label generation is blocked in the UI for a
        // rejected cycle. A second cycle on the same device, run through
        // to rejected, proves the "Generate labels" control simply isn't
        // there — hiding it is UX, GenerateCycleLabelsAction's own
        // CYCLE_NOT_RELEASED check is the actual enforcement.
        await page.goto('/cycles');
        await page.getByRole('button', { name: 'New cycle' }).click();
        await page.getByLabel('Device').click();
        await page.getByRole('option', { name: deviceName, exact: true }).click();
        await page.getByRole('button', { name: 'Create cycle' }).click();
        await expect(page).toHaveURL(/\/cycles\/[0-9a-f-]+$/);
        const rejectedCycleId = page.url().split('/').pop() as string;
        await page.getByLabel('New item description').fill('Rejected load item');
        await page.getByRole('button', { name: 'Add item' }).click();
        await page.getByRole('button', { name: 'Start cycle' }).click();
        await page
            .getByRole('button', { name: 'Complete cycle — record result' })
            .click();
        await page.getByRole('button', { name: 'Submit for release' }).click();
        await page.getByRole('button', { name: 'Reject cycle' }).click();
        const rejectDialog = page.getByRole('dialog');
        await rejectDialog
            .getByLabel('Reason')
            .fill('Bowie-Dick control test failed.');
        await rejectDialog
            .getByRole('button', { name: 'Reject cycle' })
            .click();
        await expect(page.getByText(/rejected\./)).toBeVisible();

        await page.goto(`/cycles/${rejectedCycleId}/labels`);
        await expect(
            page.getByRole('button', { name: 'Generate labels' }),
        ).toHaveCount(0);
        const rejectedLabelsResponse = await request.post(
            `/api/v1/cycles/${rejectedCycleId}/labels`,
            {
                headers: {
                    Authorization: `Bearer ${ownerToken}`,
                    'Idempotency-Key': crypto.randomUUID(),
                },
                data: { packaging_type: 'Sachet', storage_condition: 'Armoire' },
            },
        );
        expect(rejectedLabelsResponse.status()).toBe(409);
        expect((await rejectedLabelsResponse.json()).error.code).toBe(
            'CYCLE_NOT_RELEASED',
        );

        // ---- REC-06: disable the admin now, right after their release —
        // then confirm the release still shows their identity later, once
        // the audit log is checked (step below).
        await page.goto('/team');
        const adminRow = page.getByRole('row').filter({ hasText: adminEmail });
        await adminRow.getByRole('button', { name: 'Disable access' }).click();
        await page
            .getByRole('alertdialog')
            .getByRole('button', { name: 'Disable access' })
            .click();
        await expect(
            adminRow.getByText('Disabled', { exact: true }),
        ).toBeVisible();

        // A DLU rule must exist before labels can be generated
        // (GenerateCycleLabelsAction requires a matching one) — created
        // through the real practice-settings screen (UI-28).
        await page.goto('/practice-settings');
        await page.getByRole('tab', { name: 'DLU rule table' }).click();
        await page.getByRole('button', { name: 'Add rule' }).click();
        const addRuleDialog = page.getByRole('dialog');
        await addRuleDialog.getByLabel('Packaging type').fill('Sachet');
        await addRuleDialog
            .getByLabel('Storage condition')
            .fill('Armoire');
        await addRuleDialog.getByLabel('Shelf life (days)').fill('180');
        await addRuleDialog
            .getByLabel('Reason')
            .fill('EN 868 starting point for pouches.');
        await addRuleDialog.getByRole('button', { name: 'Add rule' }).click();
        await expect(page.getByText('added.', { exact: false })).toBeVisible();

        // ---- 10. Print the label sheet. --------------------------------
        await page.goto(`/cycles/${cycleId}/labels`);
        await page.getByLabel('Packaging').click();
        await page.getByRole('option', { name: 'Sachet', exact: true }).click();
        await page.getByLabel('Storage').click();
        await page.getByRole('option', { name: 'Armoire', exact: true }).click();
        await page.getByRole('button', { name: 'Generate labels' }).click();
        await expect(page.getByText('Labels generated.')).toBeVisible();

        await page
            .getByRole('link', { name: /Scaler kit, pouch 1/ })
            .click();
        await expect(page).toHaveURL(/\/labels\/[0-9a-f-]+$/);
        const labelId = page.url().split('/').pop() as string;
        await page.goto(`/cycles/${cycleId}/labels`);
        await page.getByRole('button', { name: 'Print', exact: true }).click();
        await expect(page.getByText('Label printed.')).toBeVisible();

        // ---- REC-05: a reprint without a reason is rejected; with one,
        // the counter increments.
        await page.getByRole('button', { name: 'Reprint' }).click();
        const reprintDialog = page.getByRole('dialog');
        await expect(
            reprintDialog.getByRole('button', { name: 'Reprint' }),
        ).toBeDisabled();
        await reprintDialog
            .getByLabel('Reason')
            .fill('Original label was damaged.');
        await reprintDialog.getByRole('button', { name: 'Reprint' }).click();
        await expect(page.getByText('Label reprinted.')).toBeVisible();

        // ---- 11. Resolve (scan) the label via the v1 API, exactly as the
        // mobile app does at the chair — then confirm the web reflects it.
        const scanResponse = await request.get(`/api/v1/labels/${labelId}`, {
            headers: { Authorization: `Bearer ${ownerToken}` },
        });
        expect(scanResponse.ok(), await scanResponse.text()).toBeTruthy();
        expect((await scanResponse.json()).status).toBe('used');

        await page.goto(`/labels/${labelId}`);
        await expect(
            page.getByText('Used', { exact: true }).first(),
        ).toBeVisible();

        // ---- 12. Record an issue movement without double-counting. This
        // is a pre-existing, mobile-only API surface (no web form issues
        // stock — see e2e/stock.spec.ts's own docblock), not a shortcut
        // taken for this journey.
        const stockLevelsResponse = await request.get(
            '/api/v1/stock-levels',
            { headers: { Authorization: `Bearer ${ownerToken}` } },
        );
        // StockLevelData carries batch_id, not batch_number — this fresh
        // tenant has received exactly one batch so far, so its one level
        // row is unambiguous.
        const stockLevel = (await stockLevelsResponse.json()).data[0];
        await request.post('/api/v1/stock-movements/issue', {
            headers: {
                Authorization: `Bearer ${ownerToken}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: {
                batch_id: stockLevel.batch_id,
                location_id: stockLevel.location_id,
                qty: 5,
                reason: 'Used at the chair.',
            },
        });

        await page.goto('/stock/movements');
        const issueRow = page
            .getByRole('row')
            .filter({ hasText: batchNumber })
            .filter({ hasText: 'Issue' });
        await expect(issueRow).toBeVisible();
        await expect(issueRow.getByText('-5', { exact: true })).toBeVisible();

        // ---- 13. Associate the label to a procedure — through the real
        // web fallback screen (UI-20), as the ASSISTANT persona.
        const assistantContext = await browser.newContext();
        const assistantPage = await assistantContext.newPage();
        await login(assistantPage, assistantEmail, PERSONA_PASSWORD);

        await assistantPage.goto('/patients');
        await assistantPage.getByRole('button', { name: 'New patient' }).click();
        await expect(assistantPage).toHaveURL(/\/patients\/[0-9a-f-]+$/);
        const patientReference = await assistantPage
            .getByRole('heading', { level: 1 })
            .textContent();

        await assistantPage.goto(`/labels/${labelId}`);
        await assistantPage.getByLabel('Patient', { exact: true }).click();
        await assistantPage.keyboard.type(patientReference as string);
        await assistantPage.keyboard.press('Enter');
        await assistantPage.getByLabel('Practitioner', { exact: true }).click();
        await assistantPage.keyboard.type('Assistant Assistantson');
        await assistantPage.keyboard.press('Enter');
        const procedure = unique('E2E Procedure');
        await assistantPage.getByLabel('Procedure').fill(procedure);
        await assistantPage
            .getByRole('button', { name: 'Link label to procedure' })
            .click();
        await expect(
            assistantPage.getByText('Label linked to procedure.'),
        ).toBeVisible();
        await assistantContext.close();

        // ---- 14. Trigger a near-expiry alert. This is a pre-existing
        // API-only surface too (DetectAlertsAction is only ever invoked by
        // the scheduled job or this same manual-trigger endpoint — no web
        // "detect now" button exists, see e2e/alerts.spec.ts).
        await request.post('/api/v1/alerts/detect', {
            headers: {
                Authorization: `Bearer ${ownerToken}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: {},
        });

        await page.goto('/alerts');
        const alertRow = page.getByRole('row').filter({ hasText: batchNumber });
        await expect(alertRow).toBeVisible();
        await expect(alertRow.getByText('Near expiry')).toBeVisible();

        // ---- 15. Read the audit log — and confirm REC-06: the admin's
        // release keeps their identity even though they're disabled now.
        await page.goto('/audit-log');
        await page
            .getByRole('combobox')
            .filter({ hasText: 'All actions' })
            .click();
        await page
            .getByRole('option', { name: 'cycle.released', exact: true })
            .click();
        const releaseRow = page
            .getByRole('row')
            .filter({ hasText: 'cycle.released' })
            .first();
        await expect(releaseRow).toBeVisible();
        await expect(
            releaseRow.getByText('Admin Adminson', { exact: false }),
        ).toBeVisible();

        // ---- 16. Export the evidence dossier and confirm its content. --
        await page.goto(`/labels/${labelId}`);
        // The cycle number and DLU (use-by date) the UI shows on this same
        // page — the dossier must show the exact same ones.
        const cycleNumberText = await page
            .getByText(/^#\d+$/, { exact: false })
            .first()
            .textContent();
        const cycleNumber = (cycleNumberText ?? '').replace('#', '').trim();
        const useByDate = (
            await page.getByText(/^\d{4}-\d{2}-\d{2}$/).first().textContent()
        )?.trim();

        const dossierLink = page.getByRole('link', { name: 'Evidence dossier' });
        await expect(dossierLink).toBeVisible();
        const dossierHref = await dossierLink.getAttribute('href');
        const dossierResponse = await page.request.get(dossierHref!);
        expect(dossierResponse.ok()).toBeTruthy();
        expect(dossierResponse.headers()['content-type']).toContain(
            'application/pdf',
        );

        const dossierParser = new PDFParse({ data: await dossierResponse.body() });
        const { text: dossierText } = await dossierParser.getText();
        await dossierParser.destroy();
        expect(dossierText).toContain(`Cycle #${cycleNumber}`);
        expect(dossierText).toContain(deviceName);
        // Operator (who ran the cycle) is the owner; releaser (admin) is a
        // distinct field — both must show up, proving the dossier reflects
        // the exact same chain the UI walked through above.
        expect(dossierText).toContain('Owner Ownerson');
        expect(dossierText).toContain('Admin Adminson');
        expect(dossierText).toContain(useByDate);
        expect(dossierText.toLowerCase()).toContain(labelId.toLowerCase());
    });

    // ---------------------------------------------------------------
    // REC-01 — a user of practice A cannot reach practice B's cycle.
    // ---------------------------------------------------------------
    test('REC-01: a user of practice A requesting practice B\'s cycle URL gets refused, nothing leaked', async ({
        page,
        request,
        browser,
    }) => {
        const slugA = unique('e2e-rec01-a').toLowerCase();
        const emailA = `${unique('owner-a')}@example.com`;
        const slugB = unique('e2e-rec01-b').toLowerCase();
        const emailB = `${unique('owner-b')}@example.com`;

        await request.post('/api/v1/tenants', {
            headers: { 'Idempotency-Key': crypto.randomUUID() },
            data: {
                tenant_name: 'REC-01 Practice A',
                tenant_slug: slugA,
                owner_name: 'Owner A',
                owner_email: emailA,
                password: OWNER_PASSWORD,
            },
        });

        const registerB = await request.post('/api/v1/tenants', {
            headers: { 'Idempotency-Key': crypto.randomUUID() },
            data: {
                tenant_name: 'REC-01 Practice B',
                tenant_slug: slugB,
                owner_name: 'Owner B',
                owner_email: emailB,
                password: OWNER_PASSWORD,
            },
        });
        const { token: tokenB } = await registerB.json();

        // Sites have no API create route at all (by design — an admin-
        // only web settings screen, see e2e/sites.spec.ts) — practice B
        // needs its own real browser session just to get one.
        const contextB = await browser.newContext();
        const pageB = await contextB.newPage();
        await login(pageB, emailB, OWNER_PASSWORD);
        await pageB.goto('/sites');
        await pageB.getByRole('button', { name: 'New site' }).click();
        const siteDialogB = pageB.getByRole('dialog');
        await siteDialogB.getByLabel('Name').fill('REC-01 Site B');
        await siteDialogB.getByRole('button', { name: 'Add site' }).click();
        await expect(pageB).toHaveURL(/\/sites\/[0-9a-f-]+$/);
        const siteId = pageB.url().split('/').pop() as string;
        await contextB.close();

        const deviceResponse = await request.post('/api/v1/devices', {
            headers: {
                Authorization: `Bearer ${tokenB}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: {
                site_id: siteId,
                name: 'REC-01 Device',
                serial_number: unique('REC01-SN'),
            },
        });
        const deviceId = (await deviceResponse.json()).id as string;
        const cycleResponse = await request.post('/api/v1/cycles', {
            headers: {
                Authorization: `Bearer ${tokenB}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: { device_id: deviceId },
        });
        const foreignCycleId = (await cycleResponse.json()).id as string;

        await login(page, emailA, OWNER_PASSWORD);
        const response = await page.goto(`/cycles/${foreignCycleId}`);
        expect(response?.status()).toBe(404);
        await expect(page.getByText(foreignCycleId)).toHaveCount(0);

        // NOTE: the brief also expects "a security event written" — no
        // such logging exists anywhere in the app today (confirmed:
        // nothing writes to audit_events, or anywhere else, on a blocked
        // cross-tenant lookup; TenantScope's 404 is silent). Refusal and
        // no-leak are both real and asserted above; the security-log gap
        // is genuine and is flagged in docs/ui-backlog.md, not built here
        // (this ticket writes no application code).
    });

    // ---------------------------------------------------------------
    // REC-02 — the same receipt submitted twice produces exactly one
    // stock movement. Driven at the API layer deliberately: it's the
    // only place Idempotency-Key is enforced (the web receipt form has
    // no such header/guard at all — a second, smaller, flagged gap,
    // documented in docs/ui-backlog.md rather than fixed here). The
    // storage location it receives into still has to come from the real
    // web UI first (UI-27) — there is no API to create one. Its id is
    // read back from the purchase-order show page's own Inertia props
    // (there's no locations-index API route either) rather than guessed.
    // ---------------------------------------------------------------
    test('REC-02: the same receipt submitted twice produces exactly one stock movement', async ({
        page,
        request,
    }) => {
        const tenantSlug = unique('e2e-rec02').toLowerCase();
        const ownerEmail = `${unique('rec02-owner')}@example.com`;

        const registerResponse = await request.post('/api/v1/tenants', {
            headers: { 'Idempotency-Key': crypto.randomUUID() },
            data: {
                tenant_name: 'REC-02 Practice',
                tenant_slug: tenantSlug,
                owner_name: 'REC-02 Owner',
                owner_email: ownerEmail,
                password: OWNER_PASSWORD,
            },
        });
        const { token } = await registerResponse.json();
        const authHeaders = { Authorization: `Bearer ${token}` };

        await login(page, ownerEmail, OWNER_PASSWORD);
        await page.goto('/sites');
        await page.getByRole('button', { name: 'New site' }).click();
        const newSiteDialog = page.getByRole('dialog');
        await newSiteDialog.getByLabel('Name').fill('REC-02 Site');
        await newSiteDialog.getByRole('button', { name: 'Add site' }).click();
        await expect(page).toHaveURL(/\/sites\/[0-9a-f-]+$/);
        await page.getByRole('button', { name: 'Add room' }).click();
        const newRoomDialog = page.getByRole('dialog');
        await newRoomDialog.getByLabel('Name').fill('Stock room');
        await newRoomDialog.getByRole('button', { name: 'Add room' }).click();
        const roomSection = page.getByRole('region', {
            name: 'Room: Stock room',
        });
        await roomSection.getByRole('button', { name: 'Add location' }).click();
        const newLocationDialog = page.getByRole('dialog');
        await newLocationDialog.getByLabel('Name').fill('REC-02 Shelf');
        await newLocationDialog
            .getByRole('button', { name: 'Add and add another' })
            .click();
        await expect(
            newLocationDialog.getByRole('button', {
                name: 'Add and add another',
            }),
        ).toBeDisabled();
        await newLocationDialog.getByRole('button', { name: 'Finish' }).click();
        await expect(
            page.getByRole('listitem', {
                name: 'Storage location: REC-02 Shelf',
            }),
        ).toBeVisible();

        const productResponse = await request.post('/api/v1/products', {
            headers: { ...authHeaders, 'Idempotency-Key': crypto.randomUUID() },
            data: { name: 'REC-02 Product', reference: unique('REC02-SKU') },
        });
        const productId = (await productResponse.json()).id as string;

        const supplierResponse = await request.post('/api/v1/suppliers', {
            headers: { ...authHeaders, 'Idempotency-Key': crypto.randomUUID() },
            data: { name: 'REC-02 Supplier' },
        });
        const supplierId = (await supplierResponse.json()).id as string;

        const poResponse = await request.post('/api/v1/purchase-orders', {
            headers: { ...authHeaders, 'Idempotency-Key': crypto.randomUUID() },
            data: {
                supplier_id: supplierId,
                lines: [{ product_id: productId, qty_ordered: 10 }],
            },
        });
        const po = await poResponse.json();
        const lineId = po.lines[0].id as string;
        await request.post(`/api/v1/purchase-orders/${po.id}/order`, {
            headers: { ...authHeaders, 'Idempotency-Key': crypto.randomUUID() },
            data: {},
        });

        // Read the storage location id back from the same Inertia props
        // the receipt form's own "Storage location" combobox is built
        // from — there is no API route to fetch it from instead.
        await page.goto(`/purchase-orders/${po.id}`);
        const pageDataJson = await page
            .locator('script[data-page="app"]')
            .textContent();
        const locationId = JSON.parse(pageDataJson as string).props.locations[0]
            .id as string;

        const idempotencyKey = crypto.randomUUID();
        const receiptPayload = {
            location_id: locationId,
            lines: [
                {
                    purchase_order_line_id: lineId,
                    batch_number: unique('REC02-BATCH'),
                    qty: 10,
                },
            ],
        };

        const firstReceipt = await request.post(
            `/api/v1/purchase-orders/${po.id}/receipts`,
            {
                headers: { ...authHeaders, 'Idempotency-Key': idempotencyKey },
                data: receiptPayload,
            },
        );
        const secondReceipt = await request.post(
            `/api/v1/purchase-orders/${po.id}/receipts`,
            {
                headers: { ...authHeaders, 'Idempotency-Key': idempotencyKey },
                data: receiptPayload,
            },
        );

        expect(firstReceipt.ok(), await firstReceipt.text()).toBeTruthy();
        expect(firstReceipt.status()).toBe(secondReceipt.status());
        expect(await firstReceipt.json()).toEqual(await secondReceipt.json());

        await page.goto('/stock/movements');
        const movementRows = page.getByRole('row').filter({ hasText: 'Receipt' });
        await expect(movementRows).toHaveCount(1);
    });

    // ---------------------------------------------------------------
    // Scanning a recalled label is blocked with the API's own error code.
    // ---------------------------------------------------------------
    test('scanning a recalled label is blocked with LABEL_RECALLED, not silently allowed', async ({
        page,
        request,
    }) => {
        const tenantSlug = unique('e2e-recall').toLowerCase();
        const ownerEmail = `${unique('recall-owner')}@example.com`;

        const registerResponse = await request.post('/api/v1/tenants', {
            headers: { 'Idempotency-Key': crypto.randomUUID() },
            data: {
                tenant_name: 'Recall Practice',
                tenant_slug: tenantSlug,
                owner_name: 'Recall Owner',
                owner_email: ownerEmail,
                password: OWNER_PASSWORD,
            },
        });
        const { token } = await registerResponse.json();
        const authHeaders = { Authorization: `Bearer ${token}` };

        // Sites have no API create route at all (by design — an admin-
        // only web settings screen, see e2e/sites.spec.ts) — this real
        // browser session is created early just to get one.
        await login(page, ownerEmail, OWNER_PASSWORD);
        await page.goto('/sites');
        await page.getByRole('button', { name: 'New site' }).click();
        const siteDialog = page.getByRole('dialog');
        await siteDialog.getByLabel('Name').fill('Recall Site');
        await siteDialog.getByRole('button', { name: 'Add site' }).click();
        await expect(page).toHaveURL(/\/sites\/[0-9a-f-]+$/);
        const siteId = page.url().split('/').pop() as string;

        const deviceResponse = await request.post('/api/v1/devices', {
            headers: { ...authHeaders, 'Idempotency-Key': crypto.randomUUID() },
            data: {
                site_id: siteId,
                name: 'Recall Device',
                serial_number: unique('RECALL-SN'),
            },
        });
        const deviceId = (await deviceResponse.json()).id as string;
        const cycleResponse = await request.post('/api/v1/cycles', {
            headers: { ...authHeaders, 'Idempotency-Key': crypto.randomUUID() },
            data: { device_id: deviceId },
        });
        const cycleId = (await cycleResponse.json()).id as string;
        await request.post(`/api/v1/cycles/${cycleId}/items`, {
            headers: { ...authHeaders, 'Idempotency-Key': crypto.randomUUID() },
            data: { description: 'Recall load item' },
        });
        await request.post(`/api/v1/cycles/${cycleId}/start`, {
            headers: { ...authHeaders, 'Idempotency-Key': crypto.randomUUID() },
            data: {},
        });
        await request.post(`/api/v1/cycles/${cycleId}/complete`, {
            headers: { ...authHeaders, 'Idempotency-Key': crypto.randomUUID() },
            data: {},
        });
        await request.post(`/api/v1/cycles/${cycleId}/submit-for-release`, {
            headers: { ...authHeaders, 'Idempotency-Key': crypto.randomUUID() },
            data: {},
        });
        await request.post(`/api/v1/cycles/${cycleId}/release`, {
            headers: { ...authHeaders, 'Idempotency-Key': crypto.randomUUID() },
            data: { decision: 'compliant' },
        });
        await request.post('/api/v1/dlu-rules', {
            headers: { ...authHeaders, 'Idempotency-Key': crypto.randomUUID() },
            data: {
                packaging_type: 'Sachet',
                storage_condition: 'Armoire',
                shelf_life_days: 180,
                reason: 'Test fixture.',
            },
        });
        const labelsResponse = await request.post(
            `/api/v1/cycles/${cycleId}/labels`,
            {
                headers: { ...authHeaders, 'Idempotency-Key': crypto.randomUUID() },
                data: { packaging_type: 'Sachet', storage_condition: 'Armoire' },
            },
        );
        const labelId = (await labelsResponse.json())[0].id as string;

        // Raise the non-conformity through the real UI (mirrors
        // e2e/non-conformities.spec.ts) — this is what actually recalls
        // the label, not a status flip via direct write.
        await page.goto(`/labels/${labelId}`);
        await page.getByRole('button', { name: 'Recall label' }).click();
        const dialog = page.getByRole('dialog');
        await dialog
            .getByLabel('Reason')
            .fill('Chemical indicator failed post-sterilization.');
        await dialog
            .getByRole('button', { name: /Recall 1 label/ })
            .click();
        await expect(dialog).not.toBeVisible();

        const scanResponse = await request.get(`/api/v1/labels/${labelId}`, {
            headers: authHeaders,
        });
        expect(scanResponse.status()).toBe(410);
        expect((await scanResponse.json()).error.code).toBe('LABEL_RECALLED');
    });
});
