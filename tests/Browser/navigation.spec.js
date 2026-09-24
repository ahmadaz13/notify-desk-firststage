import { test, expect } from '@playwright/test';
import { AUTH } from './global-setup.js';

// P9 shell smoke (§3, §22–§24, FROZEN D-23). One run per viewport project:
// phone 390 · iPad portrait 820 · iPad landscape 1024 · desktop 1440.
// `sidebar` = current destination in the desktop sidebar; `bar` = current bottom-bar tab.
const PAGES = {
    owner: [
        { path: '/', sidebar: 'today', bar: 'today', action: 'add-client' },
        { path: '/clients', sidebar: 'clients', bar: 'clients', action: 'add-client' },
        { path: '/finance', sidebar: 'finance-overview', bar: 'finance' },
        { path: '/finance/collections', sidebar: 'finance-collections', bar: 'finance' },
        { path: '/custom-projects', sidebar: 'custom-projects', bar: 'more', action: 'create-custom-project' },
        { path: '/settings', sidebar: 'admin-settings', bar: 'more' },
    ],
    staff: [
        { path: '/', sidebar: 'today', bar: 'today', action: 'add-client' },
        { path: '/clients', sidebar: 'clients', bar: 'clients', action: 'add-client' },
        { path: '/collections-due', sidebar: 'collections-due', bar: 'collections-due' },
        { path: '/custom-projects', sidebar: 'custom-projects', bar: 'more' },
    ],
};

const MORE = {
    owner: {
        present: ['custom-projects', 'notifications', 'admin-systems', 'admin-team', 'admin-import', 'admin-settings', 'profile', 'change-password', 'language', 'logout'],
        absent: ['finance-overview', 'collections-due'],
    },
    staff: {
        present: ['custom-projects', 'notifications', 'profile', 'change-password', 'language', 'logout'],
        absent: ['admin-systems', 'admin-team', 'admin-import', 'admin-settings', 'finance-overview', 'finance'],
    },
};

const hasSidebar = (page) => page.viewportSize().width >= 1024;

async function expectNoHorizontalOverflow(page) {
    const { scrollWidth, clientWidth } = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
    }));
    expect(scrollWidth, 'page must not scroll horizontally').toBeLessThanOrEqual(clientWidth);
}

for (const role of ['owner', 'staff']) {
    test.describe(`${role} shell`, () => {
        test.use({ storageState: AUTH[role] });

        for (const target of PAGES[role]) {
            test(`${target.path} renders with correct navigation`, async ({ page }, testInfo) => {
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));

                const response = await page.goto(target.path);
                expect(response.status()).toBe(200);
                await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
                await expect(page.locator('h1')).toHaveCount(1);
                await expectNoHorizontalOverflow(page);

                const sidebar = page.locator('.notify-sidebar');
                const bar = page.locator('.notify-mobile-nav__grid');

                if (hasSidebar(page)) {
                    await expect(sidebar).toBeVisible();
                    await expect(bar).toBeHidden();
                    await expect(sidebar.locator(`[data-nav-destination="${target.sidebar}"]`)).toHaveAttribute('aria-current', 'page');
                    await expect(sidebar.locator('[aria-current="page"]')).toHaveCount(1);
                    // RTL: sidebar sits at the inline start (right).
                    const side = await sidebar.boundingBox();
                    const main = await page.locator('.notify-main').boundingBox();
                    expect(side.x).toBeGreaterThan(main.x);
                } else {
                    await expect(sidebar).toBeHidden();
                    await expect(bar).toBeVisible();
                    await expect(bar.locator('> *')).toHaveCount(4);
                    const current = bar.locator(`[data-nav-destination="${target.bar}"]`);
                    if (target.bar === 'more') {
                        await expect(current).toHaveClass(/is-active/);
                    } else {
                        await expect(current).toHaveAttribute('aria-current', 'page');
                    }
                    // Content clears the fixed bottom bar.
                    const padding = await page.locator('.notify-content').evaluate((el) => parseFloat(getComputedStyle(el).paddingBottom));
                    const barHeight = await page.locator('.notify-mobile-nav').evaluate((el) => el.getBoundingClientRect().height);
                    expect(padding).toBeGreaterThanOrEqual(barHeight);
                    // Touch targets.
                    for (const box of await bar.locator('> *').evaluateAll((els) => els.map((el) => el.getBoundingClientRect().height))) {
                        expect(box).toBeGreaterThanOrEqual(44);
                    }
                }

                if (target.action) {
                    await expect(page.locator(`[data-page-action="${target.action}"]`).first()).toBeVisible();
                }

                if (process.env.P9_SHOTS) {
                    await page.screenshot({ path: `storage/playwright/screens/${testInfo.project.name}-${role}${target.path.replaceAll('/', '_') || '_'}.png` });
                }
                expect(errors).toEqual([]);
            });
        }

        test('More sheet exposes only permitted destinations', async ({ page }) => {
            test.skip(hasSidebar(page), 'More sheet is the phone / iPad-portrait surface');
            await page.goto('/');
            const more = page.locator('.notify-mobile-nav__grid [data-nav-destination="more"]');
            await more.click();
            const sheet = page.locator('#notify-mobile-more');
            await expect(sheet).toBeVisible();
            await sheet.evaluate((el) => Promise.all(el.getAnimations().map((animation) => animation.finished.catch(() => null))));
            await expect(more).toHaveAttribute('aria-expanded', 'true');
            for (const destination of MORE[role].present) {
                await expect(sheet.locator(`[data-nav-destination="${destination}"]`)).toBeVisible();
            }
            for (const destination of MORE[role].absent) {
                await expect(sheet.locator(`[data-nav-destination="${destination}"]`)).toHaveCount(0);
            }
            const sheetBox = await sheet.boundingBox();
            expect(sheetBox.x).toBeGreaterThanOrEqual(0);
            expect(sheetBox.x + sheetBox.width).toBeLessThanOrEqual(page.viewportSize().width);
            if (process.env.P9_SHOTS) {
                await page.screenshot({ path: `storage/playwright/screens/${test.info().project.name}-${role}-more.png` });
            }
            await page.keyboard.press('Escape');
            await expect(sheet).toBeHidden();
            await expect(more).toBeFocused();
        });
    });
}

test.describe('English (LTR) shell', () => {
    test.use({ storageState: AUTH.owner });

    test('mirrors layout direction', async ({ page }) => {
        await page.goto('/locale/en');
        await page.goto('/clients');
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        await expectNoHorizontalOverflow(page);
        if (hasSidebar(page)) {
            const side = await page.locator('.notify-sidebar').boundingBox();
            const main = await page.locator('.notify-main').boundingBox();
            expect(side.x).toBeLessThan(main.x);
        } else {
            await expect(page.locator('.notify-mobile-nav__grid [data-nav-destination="clients"]')).toHaveAttribute('aria-current', 'page');
        }
        if (process.env.P9_SHOTS) {
            await page.screenshot({ path: `storage/playwright/screens/${test.info().project.name}-owner-en_clients.png` });
        }
        await page.goto('/locale/ar');
    });
});

test.describe('keyboard', () => {
    test.use({ storageState: AUTH.owner });

    test('skip link is the first focus stop and targets main content', async ({ page }) => {
        await page.goto('/clients');
        await page.keyboard.press('Tab');
        const skip = page.locator('.notify-skip-link');
        await expect(skip).toBeFocused();
        await expect(skip).toHaveAttribute('href', '#notify-main-content');
    });
});
