import { test, expect } from '@playwright/test';
import { AUTH } from './global-setup.js';

// P10 client experience review (§5, §19, §22–§24). Fixture ids come from DatabaseSeeder +
// BrowserSmokeSeeder on a fresh e2e database: 1 prospect with an appointment today,
// 4 subscriber (money due, credential, pending receipt), 5 former subscriber, 6 closed.
const WORKSPACES = {
    prospect: { id: 1, state: 'appointment_result' },
    subscriber: { id: 4, state: 'payment_overdue' },
    former: { id: 5, state: 'renewal' },
    closed: { id: 6, state: 'closed' },
};

async function expectCleanPage(page) {
    const { scrollWidth, clientWidth } = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
    }));
    expect(scrollWidth, 'no horizontal page overflow').toBeLessThanOrEqual(clientWidth);
    await expect(page.locator('h1')).toHaveCount(1);
    expect(await page.locator('main').innerText()).not.toContain('notify.client_hub');
}

async function expectClearOfBottomBar(page) {
    const bar = page.locator('.notify-mobile-nav');
    if (!(await bar.isVisible())) { return; }
    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    const barTop = (await bar.boundingBox()).y;
    const lastCard = await page.locator('main .notify-card, main .notify-client-row').last().boundingBox();
    expect(lastCard.y + lastCard.height, 'last card clears the bottom bar').toBeLessThanOrEqual(barTop + 1);
}

function watchErrors(page) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => message.type() === 'error' && errors.push(message.text()));
    return errors;
}

