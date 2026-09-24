import { test, expect } from '@playwright/test';
import { AUTH } from './global-setup.js';

// P13 backoffice review: Administration index, Systems, Team & Roles, Reference Data, Import, Settings,
// Profile and Custom Projects at 390 / 820 / 1024 / 1440, Arabic RTL and English LTR, Owner and Staff.
// Checks: renders with one h1, no horizontal overflow, no console errors, 44px primary controls,
// 16px inputs on touch widths, sheets open/close, Settings save bar clear of the bottom nav.
// Set P13_SHOTS=1 to write screenshots to storage/playwright/screens.

function watchErrors(page) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => message.type() === 'error' && errors.push(message.text()));
    return errors;
}

async function expectNoOverflow(page) {
    const { scrollWidth, clientWidth } = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
    }));
    expect(scrollWidth, 'no horizontal page overflow').toBeLessThanOrEqual(clientWidth);
}

async function shot(page, testInfo, name) {
    if (process.env.P13_SHOTS) {
        await page.screenshot({ path: `storage/playwright/screens/p13-${testInfo.project.name}-${name}.png`, fullPage: true });
    }
}

const isPhone = (page) => page.viewportSize().width < 600;
const isTouch = (page) => page.viewportSize().width < 1280;

async function expectTouchFriendly(page) {
    if (!isTouch(page)) {
        return;
    }
    // Every visible text input/select in the page content is at least 16px (no iOS zoom) and 44px tall.
    const small = await page.evaluate(() => [...document.querySelectorAll('main .notify-input')]
        .filter((el) => el.offsetParent !== null && el.type !== 'file')
        .filter((el) => parseFloat(getComputedStyle(el).fontSize) < 16 || el.getBoundingClientRect().height < 43.5)
        .map((el) => el.id || el.name));
    expect(small, 'inputs are 16px and 44px on touch widths').toEqual([]);
}

const OWNER_PAGES = [
    { name: 'administration', path: '/administration' },
    { name: 'systems', path: '/administration/systems' },
    { name: 'team', path: '/administration/team' },
    { name: 'reference-data', path: '/administration/reference-data' },
    { name: 'import', path: '/administration/import' },
    { name: 'settings', path: '/administration/settings' },
    { name: 'profile', path: '/profile' },
    { name: 'projects', path: '/custom-projects' },
    { name: 'project-create', path: '/custom-projects/create' },
];

test.describe('owner backoffice', () => {
    test.use({ storageState: AUTH.owner });

    for (const target of OWNER_PAGES) {
        test(`${target.name} renders cleanly`, async ({ page }, testInfo) => {
            const errors = watchErrors(page);
            const response = await page.goto(target.path);
            expect(response.status()).toBe(200);
            await expect(page.locator('main h1')).toHaveCount(1);
            await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
            await expectNoOverflow(page);
            await expectTouchFriendly(page);
            await shot(page, testInfo, target.name);
            expect(errors).toEqual([]);
        });
    }

    test('project detail with and without a client', async ({ page }, testInfo) => {
        const errors = watchErrors(page);
        await page.goto('/custom-projects');
        const rows = page.locator('[data-project-list] a.notify-list__title');
        await expect(rows).toHaveCount(2);

        await page.locator('[data-project-list] tr', { has: page.locator('[data-no-client]') }).locator('a.notify-list__title').click();
        await expect(page.locator('[data-invoice-needs-client]')).toBeVisible();
        await expect(page.locator('[data-project-invoice-open]')).toHaveCount(0);
        await expectNoOverflow(page);
        await shot(page, testInfo, 'project-no-client');

        await page.goto('/custom-projects');
        await page.locator('[data-project-list] tr:not(:has([data-no-client])) a.notify-list__title').first().click();
        const open = page.locator('[data-project-invoice-open]');
        await expect(open).toBeVisible();
        await open.click();
        const sheet = page.locator('#project-invoice');
        await expect(sheet).toBeVisible();
        const submit = sheet.locator('[data-project-invoice-submit]');
        const box = await submit.boundingBox();
        expect(box.height).toBeGreaterThanOrEqual(44);
        expect(box.y + box.height, 'invoice submit reachable').toBeLessThanOrEqual(page.viewportSize().height);
        await shot(page, testInfo, 'project-invoice-sheet');
        await page.keyboard.press('Escape');
        await expect(sheet).toBeHidden();
        await expectNoOverflow(page);
        expect(errors).toEqual([]);
    });

    test('team: add sheet, menu and edit sheet', async ({ page }, testInfo) => {
        const errors = watchErrors(page);
        await page.goto('/administration/team');
        await page.locator('[data-page-action="add-member"]').click();
        const add = page.locator('#team-add');
        await expect(add).toBeVisible();
        // P13.1 (D-25): ordinary creation is Staff only — no role picker, no Admin.
        await expect(add.locator('input[name="role"]')).toHaveCount(0);
        await expect(add.locator('[data-add-role-staff]')).toBeVisible();
        await shot(page, testInfo, 'team-add');
        await page.keyboard.press('Escape');
        await expect(add).toBeHidden();

        const staffRow = page.locator('[data-team-list] tr', { hasText: 'Sara' }).first();
        await staffRow.locator('[data-team-edit]').click();
        const editSheet = page.locator('.notify-sheet:not([hidden])');
        await expect(editSheet).toBeVisible();
        const roleValues = await editSheet.locator('input[name="role"]').evaluateAll((inputs) => inputs.map((input) => input.value));
        expect(roleValues).toEqual(['founder', 'staff']);
        await shot(page, testInfo, 'team-edit-staff');
        await page.keyboard.press('Escape');

        // Two roles only in the role information, no Admin anywhere on the page.
        await expect(page.locator('.notify-admin-roles dt')).toHaveCount(2);
        await expect(page.locator('main')).not.toContainText('مدير النظام');
        await expect(page.locator('input[value="admin"]')).toHaveCount(0);

        await staffRow.locator('.notify-menu summary').click();
        await expect(staffRow.locator('[data-team-reset]')).toBeVisible();
        await expect(staffRow.locator('[data-team-deactivate]')).toBeVisible();
        await page.keyboard.press('Escape');
        await expectNoOverflow(page);
        expect(errors).toEqual([]);
    });

    test('systems + reference data sheets', async ({ page }, testInfo) => {
        const errors = watchErrors(page);
        await page.goto('/administration/systems');
        await page.locator('[data-system-edit="smart_link"]').click();
        const sheet = page.locator('.notify-sheet:not([hidden])');
        await expect(sheet).toBeVisible();
        await expect(sheet.locator('.notify-admin-code')).toBeVisible();
        await shot(page, testInfo, 'system-edit');
        await page.keyboard.press('Escape');

        await page.goto('/administration/reference-data');
        await page.locator('[data-reference-add="client_category"]').click();
        const add = page.locator('#reference-add-client_category');
        await expect(add).toBeVisible();
        await shot(page, testInfo, 'reference-add');
        await page.keyboard.press('Escape');
        await expect(add).toBeHidden();
        await expectNoOverflow(page);
        expect(errors).toEqual([]);
    });

    test('settings: save bar clear of the bottom nav and workday validation', async ({ page }, testInfo) => {
        const errors = watchErrors(page);
        await page.goto('/administration/settings');
        const save = page.locator('[data-settings-save]');
        await expect(save).toBeVisible();
        const saveBox = await save.boundingBox();
        expect(saveBox.height).toBeGreaterThanOrEqual(44);
        const nav = page.locator('.notify-mobile-nav');
        if (await nav.isVisible()) {
            const navBox = await nav.boundingBox();
            expect(saveBox.y + saveBox.height, 'save above the bottom nav').toBeLessThanOrEqual(navBox.y);
        }

        await page.locator('#set-day-start').fill('17:00');
        await page.locator('#set-day-end').fill('09:00');
        await save.click();
        await page.waitForLoadState('load');
        await expect(page.locator('#set-day-end')).toHaveAttribute('aria-invalid', 'true');
        await expect(page.locator('#set-day-end-error')).toBeVisible();
        await expect(page.locator('#set-day-end')).toBeFocused();
        await shot(page, testInfo, 'settings-invalid');
        await expectNoOverflow(page);
        expect(errors).toEqual([]);
    });
});

