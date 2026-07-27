import type { Page } from '@playwright/test';
import { DEMO_EMAIL, DEMO_PASSWORD } from './demo';

export async function loginAsDemoOwner(page: Page): Promise<void> {
    await page.goto('/login');
    await page.locator('#email').fill(DEMO_EMAIL);
    await page.locator('#password').fill(DEMO_PASSWORD);
    await page.locator('[data-test="login-button"]').click();
    await page.waitForURL('**/dashboard');
}
