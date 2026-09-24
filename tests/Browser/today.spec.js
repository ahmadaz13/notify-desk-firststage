import { test, expect } from '@playwright/test';
import { AUTH } from './global-setup.js';

// P11 Today review (§20, D-23): first-screen comprehension, Overdue → Next → Later order, one primary
// action per card, no overflow, bottom-bar clearance, P10 deep links, return to Today, Staff isolation.
// Fixtures: DatabaseSeeder + BrowserSmokeSeeder::todayBoard() on a fresh e2e database.

function watchErrors(page) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => message.type() === 'error' && errors.push(message.text()));
    return errors;
}

async function expectCleanPage(page) {
    const { scrollWidth, clientWidth } = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
    }));
    expect(scrollWidth, 'no horizontal page overflow').toBeLessThanOrEqual(clientWidth);
    await expect(page.locator('h1')).toHaveCount(1);
    const text = await page.locator('main').innerText();
    expect(text).not.toContain('notify.today_board');
    expect(text).not.toContain('notify.work');
}

async function expectClearOfBottomBar(page) {
    const bar = page.locator('.notify-mobile-nav');
    if (!(await bar.isVisible())) { return; }
    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    const barTop = (await bar.boundingBox()).y;
    const last = await page.locator('main .notify-card, main .notify-task').last().boundingBox();
    expect(last.y + last.height, 'last card clears the bottom bar').toBeLessThanOrEqual(barTop + 1);
    await page.evaluate(() => window.scrollTo(0, 0));
}

async function shot(page, testInfo, name) {
    if (process.env.P11_SHOTS) {
        await page.screenshot({ path: `storage/playwright/screens/p11-${testInfo.project.name}-${name}.png`, fullPage: true });
    }
}

async function expectCardsWellFormed(page) {
    for (const card of await page.locator('[data-work-item]:visible').all()) {
        await expect(card.locator('[data-card-primary-action]')).toHaveCount(1);
        await expect(card.locator('[data-work-client-link]')).toHaveCount(1);
        const primary = await card.locator('[data-card-primary-action]').boundingBox();
        expect(primary.height, 'primary action is a 44px target').toBeGreaterThanOrEqual(44);
    }
}

