<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\DailyNote;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase2FinalShellTodayBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function createClient(array $attributes = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'Test Business',
            'business_category' => 'Restaurant',
            'phone' => '079' . rand(1000000, 9999999),
            'city_area' => 'Amman',
            'lead_source' => 'Direct',
            'stage' => 'prospect',
            'status' => 'prospect',
        ], $attributes));
    }

    protected function createAppointment(array $attributes = [], ?User $user = null): Appointment
    {
        $appointment = Appointment::create(array_merge([
            'appointment_type' => 'in_person',
            'status' => 'scheduled',
            'appointment_date' => Carbon::today()->toDateString(),
            'appointment_time' => '10:00:00',
        ], $attributes));

        if ($user) {
            $appointment->users()->attach($user->id);
        }

        return $appointment;
    }

    public function test_role_aware_desktop_navigation_shows_4_areas_for_admin_and_founder(): void
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
            ->assertSee('data-nav-destination="administration"', false)
            ->assertSee('data-nav-area="finance"', false)
            ->assertSee('data-nav-area="administration"', false);

        // Subordinate engine isolation
        $response->assertDontSee('data-nav-destination="financial-accounts"', false)
            ->assertDontSee('data-nav-destination="accounting"', false);
    }

    public function test_staff_desktop_navigation_strictly_shows_only_today_and_clients(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $response = $this->actingAs($staff)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'daily']))
            ->assertOk();

        // Staff sees only Today, Clients, and mobile More
        $response->assertSee('data-nav-destination="today"', false)
            ->assertSee('data-nav-destination="clients"', false)
            ->assertSee('data-nav-destination="more"', false);

        // Finance and Administration are completely hidden
        $response->assertDontSee('data-nav-destination="finance"', false)
            ->assertDontSee('data-nav-destination="administration"', false)
            ->assertDontSee('data-nav-area="finance"', false)
            ->assertDontSee('data-nav-area="administration"', false)
            ->assertDontSee('data-mobile-nav-layer="finance"', false)
            ->assertDontSee('data-mobile-nav-layer="administration"', false);

        // Work is NOT in the top-level desktop sidebar
        $html = $response->getContent();
        $sidebarHtml = str($html)->between('<aside class="notify-sidebar"', '</aside>')->toString();
        $this->assertSame(0, substr_count($sidebarHtml, 'data-nav-destination="work"'));
        $this->assertSame(1, substr_count($sidebarHtml, 'data-nav-destination="today"'));
        $this->assertSame(1, substr_count($sidebarHtml, 'data-nav-destination="clients"'));
    }

    public function test_collapsible_desktop_sidebar_has_toggle_and_local_storage_contract(): void
    {
        $founder = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertOk();

        $response->assertSee('notify_sidebar_collapsed', false)
            ->assertSee('toggleSidebar()', false)
            ->assertSee('class="notify-sidebar__collapse-toggle"', false)
            ->assertSee(':class="{ \'notify-shell--collapsed\': sidebarCollapsed }"', false);
    }

    public function test_mobile_bottom_nav_contract_per_role(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        // Staff has 3 items
        $staffResponse = $this->actingAs($staff)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertOk();

        $staffHtml = $staffResponse->getContent();
        $this->assertSame(1, substr_count($staffHtml, 'notify-mobile-nav__grid--3'));
        $this->assertSame(0, substr_count($staffHtml, 'notify-mobile-nav__grid--4'));

        $staffGrid = str($staffHtml)->between('class="notify-mobile-nav__grid notify-mobile-nav__grid--3"', '</nav>')->toString();
        $this->assertSame(1, substr_count($staffGrid, 'data-nav-destination="today"'));
        $this->assertSame(1, substr_count($staffGrid, 'data-nav-destination="clients"'));
        $this->assertSame(1, substr_count($staffGrid, 'data-nav-destination="more"'));
        $this->assertSame(0, substr_count($staffGrid, 'data-nav-destination="finance"'));
        $this->assertSame(0, substr_count($staffGrid, 'data-nav-destination="work"'));

        // Admin has 4 items
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $adminResponse = $this->actingAs($admin)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertOk();

        $adminHtml = $adminResponse->getContent();
        $this->assertSame(1, substr_count($adminHtml, 'notify-mobile-nav__grid--4'));
        $this->assertSame(0, substr_count($adminHtml, 'notify-mobile-nav__grid--3'));

        $adminGrid = str($adminHtml)->between('class="notify-mobile-nav__grid notify-mobile-nav__grid--4"', '</nav>')->toString();
        $this->assertSame(1, substr_count($adminGrid, 'data-nav-destination="today"'));
        $this->assertSame(1, substr_count($adminGrid, 'data-nav-destination="clients"'));
        $this->assertSame(1, substr_count($adminGrid, 'data-nav-destination="finance"'));
        $this->assertSame(1, substr_count($adminGrid, 'data-nav-destination="more"'));
        $this->assertSame(0, substr_count($adminGrid, 'data-nav-destination="work"'));
    }

    public function test_work_is_an_internal_today_mode_not_top_level_navigation(): void
    {
        $founder = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($founder)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'work']))
            ->assertOk();

        // Mode tabs present
        $response->assertSee('data-today-tab="today"', false)
            ->assertSee('data-today-tab="work"', false)
            ->assertSee('class="notify-work-board"', false)
            ->assertSee('class="notify-work-filters"', false);

        // Desktop sidebar keeps Today as active top-level area
        $html = $response->getContent();
        $sidebarHtml = str($html)->between('<aside class="notify-sidebar"', '</aside>')->toString();
        $this->assertMatchesRegularExpression(
            '/class="[^"]*notify-nav-item[^"]*is-active[^"]*"[^>]*data-nav-destination="today"/',
            $sidebarHtml,
        );
    }

    public function test_today_board_operational_sections_and_card_structure(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $client = $this->createClient([
            'business_name' => 'Al-Masa Resto',
            'city_area' => 'Sweifieh',
            'assigned_user_id' => $user->id,
        ]);

        $now = Carbon::now('Asia/Amman');

        // 1 Overdue appointment
        $this->createAppointment([
            'client_id' => $client->id,
            'appointment_date' => $now->copy()->subDay()->toDateString(),
            'appointment_time' => '10:00:00',
        ], $user);

        // 1 Next appointment (within next hour)
        $this->createAppointment([
            'client_id' => $client->id,
            'appointment_date' => $now->toDateString(),
            'appointment_time' => $now->copy()->addMinutes(30)->format('H:i:s'),
        ], $user);

        // 1 Later Today appointment (+4 hours)
        $this->createAppointment([
            'client_id' => $client->id,
            'appointment_date' => $now->toDateString(),
            'appointment_time' => $now->copy()->addHours(4)->format('H:i:s'),
        ], $user);

        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'daily']))
            ->assertOk();

        // Sections
        $response->assertSee('data-today-section="overdue"', false)
            ->assertSee('data-today-section="next"', false)
            ->assertSee('data-today-section="later_today"', false);

        // Card contents
        $response->assertSee('Al-Masa Resto')
            ->assertSee('Sweifieh')
            ->assertSee($user->name)
            ->assertSee('data-responsible-staff', false)
            ->assertSee('data-card-primary-action', false);
    }

    public function test_all_work_mode_sections(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $client = $this->createClient([
            'business_name' => 'Amman Supermarket',
            'city_area' => 'Abdali',
            'assigned_user_id' => $user->id,
        ]);

        $now = Carbon::now('Asia/Amman');

        // Overdue
        $this->createAppointment([
            'client_id' => $client->id,
            'appointment_date' => $now->copy()->subDay()->toDateString(),
            'appointment_time' => '10:00:00',
        ], $user);

        // Today
        $this->createAppointment([
            'client_id' => $client->id,
            'appointment_date' => $now->toDateString(),
            'appointment_time' => '12:00:00',
        ], $user);

        // Upcoming (+3 days)
        $this->createAppointment([
            'client_id' => $client->id,
            'appointment_date' => $now->copy()->addDays(3)->toDateString(),
            'appointment_time' => '15:00:00',
        ], $user);

        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'work']))
            ->assertOk();

        // All Work Sections
        $response->assertSee('work-group-overdue', false)
            ->assertSee('work-group-today', false)
            ->assertSee('work-group-upcoming', false);
    }

    public function test_operational_team_board_filtering_all_work_vs_my_work(): void
    {
        $user1 = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'name' => 'Ahmad Staff',
            'is_active' => true,
        ]);

        $user2 = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'name' => 'Samer Staff',
            'is_active' => true,
        ]);

        $client1 = $this->createClient([
            'business_name' => 'Ahmad Client',
            'assigned_user_id' => $user1->id,
        ]);

        $client2 = $this->createClient([
            'business_name' => 'Samer Client',
            'assigned_user_id' => $user2->id,
        ]);

        $now = Carbon::now('Asia/Amman');

        $this->createAppointment([
            'client_id' => $client1->id,
            'appointment_date' => $now->toDateString(),
            'appointment_time' => $now->copy()->addMinutes(30)->format('H:i:s'),
        ], $user1);

        $this->createAppointment([
            'client_id' => $client2->id,
            'appointment_date' => $now->toDateString(),
            'appointment_time' => $now->copy()->addMinutes(45)->format('H:i:s'),
        ], $user2);

        // scope=all shows both clients
        $allResponse = $this->actingAs($user1)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'daily', 'scope' => 'all']))
            ->assertOk();

        $allResponse->assertSee('Ahmad Client')
            ->assertSee('Samer Client')
            ->assertSee('data-team-scope="all"', false)
            ->assertSee('data-team-scope="my"', false);

        // scope=my shows only Ahmad Client
        $myResponse = $this->actingAs($user1)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'daily', 'scope' => 'my']))
            ->assertOk();

        $myResponse->assertSee('Ahmad Client')
            ->assertDontSee('Samer Client');
    }

    public function test_daily_notes_embedded_editor_contract(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $businessNow = Carbon::now('Asia/Amman');

        DailyNote::create([
            'user_id' => $user->id,
            'date' => $businessNow->toDateString(),
            'content' => 'Reviewing morning installation tickets.',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'daily']))
            ->assertOk();

        $response->assertSee('data-daily-notes', false)
            ->assertSee('data-notes-editor', false)
            ->assertSee('data-notes-state="saving"', false)
            ->assertSee('data-notes-state="saved"', false)
            ->assertSee('data-notes-state="error"', false)
            ->assertSee('Reviewing morning installation tickets.')
            ->assertSee('@input.debounce.500ms', false)
            ->assertSee('notify_daily_notes_' . $user->id . '_' . $businessNow->toDateString(), false);
    }

    public function test_today_execution_path_has_zero_legacy_float_queries(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        DB::enableQueryLog();

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard', ['mode' => 'daily']))
            ->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Ensure zero queries sum or scan the legacy float tables in daily mode
        $suspiciousQueries = collect($queries)->filter(function ($q) {
            $sql = strtolower($q['query']);
            return (str_contains($sql, 'sum(amount)') && (str_contains($sql, 'payments') || str_contains($sql, 'expenses')))
                || str_contains($sql, 'payment_schedules');
        });

        $this->assertCount(0, $suspiciousQueries, 'Today execution path must not execute unindexed legacy float sums.');
    }
}
