<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Support\ClientLifecycle;
use App\Support\Pwa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * P9.1 — installable PWA foundation. Online-only: the worker may keep static files and the offline
 * page, never application documents, business data or credentials.
 */
class V1P91PwaFoundationTest extends TestCase
{
    use RefreshDatabase;

    private const SENSITIVE_URLS = [
        '/', '/?mode=daily', '/clients', '/clients/1', '/clients/1/edit', '/clients/1/custom-projects',
        '/clients/1/credentials/1/reveal', '/clients/1/credentials/1/copied', '/clients/1/credentials/1/send',
        '/finance', '/finance/collections', '/finance/expenses', '/finance/accounts', '/finance/accounting',
        '/finance/reports', '/finance/reports/profit-and-loss/export', '/finance/capital', '/collections-due',
        '/collections', '/contracts/1/preview', '/contracts/1/download-pdf', '/settings', '/profile',
        '/notifications', '/administration/team', '/commercial-catalog', '/clients-import', '/custom-projects',
        '/login', '/logout', '/locale/en', '/health', '/manifest.webmanifest', '/sw.js',
        '/build/manifest.json', '/build/assets/app.css?user=1',
    ];

    // ----- Manifest -----------------------------------------------------------

    public function test_manifest_is_public_and_describes_an_installable_app(): void
    {
        $response = $this->get(route('pwa.manifest'))->assertOk();
        $this->assertStringStartsWith('application/manifest+json', $response->headers->get('Content-Type'));

        $manifest = $response->json();
        $this->assertSame('Notify Desk', $manifest['name']);
        $this->assertSame('Notify', $manifest['short_name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('#2873CD', $manifest['theme_color']);
        $this->assertSame('#F7F6F3', $manifest['background_color']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('/', $manifest['id']);
        $this->assertArrayNotHasKey('orientation', $manifest);
        $this->assertStringContainsString('#F7F6F3', file_get_contents(resource_path('css/app.css')));
    }

    public function test_manifest_declares_standard_and_maskable_icons_that_exist(): void
    {
        $icons = collect($this->get(route('pwa.manifest'))->json('icons'));

        foreach (['192x192', '512x512'] as $size) {
            $this->assertTrue($icons->contains(fn ($icon) => $icon['sizes'] === $size && $icon['purpose'] === 'any'), "any {$size}");
            $this->assertTrue($icons->contains(fn ($icon) => $icon['sizes'] === $size && $icon['purpose'] === 'maskable'), "maskable {$size}");
        }

        foreach ($icons as $icon) {
            $this->assertSame('image/png', $icon['type']);
            $file = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($file);
            [$width, $height] = getimagesize($file);
            $this->assertSame($icon['sizes'], "{$width}x{$height}");
        }

        [$width, $height] = getimagesize(public_path('pwa/apple-touch-icon.png'));
        $this->assertSame([180, 180], [$width, $height]);
    }

    // ----- Layout integration -------------------------------------------------

    public function test_app_shell_and_login_carry_pwa_metadata_once(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true]);
        $pages = ['shell' => $this->actingAs($owner)->get(route('dashboard'))->assertOk()->getContent()];
        auth()->logout();
        $pages['login'] = $this->get(route('login'))->assertOk()->getContent();

        foreach ($pages as $name => $html) {
            foreach ([
                '<link rel="manifest" href="'.route('pwa.manifest').'">',
                '<meta name="theme-color" content="#2873CD">',
                '<meta name="mobile-web-app-capable" content="yes">',
                '<meta name="apple-mobile-web-app-capable" content="yes">',
                '<meta name="apple-mobile-web-app-title" content="Notify">',
                '<link rel="apple-touch-icon" href="'.asset('pwa/apple-touch-icon.png').'">',
                'navigator.serviceWorker.register("\/sw.js", { scope: "\/" })',
            ] as $needle) {
                $this->assertSame(1, substr_count($html, $needle), "{$name}: {$needle}");
            }
            $this->assertSame(1, substr_count($html, 'name="theme-color"'), "{$name}: one theme-color");
        }

        // P9 shell unchanged.
        $this->assertStringContainsString('<aside class="notify-sidebar"', $pages['shell']);
        $this->assertStringContainsString('viewport-fit=cover', $pages['shell']);
        $this->assertStringContainsString('data-connection-status', $pages['shell']);
    }

    public function test_standalone_documents_are_not_turned_into_app_pages(): void
    {
        foreach (['contracts/document.blade.php', 'contracts/partials/pdf-footer.blade.php', 'pwa/offline.blade.php'] as $view) {
            $source = file_get_contents(resource_path('views/'.$view));
            $this->assertStringNotContainsString('pwa-head', $source, $view);
            $this->assertStringNotContainsString('serviceWorker', $source, $view);
        }
    }

    // ----- Service worker -----------------------------------------------------

    public function test_service_worker_is_public_javascript_that_is_always_revalidated(): void
    {
        $response = $this->get(route('pwa.service-worker'))->assertOk();

        $this->assertStringStartsWith('application/javascript', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('const CACHE_NAME = "notify-static-'.Pwa::cacheVersion().'"', $response->getContent());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', Pwa::cacheVersion());
        $this->assertSame(Pwa::cacheVersion(), Pwa::cacheVersion());
    }

    public function test_worker_only_caches_allow_listed_static_files(): void
    {
        $this->assertSame(['/build/assets/', '/pwa/', '/brand/notify/'], Pwa::staticPathPrefixes());

        $precache = Pwa::precacheUrls();
        $this->assertContains('/offline', $precache);
        foreach ($precache as $url) {
            $this->assertTrue($url === '/offline' || Pwa::isCacheable('GET', $url), "Precache {$url} must be static");
        }

        $source = $this->get(route('pwa.service-worker'))->getContent();
        $this->assertStringContainsString('const STATIC_PATH_PREFIXES = ["\/build\/assets\/","\/pwa\/","\/brand\/notify\/"]', $source);
        // Exactly one place writes to the cache (cacheFirst for allow-listed static files) plus install precache.
        $this->assertSame(1, substr_count($source, 'cache.put('));
        $this->assertSame(1, substr_count($source, 'cache.addAll('));
        $this->assertMatchesRegularExpression('/if \(isStaticAsset\(new URL\(request\.url\)\)\) \{\s+event\.respondWith\(cacheFirst\(request\)\);/', $source);
        // Navigations are network-only; the offline page is used only when fetch rejects.
        $this->assertMatchesRegularExpression('/async function networkOnlyNavigation\(request\) \{\s+try \{\s+return await fetch\(request\);\s+\} catch \(error\) \{\s+return \(await caches\.match\(OFFLINE_URL\)\) \|\| Response\.error\(\);/', $source);
    }

    public function test_mutations_are_never_intercepted_cached_or_retried(): void
    {
        $source = $this->get(route('pwa.service-worker'))->getContent();

        $this->assertMatchesRegularExpression("/if \\(request\\.method !== 'GET'\\) \\{\\s+return;\\s+\\}/", $source);
        foreach (["addEventListener('sync'", "addEventListener('push'", 'periodicsync', 'indexedDB', 'localStorage', 'BackgroundSync', 'clients.openWindow', 'postMessage'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->assertFalse(Pwa::isCacheable($method, '/build/assets/app.css'), $method);
            $this->assertFalse(Pwa::isCacheable($method, '/clients/1/credentials/1/reveal'), $method);
        }
    }

    public function test_sensitive_and_authenticated_paths_can_never_be_cached(): void
    {
        foreach (self::SENSITIVE_URLS as $url) {
            $this->assertFalse(Pwa::isCacheable('GET', $url), "{$url} must stay network-only");
        }
        $this->assertFalse(Pwa::isCacheable('GET', 'https://evil.example/build/assets/app.css'));
        $this->assertTrue(Pwa::isCacheable('GET', '/build/assets/app-abc123.css'));
        $this->assertTrue(Pwa::isCacheable('GET', '/pwa/icon-192.png'));
    }

    public function test_no_application_route_falls_inside_the_static_cache_allow_list(): void
    {
        $checked = 0;
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $path = '/'.ltrim(preg_replace('/\{[^}]+\}/', '1', $route->uri()), '/');
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }
                $this->assertFalse(Pwa::isCacheable($method, $path), "{$method} {$path} ({$route->getName()}) must not be cacheable");
                $checked++;
            }
        }
        $this->assertGreaterThan(100, $checked);

        foreach (['clients.credentials.reveal', 'clients.credentials.copied', 'clients.credentials.send'] as $name) {
            $url = route($name, ['client' => 1, 'credential' => 1], false);
            $this->assertFalse(Pwa::isCacheable('GET', $url));
            $this->assertFalse(Pwa::isCacheable('POST', $url));
        }
    }

