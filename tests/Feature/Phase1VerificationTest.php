<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase1VerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_admin_can_login_with_exact_email(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@notify.local',
            'password' => Hash::make('secret12345'),
            'role' => 'admin',
        ]);

        $this->post(route('login.store'), [
            'email' => 'admin@notify.local',
            'password' => 'secret12345',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_internal_staff_can_login_with_case_insensitive_email(): void
    {
        $staff = User::factory()->create([
            'email' => 'staff@notify.local',
            'password' => Hash::make('secret12345'),
            'role' => 'staff',
        ]);

        $this->post(route('login.store'), [
            'email' => '  STAFF@NOTIFY.LOCAL  ',
            'password' => 'secret12345',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($staff);
    }

    public function test_canonical_mode_server_rendering(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $responseDaily = $this->actingAs($admin)->get(route('dashboard'));
        $responseDaily->assertOk();
        $responseDaily->assertViewHas('currentMode', 'daily');

        $responseWork = $this->actingAs($admin)->get(route('dashboard', ['mode' => 'work']));
        $responseWork->assertOk();
        $responseWork->assertViewHas('currentMode', 'work');

        $responseInvalid = $this->actingAs($admin)->get(route('dashboard', ['mode' => 'hacked_mode']));
        $responseInvalid->assertOk();
        $responseInvalid->assertViewHas('currentMode', 'daily');
    }

    public function test_retired_financial_mode_falls_back_to_today(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('dashboard', ['mode' => 'financial']));
        $response->assertOk();

        $response->assertViewHas('currentMode', 'daily');
        $response->assertDontSee('Legacy financial widgets');
        $response->assertDontSee('financial-cards-grid');
    }

    public function test_shell_add_client_action_has_touch_target_and_accessibility(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('dashboard'));
        $response->assertOk();

        $content = $response->getContent();

        $this->assertSame(1, substr_count($content, 'data-shell-action="add-client"'));
        $response->assertSee('notify-button--primary', false);
        $response->assertSee('href="'.route('clients.create').'"', false);
        $response->assertSee('aria-label="إضافة عميل"', false);
        $response->assertSee('data-lucide="plus"', false);
        $response->assertDontSee('quick-expense-fab-btn', false);

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.notify-button', $css);
        $this->assertStringContainsString('min-height: 48px', $css);
        $this->assertStringContainsString('.notify-mobile-nav', $css);
    }
}