test.describe('owner today', () => {
    test.use({ storageState: AUTH.owner });

    test('mixed day: overdue, next and later in order', async ({ page }, testInfo) => {
        const errors = watchErrors(page);
        await page.goto('/');
        await expectCleanPage(page);

        const sections = await page.locator('[data-today-section]').evaluateAll((els) => els.map((el) => el.dataset.todaySection));
        expect(sections[0]).toBe('overdue');
        expect(sections).toContain('next');
        expect(sections.indexOf('next')).toBeGreaterThan(sections.indexOf('overdue'));
        if (sections.includes('later_today')) {
            expect(sections.indexOf('later_today')).toBeGreaterThan(sections.indexOf('next'));
        }

        // Overdue is restrained: at most two cards until "show all".
        expect(await page.locator('[data-today-section="overdue"] [data-work-item]:visible').count()).toBeLessThanOrEqual(2);
        await expectCardsWellFormed(page);

        // The Next heading is reachable on the first screen (above the bottom bar on phone).
        const next = await page.locator('#today-next-title').boundingBox();
        expect(next.y, 'Next starts on the first screen').toBeLessThan(page.viewportSize().height);

        // Owner signals: pending confirmation from the Staff receipt; no finance totals anywhere.
        await expect(page.locator('[data-today-signal="pending-confirmations"]')).toHaveCount(1);
        const main = await page.locator('main').innerText();
        for (const forbidden of ['MRR', 'ARR', 'الربح', 'Profit', 'الرصيد']) {
            expect(main).not.toContain(forbidden);
        }

        await expectClearOfBottomBar(page);
        await shot(page, testInfo, 'owner-mixed');
        expect(errors).toEqual([]);
    });

    test('show all overdue expands and collapses', async ({ page }) => {
        await page.goto('/');
        const toggle = page.locator('[data-today-overdue-toggle]');
        if (!(await toggle.count())) { test.skip(); }
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(page.locator('#today-overdue-more')).toBeVisible();
        await toggle.click();
        await expect(page.locator('#today-overdue-more')).toBeHidden();
    });

    test('collection card opens the owner payment sheet', async ({ page }) => {
        await page.goto('/');
        const toggle = page.locator('[data-today-overdue-toggle]');
        if (await toggle.count()) { await toggle.click(); }
        const card = page.locator('[data-work-type="collection"]:has([data-pending-receipt])').first();
        await expect(card).toBeVisible();
        await expect(card.locator('[data-pending-receipt]')).toBeVisible();
        await card.locator('[data-card-primary-action="payment"]').click();
        await expect(page).toHaveURL(/open=record-payment/);
        await expect(page.locator('#modal-record-payment')).toBeVisible();
        await expect(page.locator('#modal-record-payment form')).toHaveAttribute('action', /payments\/normal/);
        await expect(page.locator('[data-return-to-today]')).toBeVisible();
    });

    test('installation and follow-up cards open their workspace sheets', async ({ page }) => {
        await page.goto('/');
        const toggle = page.locator('[data-today-overdue-toggle]');
        if (await toggle.count()) { await toggle.click(); }

        const installation = page.locator('[data-work-type="installation"] [data-card-primary-action]').first();
        await expect(installation).toHaveAttribute('href', /open=complete-installation&appointment=\d+/);
        const followUp = page.locator('[data-card-primary-action="follow_up"]').first();
        await expect(followUp).toHaveAttribute('href', /open=follow-up&follow_up=\d+/);

        await installation.click();
        await expect(page.locator('#modal-complete-installation')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.locator('#modal-complete-installation')).toBeHidden();

        await page.goto('/');
        if (await toggle.count()) { await toggle.click(); }
        await page.locator('[data-card-primary-action="follow_up"]').first().click();
        await expect(page.locator('#modal-follow-up')).toBeVisible();
    });

    test('appointment result: one sheet, then back to Today with the item gone', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'phone-390', 'mutates fixtures; run once');
        const errors = watchErrors(page);
        await page.goto('/');
        const toggle = page.locator('[data-today-overdue-toggle]');
        if (await toggle.count()) { await toggle.click(); }

        const card = page.locator('[data-work-type="appointment"]').first();
        const itemId = await card.getAttribute('data-work-item');
        await card.locator('[data-card-primary-action]').click();
        await expect(page).toHaveURL(/open=appointment-result&appointment=\d+&from=today/);
        const sheet = page.locator('#modal-appointment-result');
        await expect(sheet).toBeVisible();
        await expect(sheet.locator('form')).toHaveAttribute('action', new RegExp(`appointments/${itemId.replace('apt-', '')}/compact-outcome`));
        await shot(page, testInfo, 'owner-appointment-sheet');

        await sheet.locator('label', { has: page.locator('input[name="first_decision"][value="no_show"]') }).click();
        await sheet.locator('button[type="submit"]').click();

        await expect(page).toHaveURL(/\/$/);
        await expect(page.locator('.notify-flash')).toBeVisible();
        await expect(page.locator(`[data-work-item="${itemId}"]`)).toHaveCount(0);
        expect(Number(await page.locator('[data-completed-total]').getAttribute('data-completed-total'))).toBeGreaterThanOrEqual(2);
        expect(errors).toEqual([]);
    });

    test('daily notes auto-save', async ({ page }) => {
        await page.goto('/');
        const editor = page.locator('[data-notes-editor]');
        await editor.scrollIntoViewIfNeeded();
        await editor.fill('ملاحظة مراجعة المرحلة 11');
        await expect(page.locator('[data-notes-state="saved"]')).toBeVisible();
        await page.reload();
        await expect(page.locator('[data-notes-editor]')).toHaveValue('ملاحظة مراجعة المرحلة 11');
    });

    test('open work mode renders grouped cards', async ({ page }, testInfo) => {
        const errors = watchErrors(page);
        await page.goto('/?mode=work');
        await expectCleanPage(page);
        await expect(page.locator('[data-today-tab="work"][aria-current="page"]')).toBeVisible();
        await expectCardsWellFormed(page);
        await expectClearOfBottomBar(page);
        await shot(page, testInfo, 'owner-open-work');
        expect(errors).toEqual([]);
    });

    test('English Today is LTR and readable', async ({ page }, testInfo) => {
        await page.goto('/locale/en');
        await page.goto('/');
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        await expectCleanPage(page);
        await expect(page.locator('h1')).toHaveText('Today');
        await expect(page.locator('#today-next-title')).toContainText('Next');
        await shot(page, testInfo, 'owner-en');
        await page.goto('/locale/ar');
    });
});

test.describe('quiet day', () => {
    test.use({ storageState: AUTH.quiet });

    test('my work empty state is calm', async ({ page }, testInfo) => {
        const errors = watchErrors(page);
        await page.goto('/?scope=my');
        await expectCleanPage(page);
        await expect(page.locator('[data-today-empty]')).toBeVisible();
        await expect(page.locator('[data-today-section]')).toHaveCount(0);
        await expect(page.locator('[data-daily-notes]')).toBeVisible();
        await shot(page, testInfo, 'quiet');
        expect(errors).toEqual([]);
    });
});

test.describe('staff today', () => {
    test.use({ storageState: AUTH.staff });

    test('my work, receipt path only, no owner controls', async ({ page }, testInfo) => {
        const errors = watchErrors(page);
        await page.goto('/?scope=my');
        await expectCleanPage(page);
        await expect(page.locator('[data-team-scope="my"][aria-current="true"]')).toBeVisible();
        expect(await page.locator('[data-work-item]').count()).toBeGreaterThan(0);
        await expectCardsWellFormed(page);
        await expect(page.locator('[data-today-signal="pending-confirmations"]')).toHaveCount(0);
        await expect(page.locator('[data-today-signal="my-pending-receipts"]')).toHaveCount(1);
        await expect(page.locator('[data-card-primary-action="payment"]')).toHaveCount(0);
        await expectClearOfBottomBar(page);
        await shot(page, testInfo, 'staff-my-work');

        await page.goto('/');
        const toggle = page.locator('[data-today-overdue-toggle]');
        if (await toggle.count()) { await toggle.click(); }
        const receipt = page.locator('[data-card-primary-action="payment_receipt"]').first();
        await expect(receipt).toBeVisible();
        await receipt.click();
        await expect(page.locator('#modal-record-payment form')).toHaveAttribute('action', /payment-receipts/);
        await page.keyboard.press('Escape');
        await expect(page.locator('#modal-record-payment')).toBeHidden();
        expect(errors).toEqual([]);
    });
});
