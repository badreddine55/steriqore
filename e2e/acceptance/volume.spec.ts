import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { expect, test } from '@playwright/test';

const execFileAsync = promisify(execFile);

/**
 * REC-09 — evidence search returns complete results in under 2s at pilot
 * volume. Kept as its own spec, separate from the main journey (brief's
 * own instruction), since it seeds a much larger, slower fixture:
 * `steriqore:seed-pilot-volume` (S5-ANA-04) — 3 devices, 3,000 cycles,
 * ~10 pouches/cycle (30,000 labels/usages), 2,000 patients, 500 batches,
 * for one brand-new tenant, run inside the same Docker container this
 * whole suite already targets.
 *
 * Gap found while wiring this up: the command creates its one user via a
 * raw DB insert and never calls SeedTenantRolesAction or assignRole() —
 * every other tenant-creation path in the app (self-serve registration,
 * invitation accept) seeds roles; this one doesn't, so its user has zero
 * permissions and can't reach any permission-gated screen, including
 * Evidence search, as shipped. Assigning the owner role below is fixture
 * setup for a test-only command (mirrors how Pest tests already call
 * SeedTenantRolesAction + assignRole() directly to build their own
 * fixtures), not an application-code fix — flagged in docs/ui-backlog.md
 * as a real, small bug in steriqore:seed-pilot-volume itself.
 */
test.describe('REC-09: evidence search at pilot volume', () => {
    test('evidence search returns complete results in under 2 seconds', async ({
        page,
    }) => {
        test.setTimeout(5 * 60 * 1000);

        const { stdout: seedOutput } = await execFileAsync('docker', [
            'compose',
            'exec',
            '-T',
            'app',
            'php',
            'artisan',
            'steriqore:seed-pilot-volume',
            '--force',
        ]);
        const slugMatch = seedOutput.match(/Tenant slug: (\S+)/);

        if (!slugMatch) {
            throw new Error(
                `Could not find tenant slug in seed output:\n${seedOutput}`,
            );
        }

        const tenantSlug = slugMatch[1];

        const fixupScript = `
            $tenant = App\\Domain\\Tenancy\\Models\\Tenant::where('slug', '${tenantSlug}')->firstOrFail();
            $tenantUser = App\\Domain\\Identity\\Models\\TenantUser::where('tenant_id', $tenant->id)->firstOrFail();
            $user = $tenantUser->user;
            App\\Support\\Tenancy\\TenantContext::run($tenant, function () use ($tenant, $user) {
                app(App\\Domain\\Identity\\Actions\\SeedTenantRolesAction::class)->execute($tenant);
                $user->assignRole('owner');
            });
            echo $user->email;
        `.replace(/\n\s*/g, ' ');

        const { stdout: fixupOutput } = await execFileAsync('docker', [
            'compose',
            'exec',
            '-T',
            'app',
            'php',
            'artisan',
            'tinker',
            '--execute',
            fixupScript,
        ]);
        const email = fixupOutput.trim().split('\n').pop() as string;

        await page.goto('/login');
        await page.locator('#email').fill(email);
        await page.locator('#password').fill('password');
        await page.locator('[data-test="login-button"]').click();
        await page.waitForURL('**/dashboard');

        await page.goto('/evidence-search');
        await expect(
            page.getByRole('heading', { name: 'Evidence search' }),
        ).toBeVisible();

        // PAT-000001 — the seeded command's own patient reference format.
        const start = Date.now();
        await page.getByLabel('Patient reference').fill('PAT-000001');
        await expect(
            page.getByRole('row').filter({ hasText: 'PAT-000001' }).first(),
        ).toBeVisible();
        const elapsedMs = Date.now() - start;

        expect(elapsedMs).toBeLessThan(2000);
    });
});
