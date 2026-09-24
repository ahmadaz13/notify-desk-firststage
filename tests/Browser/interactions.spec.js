import { test, expect } from '@playwright/test';
import { AUTH } from './global-setup.js';

// P12 interaction review: sheets (open, focus trap, Escape, focus return, phone sizing, validation
// reopen), confirmation dialog, "…" menu keyboard behaviour, list table/card switch, filter sheet +
// chips. Fixtures: DatabaseSeeder + BrowserSmokeSeeder (1 prospect with free access, 4 subscriber with
// a pending Staff receipt, clients 7+ from the P11 Today board).

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
    if (process.env.P12_SHOTS) {
        await page.screenshot({ path: `storage/playwright/screens/p12-${testInfo.project.name}-${name}.png` });
    }
}

const isPhone = (page) => page.viewportSize().width < 600;

test.describe('owner interactions', () => {
    test.use({ storageState: AUTH.owner });

    test('sheet: open, trap focus, Escape, focus return, sizing', async ({ page }, testInfo) => {
        const errors = watchErrors(page);
        await page.goto('/clients/4');
        const opener = page.locator('[data-primary-action]');
        await opener.click();
        const sheet = page.locator('#modal-record-payment');
        const panel = sheet.locator('.notify-sheet__panel');
        await expect(sheet).toBeVisible();
        await expect(panel).toHaveAttribute('aria-modal', 'true');

        // Focus stays inside the dialog.
        for (let i = 0; i < 12; i++) { await page.keyboard.press('Tab'); }
        expect(await panel.evaluate((el) => el.contains(document.activeElement))).toBe(true);

        const box = await panel.boundingBox();
        const viewport = page.viewportSize();
        const submit = sheet.locator('.notify-sheet__footer button[type="submit"]');
        const submitBox = await submit.boundingBox();
        expect(submitBox.y + submitBox.height, 'submit reachable').toBeLessThanOrEqual(viewport.height);
        expect(submitBox.height).toBeGreaterThanOrEqual(44);
        if (isPhone(page)) {
            expect(box.height, 'near full-screen on phone').toBeGreaterThanOrEqual(viewport.height * 0.9);
            expect(box.width).toBeGreaterThanOrEqual(viewport.width - 1);
        } else {
            expect(box.width, 'sized dialog on tablet/desktop').toBeLessThanOrEqual(561);
            expect(Math.abs((box.x + box.width / 2) - viewport.width / 2)).toBeLessThan(2);
        }
        await shot(page, testInfo, 'payment-sheet');

        await page.keyboard.press('Escape');
        await expect(sheet).toBeHidden();
        await expect(opener).toBeFocused();
        await expectNoOverflow(page);
        expect(errors).toEqual([]);
    });

    test('validation failure reopens the same sheet at the invalid field', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'phone-390' && testInfo.project.name !== 'desktop-1440', 'phone + desktop');
        const errors = watchErrors(page);
        await page.goto('/clients/1?open=record-call');
        const sheet = page.locator('#modal-record-call');
        await expect(sheet).toBeVisible();
        await sheet.locator('label', { has: page.locator('input[name="result"][value="callback_later"]') }).click();
        await sheet.locator('#call-callback-at').fill('');
        await sheet.locator('#call-note').fill('Keep this note');
        await sheet.locator('.notify-sheet__footer button[type="submit"]').click();

        await expect(page.locator('#modal-record-call')).toBeVisible();
        await expect(page.locator('#modal-record-call [data-sheet-errors]')).toBeVisible();
        await expect(page.locator('#call-callback-at')).toHaveAttribute('aria-invalid', 'true');
        await expect(page.locator('#call-callback-at')).toBeFocused();
        await expect(page.locator('#call-note')).toHaveValue('Keep this note');
        await expect(page.locator('#modal-create-appointment')).toBeHidden();
        await shot(page, testInfo, 'reopen');
        expect(errors).toEqual([]);
    });

    test('confirmation dialog guards destructive actions', async ({ page }, testInfo) => {
        await page.goto('/clients/1');
        const revoke = page.locator('[data-revoke-access] button[type="submit"]').first();
        await expect(revoke).toBeVisible();
        await revoke.click();
        const dialog = page.locator('#notify-confirm');
        await expect(dialog).toBeVisible();
        await expect(dialog.locator('[role="alertdialog"]')).toBeVisible();
        await expect(dialog.locator('[data-confirm-text]')).not.toBeEmpty();
        await expect(dialog.locator('[data-confirm-accept]')).toHaveClass(/notify-button--danger/);
        await shot(page, testInfo, 'confirm');
        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();
        await expect(page).toHaveURL(/\/clients\/1$/);
        await expect(page.locator('[data-revoke-access]')).toHaveCount(1);
    });

    test('"…" menu: keyboard, Escape, outside click, inside viewport', async ({ page }) => {
        await page.goto('/finance/collections?tab=pending');
        const menu = page.locator('[data-pending-receipt] details[data-menu]').first();
        const trigger = menu.locator('summary');
        await trigger.focus();
        await page.keyboard.press('Enter');
        await expect(menu).toHaveAttribute('open', '');
        await expect(trigger).toHaveAttribute('aria-expanded', 'true');
        const panel = await menu.locator('.notify-menu__panel').boundingBox();
        expect(panel.x).toBeGreaterThanOrEqual(0);
        expect(panel.x + panel.width).toBeLessThanOrEqual(page.viewportSize().width);
        await page.keyboard.press('ArrowDown');
        await expect(menu.locator('[role="menuitem"]').first()).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(menu).not.toHaveAttribute('open', '');
        await expect(trigger).toBeFocused();

        await trigger.click();
        await expect(menu).toHaveAttribute('open', '');
        await page.locator('h1').click();
        await expect(menu).not.toHaveAttribute('open', '');

        // Menu item opens the reason sheet (reject receipt).
        await trigger.click();
        await menu.locator('[role="menuitem"]').first().click();
        await expect(page.locator('[id^="reject-receipt-"]').first()).toBeVisible();
    });

    test('lists: cards on phone, table from 768px', async ({ page }, testInfo) => {
        const errors = watchErrors(page);
        await page.goto('/finance/collections?tab=due');
        await expectNoOverflow(page);
        const head = page.locator('.notify-list__table thead').first();
        const row = page.locator('.notify-list__table tbody tr').first();
        await expect(row).toBeVisible();
        const display = await row.evaluate((el) => getComputedStyle(el).display);
        if (page.viewportSize().width < 768) {
            expect(display).toBe('grid');
            expect((await head.boundingBox())?.height ?? 0).toBeLessThanOrEqual(1);
        } else {
            expect(display).toBe('table-row');
            await expect(head).toBeVisible();
        }
        await shot(page, testInfo, 'collections-due');

        for (const url of ['/finance/collections?tab=pending', '/finance/expenses', '/finance/expenses?tab=recurring', '/finance/accounts', '/clients']) {
            await page.goto(url);
            await expectNoOverflow(page);
            await expect(page.locator('h1')).toHaveCount(1);
        }
        await shot(page, testInfo, 'clients');
        expect(errors).toEqual([]);
    });

    test('client filters: sheet, chip, reset keep the segment', async ({ page }, testInfo) => {
        await page.goto('/clients?view=all');
        await page.locator('[data-client-filters-open]').click();
        const sheet = page.locator('#client-filters');
        await expect(sheet).toBeVisible();
        const category = sheet.locator('#client-filter-category');
        const value = await category.locator('option').nth(1).getAttribute('value');
        await category.selectOption(value);
        await shot(page, testInfo, 'filters');
        await sheet.locator('.notify-sheet__footer button[type="submit"]').click();
        await expect(page).toHaveURL(/view=all/);
        await expect(page).toHaveURL(/category=/);
        const chip = page.locator('[data-filter-chip]');
        await expect(chip).toHaveCount(1);
        await chip.click();
        await expect(page).toHaveURL(/view=all/);
        await expect(page).not.toHaveURL(/category=/);
    });

    test('English: sheet and menu are LTR and inside the viewport', async ({ page }) => {
        await page.goto('/locale/en');
        await page.goto('/clients/1');
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        const more = page.locator('[data-client-more]');
        if (await more.count()) {
            await more.locator('summary').click();
            const panel = await more.locator('.notify-menu__panel').boundingBox();
            expect(panel.x).toBeGreaterThanOrEqual(0);
            expect(panel.x + panel.width).toBeLessThanOrEqual(page.viewportSize().width);
            await page.keyboard.press('Escape');
        }
        await page.locator('[data-primary-action]').click();
        await expect(page.locator('[data-sheet]:not([hidden]) .notify-sheet__title')).toBeVisible();
        await page.goto('/locale/ar');
    });
});

