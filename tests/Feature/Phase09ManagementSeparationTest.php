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

        // Staff sees only Today, Clients, and More
        $response->assertSee('data-nav-destination="today"', false)
            ->assertSee('data-nav-destination="clients"', false)
            ->assertSee('data-nav-destination="more"', false);

        // Finance and Administration are hidden from staff
        $response->assertDontSee('data-nav-destination="finance"', false)
            ->assertDontSee('data-nav-destination="administration"', false)
            ->assertDontSee('data-nav-area="finance"', false)
            ->assertDontSee('data-nav-area="administration"', false);

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

    public function test_founder_sees_four_top_level_areas_without_nested_sidebar_layers(): void
    {
        $founder = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertOk();

        // 4 Clean Areas
        $response->assertSee('data-nav-destination="today"', false)
            ->assertSee('data-nav-destination="clients"', false)
            ->assertSee('data-nav-destination="finance"', false)
            ->assertSee('data-nav-destination="administration"', false);

        foreach (['collections','operating-expenses','capital-management','subscription-management','executive','saas-metrics','products-pricing','import','settings','financial-accounts','accounting'] as $destination) {
            $response->assertDontSee('data-nav-destination="'.$destination.'"', false);
        }
    }

    public function test_mobile_bottom_nav_has_exact_items_per_role_and_notifications_in_header_only(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        // Staff mobile navigation: Exactly 3 items (Today, Clients, More)
        $staffResponse = $this->actingAs($staff)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertOk();

        $staffHtml = $staffResponse->getContent();
        $this->assertSame(1, substr_count($staffHtml, 'class="notify-mobile-nav__grid notify-mobile-nav__grid--3"'));
        $staffMobileGrid = str($staffHtml)->between('class="notify-mobile-nav__grid notify-mobile-nav__grid--3"', '</nav>')->toString();
        $this->assertSame(1, substr_count($staffMobileGrid, 'data-nav-destination="today"'));
        $this->assertSame(1, substr_count($staffMobileGrid, 'data-nav-destination="clients"'));
        $this->assertSame(1, substr_count($staffMobileGrid, 'data-nav-destination="more"'));
        $this->assertSame(0, substr_count($staffMobileGrid, 'data-nav-destination="finance"'));
        $this->assertSame(0, substr_count($staffMobileGrid, 'data-nav-destination="work"'));

        // Admin mobile navigation: Exactly 4 items (Today, Clients, Finance, More)
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $adminResponse = $this->actingAs($admin)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertOk();

        $adminHtml = $adminResponse->getContent();
        $this->assertSame(1, substr_count($adminHtml, 'class="notify-mobile-nav__grid notify-mobile-nav__grid--4"'));
        $adminMobileGrid = str($adminHtml)->between('class="notify-mobile-nav__grid notify-mobile-nav__grid--4"', '</nav>')->toString();
        $this->assertSame(1, substr_count($adminMobileGrid, 'data-nav-destination="today"'));
        $this->assertSame(1, substr_count($adminMobileGrid, 'data-nav-destination="clients"'));
        $this->assertSame(1, substr_count($adminMobileGrid, 'data-nav-destination="finance"'));
        $this->assertSame(1, substr_count($adminMobileGrid, 'data-nav-destination="more"'));
        $this->assertSame(0, substr_count($adminMobileGrid, 'data-nav-destination="work"'));

        // Notifications is header only, never a bottom nav tab
        $this->assertSame(1, substr_count($adminHtml, 'data-shell-action="notifications"'));
        $this->assertSame(0, substr_count($adminHtml, 'data-nav-destination="notifications"'));
    }

    public function test_desktop_daily_nav_not_polluted_by_finance_or_admin(): void
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
        $start = strpos($html, '<nav class="notify-nav notify-nav--primary"');
        $this->assertNotFalse($start);
        $end = strpos($html, '</nav>', $start);
        $this->assertNotFalse($end);
        $dailySection = substr($html, $start, $end - $start + strlen('</nav>'));

        // Staff desktop shows ONLY Today and Clients
        $this->assertStringContainsString('data-nav-destination="today"', $dailySection);
        $this->assertStringContainsString('data-nav-destination="clients"', $dailySection);
        $this->assertStringNotContainsString('data-nav-destination="finance"', $dailySection);
        $this->assertStringNotContainsString('data-nav-destination="administration"', $dailySection);
        $this->assertStringNotContainsString('data-nav-destination="accounting"', $dailySection);
        $this->assertStringNotContainsString('data-nav-destination="settings"', $dailySection);
        $this->assertStringNotContainsString('data-nav-destination="subscription-management"', $dailySection);
    }
}
