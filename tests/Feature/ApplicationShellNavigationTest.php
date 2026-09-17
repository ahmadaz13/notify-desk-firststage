<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response
            ->assertSee('data-nav-destination="today"', false)
            ->assertSee('data-nav-destination="clients"', false)
            ->assertSee('data-nav-destination="work"', false)
            ->assertSee('data-nav-destination="more"', false)
            ->assertSee('data-nav-group="operations-admin"', false)
            ->assertSee('data-nav-destination="import"', false)
            ->assertDontSee('data-nav-group="commercial"', false)
            ->assertDontSee('data-nav-group="money"', false)
            ->assertDontSee('data-nav-group="reports"', false)
            ->assertDontSee('data-nav-group="system"', false)
            ->assertDontSee('data-mobile-nav-layer="advanced"', false)
            ->assertDontSee('data-nav-destination="financial-accounts"', false)
            ->assertDontSee('data-nav-destination="accounting"', false);

        $this->actingAs($staff)->get(route('finance.index'))->assertForbidden();
    }

    public function test_founder_sees_grouped_management_and_advanced_destinations(): void
    {
        $founder = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertOk();

        foreach (['commercial', 'money', 'reports', 'operations-admin', 'system'] as $group) {
            $response->assertSee('data-nav-group="'.$group.'"', false);
        }

        foreach ([
            'subscription-management',
            'products-pricing',
            'partners',
            'collections',
            'finance',
            'operating-expenses',
            'capital-management',
            'executive',
            'saas-metrics',
            'import',
            'conflicts',
            'settings',
            'financial-accounts',
            'accounting',
        ] as $destination) {
            $response->assertSee('data-nav-destination="'.$destination.'"', false);
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
        $today->assertSee('data-shell-action="add-client"', false);

        $work = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'work']))
            ->assertOk();
        $this->assertDestinationIsCurrent($work->getContent(), 'work');
        $work->assertDontSee('data-shell-action="add-client"', false);

        $clients = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('clients.index'))
            ->assertOk();
        $this->assertDestinationIsCurrent($clients->getContent(), 'clients');
        $clients->assertSee('data-shell-action="add-client"', false);
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
            ->assertDontSee('data-nav-destination="notifications"', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('aria-controls="notify-mobile-more"', false)
            ->getContent();

        $this->assertSame(1, substr_count($content, 'data-shell-action="notifications"'));
    }

    public function test_nested_management_and_advanced_destinations_keep_their_shell_context_active(): void
    {
        $founder = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $settings = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('settings.index'))
            ->assertOk();
        $this->assertDestinationIsCurrent($settings->getContent(), 'settings');
        $this->assertMatchesRegularExpression(
            '/class="notify-nav-group is-active" data-nav-group="system"[^>]*\sopen\s/',
            $settings->getContent(),
        );

        $accounting = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('accounting.index'))
            ->assertOk();
        $this->assertDestinationIsCurrent($accounting->getContent(), 'accounting');
        $accounting->assertSee('class="notify-mobile-nav__item is-active"', false);
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
            ->assertSee('العمل')
            ->assertSee('المزيد');
    }

    private function assertDestinationIsCurrent(string $content, string $destination): void
    {
        $this->assertMatchesRegularExpression(
            '/data-nav-destination="'.preg_quote($destination, '/').'"[^>]*aria-current="page"/',
            $content,
        );
    }
}