test.describe('owner client experience', () => {
    test.use({ storageState: AUTH.owner });

    for (const segment of ['prospects', 'subscribers', 'renewal', 'closed']) {
        test(`list: ${segment}`, async ({ page }, testInfo) => {
            const errors = watchErrors(page);
            await page.goto(`/clients?view=${segment}`);
            await expectCleanPage(page);
            const activeSegment = page.locator(`[data-segment="${segment}"][aria-current="page"]`);
            await expect(activeSegment).toBeVisible();
            const tab = await activeSegment.boundingBox();
            expect(tab.x, 'active segment scrolled into view').toBeGreaterThanOrEqual(0);
            expect(tab.x + tab.width).toBeLessThanOrEqual(page.viewportSize().width);
            const rows = page.locator('[data-client-row]');
            expect(await rows.count()).toBeGreaterThan(0);
            for (const row of await rows.all()) {
                expect(await row.locator('[data-client-quick-action]').count()).toBeLessThanOrEqual(1);
                await expect(row.locator('[data-client-signal]')).toBeVisible();
            }
            await expect(page.locator('[data-page-action="add-client"]')).toBeVisible();
            await expectClearOfBottomBar(page);
            if (process.env.P9_SHOTS) {
                await page.screenshot({ path: `storage/playwright/screens/${testInfo.project.name}-owner-list-${segment}.png` });
            }
            expect(errors).toEqual([]);
        });
    }

    for (const [name, fixture] of Object.entries(WORKSPACES)) {
        test(`workspace: ${name}`, async ({ page }, testInfo) => {
            const errors = watchErrors(page);
            await page.goto(`/clients/${fixture.id}`);
            await expectCleanPage(page);
            await expect(page.locator(`[data-client-state="${fixture.state}"]`)).toBeVisible();

            // The primary action is on the first screen.
            const primary = page.locator('[data-primary-action]');
            await expect(primary).toHaveCount(1);
            const box = await primary.boundingBox();
            expect(box.y + box.height).toBeLessThanOrEqual(page.viewportSize().height);
            expect(box.height).toBeGreaterThanOrEqual(44);
            expect(await page.locator('[data-secondary-action]').count()).toBeLessThanOrEqual(2);

            // "…" menu stays inside the viewport.
            const more = page.locator('[data-client-more]');
            if (await more.count()) {
                await more.locator('summary').click();
                const panel = await more.locator('.notify-menu__panel').boundingBox();
                expect(panel.x).toBeGreaterThanOrEqual(0);
                expect(panel.x + panel.width).toBeLessThanOrEqual(page.viewportSize().width);
                await page.keyboard.press('Escape');
                await expect(more).not.toHaveAttribute('open', '');
            }

            await expectClearOfBottomBar(page);
            if (process.env.P9_SHOTS) {
                await page.evaluate(() => window.scrollTo(0, 0));
                await page.screenshot({ path: `storage/playwright/screens/${testInfo.project.name}-owner-${name}.png` });
            }
            expect(errors).toEqual([]);
        });
    }

    test('primary sheet opens, fits and closes with focus return', async ({ page }) => {
        await page.goto(`/clients/${WORKSPACES.subscriber.id}`);
        const primary = page.locator('[data-primary-action]');
        await expect(primary).toHaveAttribute('data-open-sheet', 'modal-record-payment');
        await primary.click();
        const sheet = page.locator('#modal-record-payment');
        await expect(sheet).toBeVisible();
        const card = await sheet.locator('.notify-sheet__panel').boundingBox();
        expect(card.x).toBeGreaterThanOrEqual(0);
        expect(card.x + card.width).toBeLessThanOrEqual(page.viewportSize().width + 1);
        await expect(sheet.locator('form')).toHaveAttribute('action', /payments\/normal/);
        await expect(sheet.locator('button[type="submit"]')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(sheet).toBeHidden();
        await expect(primary).toBeFocused();
    });

    test('deep link opens the renewal sheet', async ({ page }) => {
        await page.goto(`/clients/${WORKSPACES.former.id}?open=start-subscription`);
        await expect(page.locator('#modal-start-subscription')).toBeVisible();
    });

    test('English workspace is LTR and readable', async ({ page }, testInfo) => {
        await page.goto('/locale/en');
        await page.goto(`/clients/${WORKSPACES.subscriber.id}`);
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        await expectCleanPage(page);
        await expect(page.locator('[data-client-state]')).toContainText('Next step');
        if (process.env.P9_SHOTS) {
            await page.screenshot({ path: `storage/playwright/screens/${testInfo.project.name}-owner-en-subscriber.png` });
        }
        await page.goto('/locale/ar');
    });
});

test.describe('staff client experience', () => {
    test.use({ storageState: AUTH.staff });

    test('list defaults to prospects', async ({ page }) => {
        await page.goto('/clients');
        await expectCleanPage(page);
        await expect(page.locator('[data-segment="prospects"][aria-current="page"]')).toBeVisible();
    });

    test('subscriber workspace offers the receipt path only', async ({ page }, testInfo) => {
        const errors = watchErrors(page);
        await page.goto(`/clients/${WORKSPACES.subscriber.id}`);
        await expectCleanPage(page);

        const primary = page.locator('[data-primary-action]');
        await expect(primary).toHaveAttribute('data-open-sheet', 'modal-record-payment');
        await primary.click();
        await expect(page.locator('#modal-record-payment form')).toHaveAttribute('action', /payment-receipts/);
        await page.keyboard.press('Escape');

        await expect(page.locator('[data-client-pending-receipts]')).toBeVisible();
        await expect(page.locator('[data-issue-contract]')).toHaveCount(0);
        await expect(page.locator('[data-subscription-cancel]')).toHaveCount(0);
        await expect(page.locator('[data-referral-commission]')).toHaveCount(0);
        await expect(page.locator('[data-money-details]')).toHaveCount(0);
        await expect(page.locator('[data-grant-access]')).toHaveCount(1);
        await expect(page.locator('[data-credential-card] [data-secret-display]')).toHaveText('••••••••');

        await expectClearOfBottomBar(page);
        if (process.env.P9_SHOTS) {
            await page.evaluate(() => window.scrollTo(0, 0));
            await page.screenshot({ path: `storage/playwright/screens/${testInfo.project.name}-staff-subscriber.png` });
        }
        expect(errors).toEqual([]);
    });
});
