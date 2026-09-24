import { chromium } from '@playwright/test';
import fs from 'node:fs';

// Signs in the seeded demo Owner and the BrowserSmokeSeeder Staff once and stores their sessions.
export const AUTH = {
    owner: 'storage/playwright/.auth/owner.json',
    staff: 'storage/playwright/.auth/staff.json',
};

const ACCOUNTS = {
    owner: 'ahmad@example.com',
    staff: 'staff@example.com',
};

export default async function globalSetup(config) {
    const baseURL = config.projects[0].use.baseURL;
    fs.mkdirSync('storage/playwright/.auth', { recursive: true });
    const browser = await chromium.launch();

    for (const [role, email] of Object.entries(ACCOUNTS)) {
        const page = await browser.newPage({ baseURL });
        await page.goto('/login');
        await page.locator('input[name="email"]').fill(email);
        await page.locator('input[name="password"]').fill('password');
        await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/login')), page.locator('form').first().evaluate((form) => form.requestSubmit())]);
        await page.context().storageState({ path: AUTH[role] });
        await page.close();
    }

    await browser.close();
}
