<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase09ManagementSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_sees_daily_navigation_only_and_more_is_permission_filtered(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $response = $this->actingAs($staff)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'daily']))
            ->assertOk();

        // Daily navigation items are visible
        $response->assertSee('data-nav-destination="today"', false)
            ->assertSee('data-nav-destination="clients"', false)
            ->assertSee('data-nav-destination="work"', false)
            ->assertSee('data-nav-destination="more"', false);

        // Owner-level management groups are hidden
        $response->assertDontSee('data-nav-group="commercial"', false)
            ->assertDontSee('data-nav-group="money"', false)
            ->assertDontSee('data-nav-group="reports"', false)
            ->assertDontSee('data-nav-group="system"', false);

        // Advanced engine layer is hidden
        $response->assertDontSee('data-mobile-nav-layer="advanced"', false)
            ->assertDontSee('data-nav-destination="financial-accounts"', false)
            ->assertDontSee('data-nav-destination="accounting"', false);

        // Subscription management is hidden from staff
        $response->assertDontSee('data-nav-destination="subscription-management"', false);

        // Direct routes remain protected independently from UI
        $this->actingAs($staff)->get(route('subscription-billing.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('finance.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('accounting.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('financial-accounts.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('collections.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('settings.index'))->assertForbidden();
    }

    public function test_founder_sees_grouped_management_and_subordinate_advanced_layers(): void
    {
        $founder = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertOk();

        // Daily navigation remains simple
        $response->assertSee('data-nav-destination="today"', false)
            ->assertSee('data-nav-destination="clients"', false)
            ->assertSee('data-nav-destination="work"', false);

        // Management groups visible and separated
        $response->assertSee('data-nav-group="commercial"', false)
            ->assertSee('data-nav-destination="subscription-management"', false)
            ->assertSee('data-nav-destination="products-pricing"', false)
            ->assertSee('data-nav-destination="partners"', false);

        $response->assertSee('data-nav-group="money"', false)
            ->assertSee('data-nav-destination="collections"', false)
            ->assertSee('data-nav-destination="finance"', false)
            ->assertSee('data-nav-destination="operating-expenses"', false)
            ->assertSee('data-nav-destination="capital-management"', false);

        $response->assertSee('data-nav-group="reports"', false)
            ->assertSee('data-nav-destination="executive"', false)
            ->assertSee('data-nav-destination="saas-metrics"', false);

        $response->assertSee('data-nav-group="operations-admin"', false)
            ->assertSee('data-nav-destination="import"', false)
            ->assertSee('data-nav-destination="conflicts"', false);

        $response->assertSee('data-nav-group="system"', false)
            ->assertSee('data-nav-destination="settings"', false);

        // Advanced engine grouped separately
        $response->assertSee('data-nav-layer="advanced"', false)
            ->assertSee('data-nav-destination="financial-accounts"', false)
            ->assertSee('data-nav-destination="accounting"', false);
    }

    public function test_mobile_bottom_nav_has_exact_four_items_and_notifications_in_header_only(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $response = $this->actingAs($staff)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertOk();

        $html = $response->getContent();

        // Exact 4 items in bottom nav grid
        $this->assertSame(1, substr_count($html, 'class="notify-mobile-nav__grid"'));
        $mobileGrid = str($html)->between('class="notify-mobile-nav__grid"', '</nav>')->toString();
        $this->assertSame(1, substr_count($mobileGrid, 'data-nav-destination="today"'));
        $this->assertSame(1, substr_count($mobileGrid, 'data-nav-destination="clients"'));
        $this->assertSame(1, substr_count($mobileGrid, 'data-nav-destination="work"'));
        $this->assertSame(1, substr_count($mobileGrid, 'data-nav-destination="more"'));

        // Notifications is header only, never a bottom nav tab
        $this->assertSame(1, substr_count($html, 'data-shell-action="notifications"'));
        $this->assertSame(0, substr_count($html, 'data-nav-destination="notifications"'));
    }

    public function test_desktop_daily_nav_not_polluted_by_finance_or_admin(): void
    {
        $founder = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertOk();

        // Daily section contains only today, clients, work
        $html = $response->getContent();
        $start = strpos($html, '<section class="notify-nav__section notify-nav__section--daily"');
        $this->assertNotFalse($start);
        $end = strpos($html, '</section>', $start);
        $this->assertNotFalse($end);
        $dailySection = substr($html, $start, $end - $start + strlen('</section>'));

        $this->assertStringContainsString('data-nav-destination="today"', $dailySection);
        $this->assertStringContainsString('data-nav-destination="clients"', $dailySection);
        $this->assertStringContainsString('data-nav-destination="work"', $dailySection);
        $this->assertStringNotContainsString('data-nav-destination="finance"', $dailySection);
        $this->assertStringNotContainsString('data-nav-destination="accounting"', $dailySection);
        $this->assertStringNotContainsString('data-nav-destination="settings"', $dailySection);
        $this->assertStringNotContainsString('data-nav-destination="subscription-management"', $dailySection);
    }
}
