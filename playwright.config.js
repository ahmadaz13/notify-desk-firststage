import { defineConfig } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

// Minimal browser smoke tests (§32, FROZEN D-23): navigation + overflow at the critical viewports.
// Requires built assets (`npm run build`). Uses its own throw-away SQLite database.
const port = 8124;
const database = path.resolve('database/e2e.sqlite');
if (!fs.existsSync(database)) {
    fs.writeFileSync(database, '');
}

export default defineConfig({
    testDir: 'tests/Browser',
    outputDir: 'storage/playwright/results',
    fullyParallel: false,
    workers: 1,
    timeout: 60_000,
    reporter: [['list']],
    globalSetup: './tests/Browser/global-setup.js',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        locale: 'ar',
        trace: 'off',
    },
    projects: [
        { name: 'phone-390', use: { viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true } },
        { name: 'ipad-portrait-820', use: { viewport: { width: 820, height: 1180 }, hasTouch: true } },
        { name: 'ipad-landscape-1024', use: { viewport: { width: 1024, height: 768 }, hasTouch: true } },
        { name: 'desktop-1440', use: { viewport: { width: 1440, height: 900 } } },
    ],
    webServer: {
        command: 'php artisan migrate:fresh --seed --force && php artisan db:seed --class=BrowserSmokeSeeder --force && cd public && php -S 127.0.0.1:8124 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
        url: `http://127.0.0.1:${port}/health`,
        reuseExistingServer: false,
        stdout: 'ignore',
        timeout: 120_000,
        env: {
            APP_ENV: 'local',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: database,
            SESSION_DRIVER: 'file',
            CACHE_STORE: 'array',
        },
    },
});
