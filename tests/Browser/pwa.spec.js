import { test, expect } from '@playwright/test';
import { AUTH } from './global-setup.js';

// P9.1 PWA smoke (phone 390 + iPad portrait 820): manifest, service worker, static-only cache,
// offline fallback and recovery. Online-only app: no business page may end up in any cache.
const STATIC_PREFIXES = ['/build/assets/', '/pwa/', '/brand/notify/'];

test.describe('installable PWA', () => {
    test.use({ storageState: AUTH.owner });
    test.skip(({ viewport }) => viewport.width >= 1024, 'focused PWA smoke runs at 390 and 820');

    async function controlledPage(page) {
        await page.goto('/');
        await page.evaluate(() => navigator.serviceWorker.ready);
        if (!(await page.evaluate(() => Boolean(navigator.serviceWorker.controller)))) {
            await page.reload();
        }
        await expect.poll(() => page.evaluate(() => Boolean(navigator.serviceWorker.controller))).toBe(true);
    }

    async function cachedPaths(page) {
        return page.evaluate(async () => {
            const paths = [];
            for (const key of await caches.keys()) {
                const cache = await caches.open(key);
                for (const request of await cache.keys()) {
                    paths.push(new URL(request.url).pathname + new URL(request.url).search);
                }
            }
            return paths;
        });
    }

    test('manifest and service worker are healthy', async ({ page, request }) => {
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        page.on('console', (message) => message.type() === 'error' && errors.push(message.text()));

        const manifestResponse = await request.get('/manifest.webmanifest');
        expect(manifestResponse.ok()).toBe(true);
        const manifest = await manifestResponse.json();
        expect(manifest).toMatchObject({ name: 'Notify Desk', short_name: 'Notify', display: 'standalone', theme_color: '#2873CD', start_url: '/', scope: '/' });

        await controlledPage(page);
        expect(await page.evaluate(async () => (await navigator.serviceWorker.getRegistration()).active.state)).toBe('activated');
        await expect(page.locator('link[rel="manifest"]')).toHaveCount(1);

        // P9 shell unchanged: bottom bar present, safe-area rules shipped with viewport-fit=cover.
        await expect(page.locator('.notify-mobile-nav__grid')).toBeVisible();
        await expect(page.locator('meta[name="viewport"]')).toHaveAttribute('content', /viewport-fit=cover/);
        const safeAreaRules = await page.evaluate(() => [...document.styleSheets].flatMap((sheet) => {
            try { return [...sheet.cssRules].map((rule) => rule.cssText); } catch { return []; }
        }).filter((text) => text.includes('safe-area-inset-bottom')).length);
        expect(safeAreaRules).toBeGreaterThan(2);

        // Chromium installability diagnostics (localhost counts as a secure context).
        const cdp = await page.context().newCDPSession(page);
        const { installabilityErrors } = await cdp.send('Page.getInstallabilityErrors');
        expect(installabilityErrors.map((error) => error.errorId)).toEqual([]);

        expect(errors).toEqual([]);
    });

    test('only static files are cached, even after visiting sensitive pages', async ({ page }) => {
        await controlledPage(page);
        for (const path of ['/clients', '/clients/1', '/finance', '/finance/collections', '/finance/reports', '/settings', '/profile', '/notifications']) {
            await page.goto(path);
        }

        const paths = await cachedPaths(page);
        expect(paths).toContain('/offline');
        for (const path of paths) {
            expect(path === '/offline' || STATIC_PREFIXES.some((prefix) => path.startsWith(prefix)), `unexpected cached ${path}`).toBe(true);
        }
    });

    test('offline navigation shows the branded fallback and recovers online', async ({ page, context }) => {
        await controlledPage(page);

        await context.setOffline(true);
        await page.goto('/clients').catch(() => {});
        const offline = page.locator('[data-offline-page]');
        await expect(offline).toBeVisible();
        await expect(page.locator('h1')).toHaveText('لا يوجد اتصال بالإنترنت');
        await expect(offline).toContainText('No internet connection');
        await expect(page.locator('[data-offline-retry]')).toBeVisible();
        await expect(page.locator('.notify-sidebar, .notify-mobile-nav, [data-nav-destination]')).toHaveCount(0);
        expect(page.url()).toContain('/clients');
        const box = await page.locator('[data-offline-retry]').boundingBox();
        expect(box.height).toBeGreaterThanOrEqual(44);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        if (process.env.P9_SHOTS) {
            await page.screenshot({ path: `storage/playwright/screens/${test.info().project.name}-offline.png` });
        }

        // Back online: the offline page reloads itself into the real, network-fresh page.
        await Promise.all([page.waitForURL('**/clients'), page.waitForEvent('load'), context.setOffline(false)]);
        await expect(page.locator('[data-offline-page]')).toHaveCount(0);
        await expect(page.locator('.notify-mobile-nav__grid [data-nav-destination="clients"]')).toHaveAttribute('aria-current', 'page');
    });

    test('connection feedback appears while offline in the shell', async ({ page, context }) => {
        await controlledPage(page);
        const status = page.locator('[data-connection-status]');

        await context.setOffline(true);
        await expect(status.locator('.notify-connection-status__pill--offline')).toBeVisible();
        await context.setOffline(false);
        await expect(status.locator('.notify-connection-status__pill--offline')).toBeHidden();
        await expect(status.locator('.notify-connection-status__pill--online')).toBeVisible();
        await expect(status.locator('.notify-connection-status__pill--online')).toBeHidden({ timeout: 6000 });
    });
});
