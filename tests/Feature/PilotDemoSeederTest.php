<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ConflictResolutionRequest;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\PilotDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PilotDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_demo_seeder_creates_expected_records(): void
    {
        $this->seed(PilotDemoSeeder::class);

        // 2 Admins
        $this->assertDatabaseHas('users', ['email' => 'ahmad@example.com', 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['email' => 'khalid@example.com', 'role' => 'admin']);

        // 3 Partners with different profit shares
        $this->assertEquals(3, Partner::count());
        $this->assertDatabaseHas('partners', ['profit_share_percentage' => 20.00]);
        $this->assertDatabaseHas('partners', ['profit_share_percentage' => 25.00]);
        $this->assertDatabaseHas('partners', ['profit_share_percentage' => 30.00]);

        // 1 Partner User with known email
        $partnerUser = User::where('email', 'partner@demo.com')->first();
        $this->assertNotNull($partnerUser);
        $this->assertEquals('partner', $partnerUser->role);
        $this->assertNotNull($partnerUser->partner_id);

        // 15 Clients
        $this->assertEquals(15, Client::count());
        $this->assertEquals(5, Client::whereNull('partner_id')->count());
        $this->assertEquals(10, Client::whereNotNull('partner_id')->count());

        // 5 Active Subscriptions
        $this->assertEquals(5, DB::table('subscriptions')->where('status', 'active')->count());

        // 20 Payments
        $this->assertEquals(20, DB::table('payments')->count());

        // 10 Expenses across categories
        $this->assertEquals(10, DB::table('expenses')->count());

        // 5 Appointments (some with both attendees)
        $this->assertEquals(5, Appointment::count());
        $multiAttendeeApts = Appointment::has('users', '>=', 2)->count();
        $this->assertGreaterThanOrEqual(1, $multiAttendeeApts);

        // 3 Pending Follow-ups
        $this->assertEquals(3, DB::table('follow_ups')->count());

        // 2 Conflict Resolution Requests
        $this->assertEquals(2, ConflictResolutionRequest::where('status', 'pending')->count());
    }

    public function test_pilot_demo_seeder_can_be_run_multiple_times_safely(): void
    {
        // First run
        $this->seed(PilotDemoSeeder::class);

        // Second run (Idempotency check)
        $this->seed(PilotDemoSeeder::class);

        // Assert no duplicate records were created
        $this->assertEquals(3, Partner::count());
        $this->assertEquals(15, Client::count());
        $this->assertEquals(5, DB::table('subscriptions')->count());
        $this->assertEquals(20, DB::table('payments')->count());
        $this->assertEquals(10, DB::table('expenses')->count());
        $this->assertEquals(5, Appointment::count());
        $this->assertEquals(3, DB::table('follow_ups')->count());
        $this->assertEquals(2, ConflictResolutionRequest::count());
    }
}