test.describe('english LTR', () => {
    test.use({ storageState: AUTH.owner });

    test('backoffice pages in English', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'phone-390' && testInfo.project.name !== 'desktop-1440', 'phone + desktop');
        const errors = watchErrors(page);
        await page.goto('/locale/en');
        for (const target of ['/administration/settings', '/administration/team', '/administration/reference-data', '/profile', '/custom-projects']) {
            await page.goto(target);
            await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
            await expectNoOverflow(page);
        }
        await page.goto('/administration/team');
        await expect(page.locator('.notify-admin-roles dt')).toHaveText(['Founder', 'Staff']);
        await expect(page.locator('main')).not.toContainText('Admin ');
        await shot(page, testInfo, 'team-en');
        await page.goto('/administration/settings');
        await shot(page, testInfo, 'settings-en');
        await page.goto('/locale/ar');
        expect(errors).toEqual([]);
    });
});

test.describe('staff', () => {
    test.use({ storageState: AUTH.staff });

    test('profile and read-only projects; no administration', async ({ page }, testInfo) => {
        const errors = watchErrors(page);
        await page.goto('/profile');
        await expect(page.locator('[data-profile-summary]')).toBeVisible();
        await expect(page.locator('input[name="email"]')).toHaveCount(0);
        await expect(page.locator('label[for="profile-avatar"]')).toBeVisible();
        await expectNoOverflow(page);
        await shot(page, testInfo, 'staff-profile');

        await page.goto('/custom-projects');
        await expect(page.locator('[data-read-only]')).toBeVisible();
        await expect(page.locator('[data-page-action="create-custom-project"]')).toHaveCount(0);
        await page.locator('[data-project-list] a.notify-list__title').first().click();
        await expect(page.locator('[data-project-edit]')).toHaveCount(0);
        await expect(page.locator('[data-project-invoice-open]')).toHaveCount(0);
        await expectNoOverflow(page);
        await shot(page, testInfo, 'staff-project');

        for (const path of ['/administration', '/administration/systems', '/administration/team', '/administration/reference-data', '/administration/import', '/administration/settings']) {
            const response = await page.goto(path);
            expect(response.status(), path).toBe(403);
        }
        await page.goto('/');
        await expect(page.locator('[data-nav-destination^="admin-"]')).toHaveCount(0);
        expect(errors.filter((e) => !e.includes('403'))).toEqual([]);
    });
});
