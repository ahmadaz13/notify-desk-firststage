<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiAttendeeAppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpenseCategorySeeder::class);
    }

    public function test_appointment_with_multiple_attendees(): void
    {
        $ahmad = User::factory()->create(['role' => 'founder', 'name' => 'Ahmad']);
        $khalid = User::factory()->create(['role' => 'founder', 'name' => 'Khalid']);

        $client = Client::create([
            'business_name' => 'شركة التقنية الحديثة',
            'contact_person' => 'سمير',
            'phone' => '0791112233',
            'city_area' => 'عمان - الجبيهة',
            'business_category' => 'برمجيات',
            'lead_source' => 'ميداني',
            'status' => 'prospect',
        ]);

        $response = $this->actingAs($ahmad)
            ->post(route('appointments.store'), [
                'client_id' => $client->id,
                'appointment_date' => Carbon::today()->addDay()->toDateString(),
                'appointment_time' => '11:00',
                'appointment_type' => 'physical_visit',
                'location' => 'مقر الشركة - الجبيهة',
                'notes' => 'اجتماع تعريفي مشترك بحضور أحمد وخالد',
                'attendees' => [$ahmad->id, $khalid->id],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $appointment = Appointment::where('client_id', $client->id)->first();
        $this->assertNotNull($appointment);
        $this->assertEquals('scheduled', $appointment->status);

        // Check pivot table appointment_user has both Ahmad and Khalid
        $this->assertDatabaseHas('appointment_user', [
            'appointment_id' => $appointment->id,
            'user_id' => $ahmad->id,
        ]);
        $this->assertDatabaseHas('appointment_user', [
            'appointment_id' => $appointment->id,
            'user_id' => $khalid->id,
        ]);

        $this->assertCount(2, $appointment->users);
        $attendeeNames = $appointment->users->pluck('name')->all();
        $this->assertContains('Ahmad', $attendeeNames);
        $this->assertContains('Khalid', $attendeeNames);
    }

    public function test_appointment_attendee_chips_display(): void
    {
        $ahmad = User::factory()->create(['role' => 'founder', 'name' => 'Ahmad']);
        $khalid = User::factory()->create(['role' => 'founder', 'name' => 'Khalid']);

        $client = Client::create([
            'business_name' => 'سوبرماركت المدينة',
            'contact_person' => 'مروان',
            'phone' => '0795554433',
            'city_area' => 'عمان - تلاع العلي',
            'business_category' => 'سوبرماركت',
            'lead_source' => 'ميداني',
            'status' => 'prospect',
        ]);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => Carbon::today()->toDateString(),
            'appointment_time' => '14:00',
            'appointment_type' => 'physical_visit',
            'location' => 'تلاع العلي',
            'status' => 'scheduled',
        ]);

        $appointment->users()->sync([$ahmad->id, $khalid->id]);

        // Today card lists every attendee on its responsible line (P11: names, no emoji chips).
        $dashboardResponse = $this->actingAs($ahmad)->get(route('dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Ahmad، Khalid');

        // Client profile appointment history displays attendee chips
        $clientResponse = $this->actingAs($ahmad)->get(route('clients.show', $client->id));
        $clientResponse->assertOk();
        $clientResponse->assertSee('Ahmad');
        $clientResponse->assertSee('Khalid');
    }

    public function test_call_and_whatsapp_links_present_on_client_page(): void
    {
        $admin = User::factory()->create(['role' => 'founder', 'name' => 'Ahmad']);

        $client = Client::create([
            'business_name' => 'مخبز الأمانة',
            'contact_person' => 'يوسف',
            'phone' => '0791234567',
            'city_area' => 'عمان - الصويفية',
            'business_category' => 'مخابز',
            'lead_source' => 'ميداني',
            'status' => 'prospect',
        ]);

        // Client Show View
        $showResponse = $this->actingAs($admin)->get(route('clients.show', $client->id));
        $showResponse->assertOk();
        $showResponse->assertSee('tel:0791234567');
        $showResponse->assertSee('https://wa.me/962791234567');

        // Client Index View — P10 (§5, §31): prospects segment, one quick action (Call) per row.
        $indexResponse = $this->actingAs($admin)->get(route('clients.index', ['view' => 'prospects']));
        $indexResponse->assertOk();
        $indexResponse->assertSee('tel:0791234567');
    }
}
