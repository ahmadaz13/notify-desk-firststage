<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use App\Services\PaymentReceiptService;
use App\Support\ClientLifecycle;
use App\Support\ShellNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P9 — UX foundation & navigation (§3, §22–§26).
 * Visibility comes from the permission matrix; hiding is UX only and never replaces authorization.
 */
class V1P9ShellNavigationTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER_SIDEBAR = [
        'today', 'clients', 'custom-projects',
        'finance-overview', 'finance-collections', 'finance-expenses', 'finance-accounts', 'finance-accounting', 'finance-reports',
        // P13 adds Operational Reference Data (§3.1).
        'admin-systems', 'admin-team', 'admin-reference-data', 'admin-import', 'admin-settings',
    ];

    private const OWNER_ONLY = [
        'finance', 'finance-overview', 'finance-collections', 'finance-expenses', 'finance-accounts', 'finance-accounting',
        'finance-reports', 'finance-capital', 'admin-systems', 'admin-team', 'admin-reference-data', 'admin-import', 'admin-settings',
    ];

    private const LEGACY_PATHS = ['/collections', '/financial-accounts', '/operating-expenses', '/accounting', '/executive', '/saas-metrics', '/subscription-billing', '/capital-management', '/finance?section'];

    private User $founder;

    private User $cofounder;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->founder = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true]);
        $this->cofounder = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true]);
    }

    // ----- Owner navigation -------------------------------------------------

    /** D-25 (P13.1): two navigation experiences only — Founder (owner) and Staff. */
    public function test_only_founder_and_staff_navigation_experiences_exist(): void
    {
        $founderSidebar = $this->destinations($this->sidebar($this->page($this->founder, route('dashboard'))));
        $staffSidebar = $this->destinations($this->sidebar($this->page($this->staff, route('dashboard'))));
        $this->assertSame(self::OWNER_SIDEBAR, $founderSidebar);
        $this->assertNotSame($founderSidebar, $staffSidebar);

        // A user still carrying the deferred Admin value gets no application shell at all.
        $legacyAdmin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($legacyAdmin)->get(route('dashboard'))->assertForbidden();
    }

    public function test_owner_sidebar_has_frozen_primary_destinations_and_groups_in_order(): void
    {
        foreach ([$this->founder, $this->cofounder] as $owner) {
            $sidebar = $this->sidebar($this->page($owner, route('dashboard')));

            $this->assertSame(self::OWNER_SIDEBAR, $this->destinations($sidebar));
            $this->assertStringContainsString('data-nav-group="finance"', $sidebar);
            $this->assertStringContainsString('data-nav-group="administration"', $sidebar);
            $this->assertStringNotContainsString('data-nav-destination="collections-due"', $sidebar);
        }
    }

    public function test_owner_finance_and_administration_links_use_canonical_routes_only(): void
    {
        $html = $this->page($this->founder, route('dashboard'));
        $sidebar = $this->sidebar($html);

        $expected = [
            'finance-overview' => route('finance.index'),
            'finance-collections' => route('finance.collections'),
            'finance-expenses' => route('finance.expenses'),
            'finance-accounts' => route('finance.accounts'),
            'finance-accounting' => route('finance.accounting'),
            'finance-reports' => route('finance.reports'),
            'admin-systems' => route('commercial-catalog.index'),
            'admin-team' => route('administration.team'),
            'admin-reference-data' => route('administration.reference-data'),
            'admin-import' => route('clients.import'),
            'admin-settings' => route('settings.index'),
            'custom-projects' => route('custom-projects.index'),
        ];
        foreach ($expected as $destination => $href) {
            $this->assertMatchesRegularExpression('/href="'.preg_quote($href, '/').'" data-nav-destination="'.$destination.'"/', $sidebar);
        }

        foreach (self::LEGACY_PATHS as $legacy) {
            $this->assertStringNotContainsString('href="'.url($legacy).'"', $html, "Navigation links to legacy {$legacy}");
        }
    }

    public function test_capital_destination_follows_the_feature_flag(): void
    {
        Setting::set('feature_capital_financing', '0');
        $off = $this->page($this->founder, route('dashboard'));
        $this->assertStringNotContainsString('data-nav-destination="finance-capital"', $off);

        Setting::set('feature_capital_financing', '1');
        $on = $this->page($this->founder, route('finance.index'));
        $sidebar = $this->sidebar($on);
        $this->assertStringContainsString('data-nav-destination="finance-capital"', $sidebar);
        $this->assertStringContainsString('href="'.route('finance.capital').'"', $sidebar);
        // In-page Finance sections read the same source.
        $this->assertStringContainsString('data-finance-section="capital"', $on);

        // Staff never sees it, flag or not.
        $this->assertStringNotContainsString('finance-capital', $this->page($this->staff, route('dashboard')));
    }

    public function test_pending_receipts_badge_appears_on_collections_for_approvers_only(): void
    {
        $client = Client::create([
            'business_name' => 'Badge Bakery', 'phone' => '0790000001', 'city_area' => 'Amman',
            'business_category' => 'Bakery', 'lead_source' => 'Google Maps', 'status' => 'subscriber',
            'stage' => ClientLifecycle::SUBSCRIBER,
        ]);
        app(PaymentReceiptService::class)->submit($client, [
            'amount' => '10.000', 'payment_method' => 'cash', 'received_at' => now()->subHour()->format('Y-m-d H:i'),
        ], $this->staff, 'rcpt_'.Str::uuid());

        $sidebar = $this->sidebar($this->page($this->founder, route('dashboard')));
        $collections = str($sidebar)->after('data-nav-destination="finance-collections"')->before('</a>')->toString();
        $this->assertStringContainsString('notify-nav-item__badge', $collections);
        $this->assertStringContainsString('>1<', $collections);

        $this->assertStringNotContainsString('notify-nav-item__badge', $this->page($this->staff, route('dashboard')));
    }

    // ----- Staff navigation -------------------------------------------------

    public function test_staff_sidebar_is_today_clients_collections_due_custom_projects(): void
    {
        $html = $this->page($this->staff, route('dashboard'));

        $this->assertSame(['today', 'clients', 'collections-due', 'custom-projects'], $this->destinations($this->sidebar($html)));
        $this->assertStringNotContainsString('data-nav-group=', $html);
        foreach (self::OWNER_ONLY as $destination) {
            $this->assertStringNotContainsString('data-nav-destination="'.$destination.'"', $html, "Staff must not see {$destination}");
        }
    }

    public function test_navigation_hiding_does_not_replace_server_authorization(): void
    {
        foreach (['finance.index', 'finance.collections', 'finance.expenses', 'finance.accounts', 'finance.accounting', 'finance.reports', 'commercial-catalog.index', 'administration.team', 'clients.import', 'settings.index', 'custom-projects.create'] as $route) {
            $this->actingAs($this->staff)->get(route($route))->assertForbidden();
        }
        $this->actingAs($this->staff)->get(route('custom-projects.index'))->assertOk();
        $this->actingAs($this->staff)->get(route('collections-due.index'))->assertOk();
    }

    // ----- Phone / iPad-portrait navigation ---------------------------------

    public function test_bottom_bar_is_exactly_three_destinations_plus_more_per_role(): void
    {
        $this->assertSame(['today', 'clients', 'finance', 'more'], $this->destinations($this->bottomBar($this->page($this->founder, route('dashboard')))));
        $this->assertSame(['today', 'clients', 'collections-due', 'more'], $this->destinations($this->bottomBar($this->page($this->staff, route('dashboard')))));

        $staffBar = $this->bottomBar($this->page($this->staff, route('dashboard'), 'en'));
        $this->assertStringContainsString('>Collections<', $staffBar);
        $this->assertStringContainsString('href="'.route('collections-due.index').'"', $staffBar);
        $ownerBar = $this->bottomBar($this->page($this->founder, route('dashboard')));
        $this->assertStringContainsString('href="'.route('finance.index').'"', $ownerBar);
    }

    public function test_more_sheet_is_grouped_and_permission_filtered(): void
    {
        $ownerMore = $this->moreSheet($this->page($this->founder, route('dashboard')));
        $this->assertSame(
            ['custom-projects', 'notifications', 'admin-systems', 'admin-team', 'admin-reference-data', 'admin-import', 'admin-settings', 'profile', 'change-password', 'language', 'logout'],
            $this->destinations($ownerMore),
        );
        foreach (['work', 'administration', 'account'] as $layer) {
            $this->assertStringContainsString('data-mobile-nav-layer="'.$layer.'"', $ownerMore);
        }

        $staffMore = $this->moreSheet($this->page($this->staff, route('dashboard')));
        $this->assertSame(['custom-projects', 'notifications', 'profile', 'change-password', 'language', 'logout'], $this->destinations($staffMore));
        $this->assertStringNotContainsString('data-mobile-nav-layer="administration"', $staffMore);
    }

    // ----- Active state -----------------------------------------------------

    public function test_active_state_follows_route_families(): void
    {
        $client = Client::create([
            'business_name' => 'Active Cafe', 'phone' => '0790000002', 'city_area' => 'Amman',
            'business_category' => 'Cafe', 'lead_source' => 'Google Maps', 'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ]);

        $cases = [
            [route('clients.show', $client), 'clients', 'clients', false],
            [route('clients.edit', $client), 'clients', 'clients', false],
            [route('clients.create'), 'clients', 'clients', false],
            [route('finance.collections'), 'finance-collections', 'finance', false],
            [route('finance.reports'), 'finance-reports', 'finance', false],
            [route('custom-projects.index'), 'custom-projects', null, true],
            [route('clients.custom-projects.index', $client), 'custom-projects', null, true],
            [route('administration.team'), 'admin-team', null, true],
            [route('administration.reference-data'), 'admin-reference-data', null, true],
            [route('clients.import'), 'admin-import', null, true],
            [route('settings.index'), 'admin-settings', null, true],
            [route('commercial-catalog.index'), 'admin-systems', null, true],
        ];

        foreach ($cases as [$url, $sidebarCurrent, $barCurrent, $moreActive]) {
            $html = $this->page($this->founder, $url);
            $sidebar = $this->sidebar($html);

            $this->assertSame([$sidebarCurrent], $this->currentDestinations($sidebar), "Sidebar current for {$url}");
            $bar = $this->bottomBar($html);
            $this->assertSame($barCurrent === null ? [] : [$barCurrent], $this->currentDestinations($bar), "Bar current for {$url}");
            $this->assertSame($moreActive, (bool) preg_match('/class="notify-mobile-nav__item is-active"[^>]*data-nav-destination="more"/', $bar), "More active for {$url}");
        }

        // Group context stays visible on child pages.
        $finance = $this->sidebar($this->page($this->founder, route('finance.expenses')));
        $this->assertStringContainsString('class="notify-nav-section is-active" data-nav-group="finance"', $finance);
        $admin = $this->sidebar($this->page($this->founder, route('administration.index')));
        $this->assertStringContainsString('class="notify-nav-section is-active" data-nav-group="administration"', $admin);
        $this->assertSame([], $this->currentDestinations($admin));
    }

    public function test_staff_custom_projects_is_current_in_sidebar_and_more(): void
    {
        $html = $this->page($this->staff, route('custom-projects.index'));
        $this->assertSame(['custom-projects'], $this->currentDestinations($this->sidebar($html)));
        $this->assertSame(['custom-projects'], $this->currentDestinations($this->moreSheet($html)));
        $this->assertSame(['collections-due'], $this->currentDestinations($this->sidebar($this->page($this->staff, route('collections-due.index')))));
    }

    // ----- Header, page header, accessibility, theme --------------------------

    public function test_header_is_language_notifications_and_account_menu_only(): void
    {
        $html = $this->page($this->founder, route('clients.index'), 'en');
        $header = str($html)->betweenFirst('<header class="notify-topbar">', '</header>')->toString();

        $this->assertStringContainsString('data-shell-action="notifications"', $header);
        $this->assertStringContainsString('aria-label="Notifications"', $header);
        $this->assertStringContainsString('aria-controls="notify-profile-menu"', $header);
        $this->assertStringContainsString('aria-label="Account menu"', $header);
        $this->assertStringContainsString(route('locale.switch', 'ar'), $header);
        $this->assertStringContainsString(route('logout'), $header);
        $this->assertStringContainsString('href="'.route('profile.edit').'#password"', $header);
        $this->assertStringNotContainsString('add-client', $header);
        $this->assertStringNotContainsString('<h1', $header);
        $this->assertStringContainsString('data-shell-area="clients"', $header);
    }

    public function test_every_page_has_exactly_one_h1_and_landmarks(): void
    {
        foreach ([
            [$this->founder, route('dashboard')],
            [$this->founder, route('clients.index')],
            [$this->founder, route('finance.index')],
            [$this->founder, route('custom-projects.index')],
            [$this->founder, route('settings.index')],
            [$this->staff, route('collections-due.index')],
            [$this->staff, route('custom-projects.index')],
        ] as [$user, $url]) {
            $html = $this->page($user, $url);
            $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html), "One h1 on {$url}");
            $this->assertStringContainsString('<aside class="notify-sidebar" aria-label=', $html);
            $this->assertMatchesRegularExpression('/<nav\s+class="notify-mobile-nav"\s+aria-label="[^"]+"/', $html);
            $this->assertStringContainsString('<main id="notify-main-content"', $html);
            $this->assertStringContainsString('class="notify-skip-link" href="#notify-main-content"', $html);
        }
    }

    public function test_page_header_carries_the_primary_action(): void
    {
        $clients = $this->page($this->founder, route('clients.index'), 'en');
        $header = str($clients)->between('data-page-header', '</header>')->toString();
        $this->assertStringContainsString('Clients', $header);
        $this->assertStringContainsString('data-page-action="add-client"', $header);
        $this->assertStringNotContainsString('CLIENTS_01', $clients);

        $ownerProjects = $this->page($this->founder, route('custom-projects.index'));
        $this->assertStringContainsString('data-page-action="create-custom-project"', $ownerProjects);
        $staffProjects = $this->page($this->staff, route('custom-projects.index'));
        $this->assertStringNotContainsString('data-page-action="create-custom-project"', $staffProjects);
    }

    public function test_dark_mode_is_removed_and_theme_is_light_only(): void
    {
        $html = $this->page($this->founder, route('dashboard'));
        $this->assertStringNotContainsString('notify_theme', $html);
        $this->assertStringNotContainsString('toggleTheme', $html);
        $this->assertStringContainsString('<meta name="color-scheme" content="light">', $html);

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringNotContainsString('[data-theme="dark"]', $css);
        $this->assertStringContainsString('color-scheme: light', $css);
        $this->assertStringContainsString('--primary: #2873CD', $css);

        $this->assertFalse(\Illuminate\Support\Facades\Lang::has('notify.shell.toggle_theme', 'ar', false));
        $this->assertStringNotContainsString('toggleTheme', file_get_contents(resource_path('views/components/notify/app-shell.blade.php')));
    }

    public function test_css_uses_only_breakpoint_tokens(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        preg_match_all('/@media[^{]*/', $css, $queries);
        preg_match_all('/(?:min|max)-width:\s*([\d.]+)px/', implode(' ', $queries[0]), $widths);

        $allowed = ['600', '768', '1024', '1280', '599.98', '767.98', '1023.98', '1279.98'];
        $this->assertNotEmpty($widths[1]);
        $this->assertSame([], array_values(array_diff(array_unique($widths[1]), $allowed)));
    }

    public function test_shell_supports_normal_and_wide_content_width(): void
    {
        $this->actingAs($this->founder);
        $this->withViewErrors([]);

        $normal = (string) $this->blade('<x-notify.app-shell>body</x-notify.app-shell>');
        $this->assertStringContainsString('class="notify-content notify-content--normal"', $normal);

        $wide = (string) $this->blade('<x-notify.app-shell width="wide">body</x-notify.app-shell>');
        $this->assertStringContainsString('class="notify-content notify-content--wide"', $wide);

        $unknown = (string) $this->blade('<x-notify.app-shell width="huge">body</x-notify.app-shell>');
        $this->assertStringContainsString('class="notify-content notify-content--normal"', $unknown);
    }

    public function test_shell_navigation_labels_have_locale_parity(): void
    {
        $ar = require lang_path('ar/notify.php');
        $en = require lang_path('en/notify.php');

        $this->assertSame(array_keys(\Illuminate\Support\Arr::dot($ar['shell_nav'])), array_keys(\Illuminate\Support\Arr::dot($en['shell_nav'])));
        $this->assertArrayNotHasKey('toggle_theme', $ar['shell']);
        $this->assertArrayNotHasKey('toggle_theme', $en['shell']);

        $html = $this->page($this->founder, route('dashboard'), 'en');
        $this->assertDoesNotMatchRegularExpression('/>\s*notify\.shell_nav\./', $html);
    }

    public function test_every_mapped_icon_is_registered_in_the_bundle(): void
    {
        $blade = file_get_contents(resource_path('views/components/notify/icon.blade.php'));
        $js = file_get_contents(resource_path('js/app.js'));
        preg_match_all("/'[a-z-]+' => '([a-z-]+)'/", str($blade)->between('$icons = [', '];')->toString(), $icons);

        foreach (array_unique($icons[1]) as $lucide) {
            $pascal = str_replace(' ', '', ucwords(str_replace('-', ' ', $lucide)));
            $this->assertMatchesRegularExpression('/^\s+'.$pascal.',$/m', $js, "Icon {$lucide} is mapped but not registered");
        }
    }

    public function test_navigation_builder_is_role_name_free(): void
    {
        $source = file_get_contents(app_path('Support/ShellNavigation.php'));
        foreach (['isStaff', 'isAdmin', 'isFounder', 'ROLE_'] as $roleCheck) {
            $this->assertStringNotContainsString($roleCheck, $source);
        }
        $shell = file_get_contents(resource_path('views/components/notify/app-shell.blade.php'));
        $this->assertStringNotContainsString('isStaff', $shell);
        $this->assertStringNotContainsString('isAdmin', $shell);

        $nav = new ShellNavigation($this->staff, 'dashboard');
        $this->assertSame([], $nav->groups);
    }

    // ----- helpers ----------------------------------------------------------

    private function page(User $user, string $url, string $locale = 'ar'): string
    {
        return $this->actingAs($user)->withSession(['locale' => $locale])->get($url)->assertOk()->getContent();
    }

    private function sidebar(string $html): string
    {
        return str($html)->between('<aside class="notify-sidebar"', '</aside>')->toString();
    }

    private function bottomBar(string $html): string
    {
        return str($html)->after('class="notify-mobile-nav__grid')->before('</nav>')->toString();
    }

    private function moreSheet(string $html): string
    {
        return str($html)->between('id="notify-mobile-more"', '</section>')->toString();
    }

    /** @return array<int, string> */
    private function destinations(string $html): array
    {
        preg_match_all('/data-nav-destination="([^"]+)"/', $html, $matches);

        return $matches[1];
    }

    /** @return array<int, string> */
    private function currentDestinations(string $html): array
    {
        preg_match_all('/<(?:a|button)\b[^>]*>/', $html, $tags);
        $current = [];
        foreach ($tags[0] as $tag) {
            if (str_contains($tag, 'aria-current="page"') && preg_match('/data-nav-destination="([^"]+)"/', $tag, $m)) {
                $current[] = $m[1];
            }
        }

        return $current;
    }
}
