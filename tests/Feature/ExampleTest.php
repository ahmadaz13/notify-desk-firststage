<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            // P11: Today header is the operational page title + date; calm empty state.
            ->assertSee(__('notify.today_board.title'))
            ->assertSee(__('notify.today_board.mode_today'))
            ->assertSee(__('notify.today_board.scope_my'))
            ->assertSee(__('notify.today_board.empty_title'))
            ->assertDontSee('صافي نتيجة الشهر');
    }

    public function test_authenticated_user_can_view_client_detail_workspace(): void
    {
        $user = User::factory()->create();

        $clientId = DB::table('clients')->insertGetId([
            'business_name' => 'مطعم الأهرام',
            'phone' => '0798887766',
            'city_area' => 'عمان',
            'business_category' => 'مطاعم',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('clients.show', $clientId))
            ->assertOk()
            ->assertSee('مطعم الأهرام')
            // P10 (§19): card stack instead of the legacy tabbed sections.
            ->assertSee('data-client-workspace', false)
            ->assertSee('data-client-state', false)
            ->assertSee('data-client-details', false)
            ->assertSee('data-client-activity', false)
            ->assertSee(__('notify.client_hub.state.eyebrow'));
    }

    public function test_authenticated_user_can_view_import_and_notifications(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_FOUNDER]); // import is Founder-only (D-25)

        $this->actingAs($user)
            ->get(route('clients.import'))
            ->assertOk()
            ->assertSee(__('notify.import.title')); // P13: page title is "Import clients"

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('التنبيهات والإشعارات');
    }
}
