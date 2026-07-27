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
        },
    ],
});
