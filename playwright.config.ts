import { defineConfig, devices } from '@playwright/test';

/**
 * Runs against the real running Docker container (steriqore.sh up), not a
 * dev server Playwright spins up itself — this is the same container the
 * app actually deploys as, FrankenPHP/Octane included, not a stand-in.
 */
export default defineConfig({
    testDir: './e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: [['list']],
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:8010',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
            testIgnore: '**/acceptance/**',
        },
        // UI-31 — the full §10 demo-journey acceptance suite. Kept out of
        // the default `chromium` project (and out of `npm run e2e`'s
        // day-to-day loop) since it registers its own fresh tenant, sends
        // real mail through Mailpit, and is slow by design; run it
        // explicitly via `npm run e2e:acceptance`. NO retries, ever — a
        // flaky acceptance test is a failing one, never papered over.
        {
            name: 'acceptance',
            testDir: './e2e/acceptance',
            retries: 0,
            use: { ...devices['Desktop Chrome'], video: 'retain-on-failure' },
        },
    ],
});
