<?php

namespace App\Support;

/**
 * Installable-PWA foundation (P9.1).
 *
 * Notify Desk is online-only: the service worker may keep versioned static files and the branded
 * offline page, never application documents or business data. The static allow-list below is the
 * single source for both the generated service worker and the PHP-side policy check.
 */
class Pwa
{
    public const NAME = 'Notify Desk';

    public const SHORT_NAME = 'Notify';

    public const THEME_COLOR = '#2873CD';

    /** Light application canvas (--bg in resources/css/app.css). */
    public const BACKGROUND_COLOR = '#F7F6F3';

    public const CACHE_PREFIX = 'notify-static-';

    /** Bump when the service-worker logic itself changes. */
    private const WORKER_REVISION = 1;

    /** Public directories whose files are static, non-personal and safe to cache. */
    private const STATIC_DIRECTORIES = ['build/assets/', 'pwa/', 'brand/notify/'];

    private const ICONS = [
        ['file' => 'pwa/icon-192.png', 'sizes' => '192x192', 'purpose' => 'any'],
        ['file' => 'pwa/icon-512.png', 'sizes' => '512x512', 'purpose' => 'any'],
        ['file' => 'pwa/icon-maskable-192.png', 'sizes' => '192x192', 'purpose' => 'maskable'],
        ['file' => 'pwa/icon-maskable-512.png', 'sizes' => '512x512', 'purpose' => 'maskable'],
    ];

    /** @return array<string, mixed> */
    public static function manifest(): array
    {
        return [
            'id' => self::path(route('dashboard')),
            'name' => self::NAME,
            'short_name' => self::SHORT_NAME,
            'description' => __('notify.pwa.description', [], config('app.locale')),
            'lang' => config('app.locale'),
            'dir' => 'auto',
            'start_url' => self::path(route('dashboard')),
            'scope' => self::scope(),
            'display' => 'standalone',
            'background_color' => self::BACKGROUND_COLOR,
            'theme_color' => self::THEME_COLOR,
            'categories' => ['business', 'productivity'],
            'icons' => array_map(fn (array $icon) => [
                'src' => self::path(asset($icon['file'])),
                'sizes' => $icon['sizes'],
                'type' => 'image/png',
                'purpose' => $icon['purpose'],
            ], self::ICONS),
        ];
    }

    /** Service-worker scope: the application root. */
    public static function scope(): string
    {
        return rtrim(self::path(url('/')), '/').'/';
    }

    /** @return array<int, string> URL path prefixes the service worker may cache. */
    public static function staticPathPrefixes(): array
    {
        return array_map(fn (string $directory) => rtrim(self::path(asset($directory)), '/').'/', self::STATIC_DIRECTORIES);
    }

    /** PHP mirror of the worker's isStaticAsset(): same-origin GET, no query string, allow-listed prefix. */
    public static function isCacheable(string $method, string $url): bool
    {
        if (strtoupper($method) !== 'GET') {
            return false;
        }

        $parts = parse_url($url);
        $base = parse_url(url('/'));
        if (isset($parts['host']) && ($parts['host'] !== ($base['host'] ?? null) || ($parts['port'] ?? null) !== ($base['port'] ?? null))) {
            return false;
        }
        if (! empty($parts['query'])) {
            return false;
        }

        $path = $parts['path'] ?? '/';
        foreach (self::staticPathPrefixes() as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> Files stored when the worker installs. */
    public static function precacheUrls(): array
    {
        $urls = [self::path(route('pwa.offline'))];
        foreach (self::viteEntryFiles() as $file) {
            $urls[] = self::path(asset('build/'.$file));
        }
        foreach ([...array_column(self::ICONS, 'file'), 'pwa/apple-touch-icon.png', 'pwa/favicon-32.png'] as $file) {
            $urls[] = self::path(asset($file));
        }

        return array_values(array_unique($urls));
    }

    /**
     * Deterministic cache version: changes whenever a new Vite build, icon set or worker revision
     * ships, so the previous static cache is retired on activation.
     */
    public static function cacheVersion(): string
    {
        $manifest = public_path('build/manifest.json');
        $fingerprint = [self::WORKER_REVISION, is_file($manifest) ? sha1_file($manifest) : 'no-build'];
        foreach (self::ICONS as $icon) {
            $fingerprint[] = is_file(public_path($icon['file'])) ? sha1_file(public_path($icon['file'])) : 'missing';
        }

        return substr(sha1(implode('|', $fingerprint)), 0, 12);
    }

    /** @return array<int, string> Built CSS/JS for the Vite entry points (hashed file names). */
    private static function viteEntryFiles(): array
    {
        $manifest = public_path('build/manifest.json');
        if (! is_file($manifest)) {
            return [];
        }

        $entries = json_decode((string) file_get_contents($manifest), true) ?: [];
        $files = [];
        foreach (['resources/css/app.css', 'resources/js/app.js'] as $entry) {
            if (isset($entries[$entry]['file'])) {
                $files[] = $entries[$entry]['file'];
            }
            foreach ($entries[$entry]['css'] ?? [] as $css) {
                $files[] = $css;
            }
        }

        return $files;
    }

    private static function path(string $url): string
    {
        return parse_url($url, PHP_URL_PATH) ?: '/';
    }
}
