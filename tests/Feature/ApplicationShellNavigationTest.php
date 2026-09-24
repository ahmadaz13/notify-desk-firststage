<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApplicationShellNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shell_requires_an_active_internal_user(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $inactive = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => false,
        ]);

        $this->actingAs($inactive)->get(route('dashboard'))->assertForbidden();
    }

    public function test_staff_sees_daily_navigation_without_owner_finance_destinations(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $response = $this->actingAs($staff)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'daily']))
            ->assertOk();

        // Staff sees ONLY Today, Clients, and mobile More
        $response
            ->assertSee('data-nav-destination="today"', false)
            ->assertSee('data-nav-destination="clients"', false)
            ->assertSee('data-nav-destination="more"', false)
            ->assertDontSee('data-nav-destination="finance"', false)
            ->assertDontSee('data-nav-destination="administration"', false)
            ->assertDontSee('data-nav-area="finance"', false)
            ->assertDontSee('data-nav-area="administration"', false)
            ->assertDontSee('data-mobile-nav-layer="finance"', false)
            ->assertDontSee('data-mobile-nav-layer="administration"', false)
            ->assertDontSee('data-nav-destination="financial-accounts"', false)
            ->assertDontSee('data-nav-destination="accounting"', false);

        $this->actingAs($staff)->get(route('finance.index'))->assertForbidden();
    }

    public function test_founder_sees_only_four_top_level_destinations(): void
    {
        // P9 (§3.1): primary destinations + Finance/Administration groups; no legacy links.
        $founder = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertOk();

        foreach (['today', 'clients', 'custom-projects', 'finance', 'finance-overview', 'admin-settings'] as $destination) {
            $response->assertSee('data-nav-destination="'.$destination.'"', false);
        }
        foreach (['subscription-management','products-pricing','collections','operating-expenses','capital-management','executive','saas-metrics','import','settings','financial-accounts','accounting','administration'] as $destination) {
            $response->assertDontSee('data-nav-destination="'.$destination.'"', false);
        }
    }

    public function test_daily_active_states_and_add_client_context_are_correct(): void
    {
        $founder = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $today = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'daily']))
            ->assertOk();
        $this->assertDestinationIsCurrent($today->getContent(), 'today');
        // P9 (§3.3): Add client is a page action (Today quick action / Clients header), never in the shell header.
        $today->assertSee('data-page-action="add-client"', false)
            ->assertDontSee('data-shell-action="add-client"', false);

        $work = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'work']))
            ->assertOk();
        // Today remains the active top-level area because Work is an internal Today mode
        $this->assertDestinationIsCurrent($work->getContent(), 'today');
        $work->assertDontSee('data-shell-action="add-client"', false);

        $clients = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('clients.index'))
            ->assertOk();
        $this->assertDestinationIsCurrent($clients->getContent(), 'clients');
        $clients->assertSee('data-page-action="add-client"', false);
    }

    public function test_notifications_are_header_only_and_more_is_an_accessible_sheet(): void
    {
        $founder = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $content = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-shell-action="notifications"', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('aria-controls="notify-mobile-more"', false)
            ->getContent();

        // One header control; on phone it is also listed in the More sheet (§3.4), never in the sidebar or bottom bar.
        $this->assertSame(1, substr_count($content, 'data-shell-action="notifications"'));
        $sidebar = str($content)->between('<aside class="notify-sidebar"', '</aside>')->toString();
        $bar = str($content)->between('class="notify-mobile-nav__grid', '</nav>')->toString();
        $this->assertStringNotContainsString('data-nav-destination="notifications"', $sidebar);
        $this->assertStringNotContainsString('data-nav-destination="notifications"', $bar);
    }

    public function test_internal_pages_keep_their_top_level_shell_context_active(): void
    {
        $founder = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $settings = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('settings.index'))
            ->assertOk();
        // P9: Settings is a child of the Administration group.
        $this->assertDestinationIsCurrent($settings->getContent(), 'admin-settings');
        $settings->assertSee('data-nav-group="administration"', false)
            ->assertDontSee('data-nav-area="administration"', false);

        $finance = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('finance.index'))
            ->assertOk();
        $this->assertDestinationIsCurrent($finance->getContent(), 'finance');
        $this->assertDestinationIsCurrent($finance->getContent(), 'finance-overview');
        $finance->assertDontSee('data-nav-area="finance"', false);
    }

    public function test_arabic_shell_uses_canonical_daily_labels(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->withSession(['locale' => 'ar'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('اليوم')
            ->assertSee('العملاء')
            ->assertSee('المزيد');
    }

    public function test_header_exposes_persistent_theme_language_and_profile_controls(): void
    {
        // §26 FROZEN D-04: no theme control or theme script; language and profile controls remain.
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'password' => Hash::make('old-password')]);
        $response = $this->actingAs($admin)->withSession(['locale' => 'en'])->get(route('dashboard'))->assertOk();
        $response->assertDontSee('notify_theme', false)
            ->assertDontSee('toggleTheme', false)
            ->assertDontSee('data-theme', false)
            ->assertDontSee('data-lucide="sun-moon"', false)
            ->assertSee('<meta name="color-scheme" content="light">', false)
            ->assertSee(route('locale.switch', 'ar'), false)
            ->assertSee(route('profile.edit'), false);

        $this->actingAs($admin)->put(route('profile.update'), ['name' => 'Updated Owner', 'email' => 'owner@example.test'])->assertRedirect();
        $this->actingAs($admin)->put(route('profile.password'), ['current_password' => 'old-password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])->assertRedirect();
        $this->assertTrue(Hash::check('new-password-123', $admin->fresh()->password));
    }

    private function assertDestinationIsCurrent(string $content, string $destination): void
    {
        $this->assertMatchesRegularExpression(
            '/data-nav-destination="'.preg_quote($destination, '/').'"[^>]*aria-current="page"/',
            $content,
        );
    }
}