    // ----- Offline page -------------------------------------------------------

    public function test_offline_page_is_public_bilingual_and_has_retry(): void
    {
        $html = $this->get(route('pwa.offline'))->assertOk()->getContent();

        foreach (['ar', 'en'] as $locale) {
            $this->assertStringContainsString(e(__('notify.pwa.offline.title', [], $locale)), $html);
            $this->assertStringContainsString(e(__('notify.pwa.offline.message', [], $locale)), $html);
            $this->assertStringContainsString(e(__('notify.pwa.offline.retry', [], $locale)), $html);
        }
        $this->assertStringContainsString('لا يوجد اتصال بالإنترنت', $html);
        $this->assertStringContainsString('No internet connection', $html);
        $this->assertStringContainsString('data-offline-retry', $html);
        $this->assertStringContainsString("addEventListener('online'", $html);
        $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html));

        $this->withSession(['locale' => 'en'])->get(route('pwa.offline'))->assertOk()->assertSee('<html lang="en" dir="ltr">', false);
    }

    public function test_offline_page_never_contains_user_or_business_data(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true, 'name' => 'Owner Person', 'email' => 'owner-secret@example.test']);
        Client::create([
            'business_name' => 'Secret Bakery', 'phone' => '0799999999', 'city_area' => 'Amman',
            'business_category' => 'Bakery', 'lead_source' => 'Google Maps', 'status' => 'subscriber',
            'stage' => ClientLifecycle::SUBSCRIBER,
        ]);

        $html = $this->actingAs($owner)->get(route('pwa.offline'))->assertOk()->getContent();

        foreach (['Owner Person', 'owner-secret@example.test', 'Secret Bakery', '0799999999', '_token', 'csrf', 'notify-sidebar', 'notify-mobile-nav', 'data-nav-destination', 'JOD', 'د.أ'] as $leak) {
            $this->assertStringNotContainsString($leak, $html, "Offline page leaked {$leak}");
        }
    }

    public function test_pwa_lang_keys_have_parity(): void
    {
        $ar = require lang_path('ar/notify.php');
        $en = require lang_path('en/notify.php');
        $this->assertSame(array_keys(\Illuminate\Support\Arr::dot($ar['pwa'])), array_keys(\Illuminate\Support\Arr::dot($en['pwa'])));
    }

    // ----- Authentication unchanged ------------------------------------------

    public function test_authentication_flow_is_unchanged(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('clients.index'))->assertRedirect(route('login'));

        foreach (['pwa.manifest', 'pwa.service-worker', 'pwa.offline'] as $name) {
            $this->get(route($name))->assertOk();
        }

        $manifest = json_encode($this->get(route('pwa.manifest'))->json());
        $worker = $this->get(route('pwa.service-worker'))->getContent();
        foreach (['password', 'token', 'session', 'cookie', 'credential'] as $secret) {
            $this->assertStringNotContainsStringIgnoringCase($secret, $manifest);
            $this->assertStringNotContainsStringIgnoringCase($secret, $worker);
        }
    }
}