test.describe('today → workspace → validation → back to Today', () => {
    test.use({ storageState: AUTH.owner });

    test('follow-up completed from Today after one validation retry', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'phone-390', 'mutates fixtures; run once');
        const errors = watchErrors(page);
        await page.goto('/');
        const toggle = page.locator('[data-today-overdue-toggle]');
        if (await toggle.count()) { await toggle.click(); }
        await page.locator('[data-card-primary-action="follow_up"]').first().click();
        const sheet = page.locator('#modal-follow-up');
        await expect(sheet).toBeVisible();
        await sheet.locator('label', { has: page.locator('input[name="outcome"][value="callback_later"]') }).click();
        await sheet.locator('#follow-up-next-date').fill('');
        await sheet.locator('.notify-sheet__footer button[type="submit"]').click();

        await expect(page.locator('#modal-follow-up [data-sheet-errors]')).toBeVisible();
        await expect(page).toHaveURL(/from=today/);
        await page.locator('#follow-up-next-date').fill('2026-12-01');
        await page.locator('#modal-follow-up .notify-sheet__footer button[type="submit"]').click();
        await expect(page).toHaveURL(/\/$/);
        await expect(page.locator('[data-flash="success"]')).toBeVisible();
        expect(errors).toEqual([]);
    });
});

test.describe('staff interactions', () => {
    test.use({ storageState: AUTH.staff });

    test('receipt sheet only, same sheet system', async ({ page }) => {
        await page.goto('/clients/4?open=record-payment');
        const sheet = page.locator('#modal-record-payment');
        await expect(sheet).toBeVisible();
        await expect(sheet.locator('form')).toHaveAttribute('data-payment-form', 'staff-receipt');
        await expect(sheet.locator('input[name="payment_method"]')).toHaveCount(2);
        await page.keyboard.press('Escape');
        await page.goto('/collections-due');
        await expectNoOverflow(page);
        await expect(page.locator('.notify-list__table')).toHaveCount(2);
    });
});
