<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Services\DailyOperationalService;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase2FreeInstallationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_installation_appointment_can_be_scheduled_without_subscription_or_payment(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->makeClient();

        $this->actingAs($admin)
            ->post(route('clients.installations.schedule', $client->id), [
                'appointment_date' => now()->addDay()->toDateString(),
                'appointment_time' => '13:30',
                'attendees' => [$admin->id],
                'location' => 'فرع عبدون',
                'branch_name' => 'عبدون',
                'notes' => 'تركيب مجاني أولي',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'client_id' => $client->id,
            'appointment_type' => AppointmentTypes::INSTALLATION,
            'appointment_time' => '13:30',
            'branch_name' => 'عبدون',
            'status' => 'scheduled',
        ]);
        $this->assertSame(ClientLifecycle::INSTALLATION_SCHEDULED, $client->fresh()->stage);
        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $client->id,
            'type' => 'installation_scheduled',
        ]);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('installations', 0);
    }

    public function test_installation_appointment_can_be_rescheduled_with_real_date_time_and_timeline(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->makeClient(['stage' => ClientLifecycle::INSTALLATION_SCHEDULED]);
        $appointment = $this->makeAppointment($client);

        $this->actingAs($admin)
            ->patch(route('appointments.reschedule', $appointment->id), [
                'appointment_date' => now()->addDays(3)->toDateString(),
                'appointment_time' => '15:45',
                'location' => 'موقع جديد',
                'branch_name' => 'الصويفية',
            ])
            ->assertRedirect();

        $appointment->refresh();
        $this->assertSame(now()->addDays(3)->toDateString(), $appointment->appointment_date->toDateString());
        $this->assertSame('15:45', substr($appointment->appointment_time, 0, 5));
        $this->assertSame('scheduled', $appointment->status);
        $this->assertSame(ClientLifecycle::INSTALLATION_SCHEDULED, $client->fresh()->stage);
        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $client->id,
            'type' => 'appointment_rescheduled',
        ]);
    }

    public function test_completed_linked_installation_creates_history_items_follow_up_and_no_billing_records(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $installer = User::factory()->create(['role' => 'founder', 'name' => 'Installer']);
        $client = $this->makeClient(['stage' => ClientLifecycle::INSTALLATION_SCHEDULED]);
        $appointment = $this->makeAppointment($client);
        $service = Service::create([
            'key' => 'restaurant-pos',
            'name_ar' => 'نظام نقاط بيع',
            'name_en' => 'POS',
            'default_price' => 250,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('clients.installations.complete', $client->id), [
                'appointment_id' => $appointment->id,
                'installed_at' => now()->format('Y-m-d H:i:s'),
                'installed_by' => $installer->id,
                'branch_name' => 'الفرع الرئيسي',
                'service_ids' => [$service->id],
                'custom_item_names' => "طابعة حرارية\nإعداد حساب واتساب",
                'next_follow_up_date' => now()->addDays(2)->toDateString(),
                'next_action' => 'متابعة القرار بعد التجربة',
                'notes' => 'تم التركيب بنجاح',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('installations', 1);
        $installation = DB::table('installations')->first();
        $this->assertSame($client->id, (int) $installation->client_id);
        $this->assertSame($appointment->id, (int) $installation->appointment_id);
        $this->assertSame($installer->id, (int) $installation->installed_by);
        $this->assertSame('الفرع الرئيسي', $installation->branch_name);

        $this->assertDatabaseHas('installation_items', [
            'installation_id' => $installation->id,
            'service_id' => $service->id,
            'service_key' => 'restaurant-pos',
            'service_name_snapshot' => 'نظام نقاط بيع',
        ]);
        $this->assertDatabaseHas('installation_items', [
            'installation_id' => $installation->id,
            'service_name_snapshot' => 'طابعة حرارية',
        ]);

        $service->update(['name_ar' => 'نظام نقاط بيع معدل', 'default_price' => 999]);
        $this->assertDatabaseHas('installation_items', [
            'installation_id' => $installation->id,
            'service_name_snapshot' => 'نظام نقاط بيع',
        ]);
        $this->assertFalse(Schema::hasColumn('installation_items', 'price_contribution'));

        $this->assertSame(ClientLifecycle::INSTALLED_FREE, $client->fresh()->stage);
        $this->assertNotSame('subscriber', $client->fresh()->stage);
        $this->assertSame('prospect', $client->fresh()->status);
        $this->assertSame('completed', $appointment->fresh()->status);
        $this->assertDatabaseHas('follow_ups', [
            'client_id' => $client->id,
            'installation_id' => $installation->id,
            'next_action' => 'متابعة القرار بعد التجربة',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $client->id,
            'type' => 'free_installation_completed',
        ]);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_duplicate_completion_of_same_installation_appointment_is_prevented(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->makeClient();
        $appointment = $this->makeAppointment($client);

        $payload = [
            'appointment_id' => $appointment->id,
            'installed_at' => now()->format('Y-m-d H:i:s'),
            'installed_by' => $admin->id,
            'custom_item_names' => 'إعداد النظام',
            'next_follow_up_date' => now()->addDay()->toDateString(),
            'next_action' => 'متابعة',
        ];

        $this->actingAs($admin)->post(route('clients.installations.complete', $client->id), $payload)->assertRedirect();
        $this->actingAs($admin)
            ->from(route('clients.show', $client->id))
            ->post(route('clients.installations.complete', $client->id), $payload)
            ->assertRedirect(route('clients.show', $client->id))
            ->assertSessionHasErrors('appointment_id');

        $this->assertDatabaseCount('installations', 1);
    }

    public function test_completion_without_precreated_appointment_schedules_default_three_day_follow_up(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->makeClient();

        $this->actingAs($admin)
            ->post(route('clients.installations.complete', $client->id), [
                'installed_at' => now()->format('Y-m-d H:i:s'),
                'installed_by' => $admin->id,
                'custom_item_names' => 'إعداد شاشة الطلبات',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('installations', 1);
        $this->assertDatabaseCount('follow_ups', 1);
        $this->assertDatabaseHas('follow_ups', [
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'reason' => 'متابعة ما بعد التركيب المجاني',
        ]);
        $this->assertSame(ClientLifecycle::INSTALLED_FREE, $client->fresh()->stage);
    }

    public function test_cancelled_installation_appointment_creates_no_installation_and_requires_fallback_stage(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->makeClient(['stage' => ClientLifecycle::INSTALLATION_SCHEDULED]);
        $appointment = $this->makeAppointment($client);

        $this->actingAs($admin)
            ->from(route('clients.show', $client->id))
            ->patch(route('appointments.update', $appointment->id), ['status' => 'cancelled'])
            ->assertRedirect(route('clients.show', $client->id))
            ->assertSessionHasErrors('next_stage');

        $this->assertSame('scheduled', $appointment->fresh()->status);
        $this->assertDatabaseCount('installations', 0);

        $this->actingAs($admin)
            ->patch(route('appointments.update', $appointment->id), [
                'status' => 'cancelled',
                'next_stage' => ClientLifecycle::CONTACTING,
            ])
            ->assertRedirect();

        $this->assertSame('cancelled', $appointment->fresh()->status);
        $this->assertSame(ClientLifecycle::CONTACTING, $client->fresh()->stage);
        $this->assertDatabaseCount('installations', 0);
    }

    public function test_legacy_appointment_types_continue_to_work(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->makeClient();

        $this->actingAs($admin)
            ->post(route('appointments.store'), [
                'client_id' => $client->id,
                'appointment_date' => now()->addDay()->toDateString(),
                'appointment_time' => '12:00',
                'appointment_type' => 'meeting',
                'location' => 'عن بعد',
            ])
            ->assertRedirect();

        $appointment = Appointment::where('appointment_type', 'meeting')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('appointments.update', $appointment->id), ['status' => 'confirmed'])
            ->assertRedirect();

        $this->assertSame('confirmed', $appointment->fresh()->status);
    }

    public function test_daily_operations_exposes_installation_preparation_counts(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $scheduledClient = $this->makeClient();
        $installedClient = $this->makeClient([
            'business_name' => 'عميل مركب',
            'phone' => '0795550001',
            'stage' => ClientLifecycle::INSTALLED_FREE,
        ]);
        $decisionClient = $this->makeClient([
            'business_name' => 'عميل قرار',
            'phone' => '0795550002',
            'stage' => ClientLifecycle::DECISION_PENDING,
        ]);

        $this->makeAppointment($scheduledClient, ['appointment_date' => now()->toDateString()]);

        $service = app(DailyOperationalService::class);
        $snapshot = $service->getTodaySnapshot($admin);

        $this->assertSame(1, $service->getTodayInstallationAppointments($admin)->count());
        $this->assertSame(1, $service->getInstalledFreeClientsCount($admin));
        $this->assertSame(1, $service->getDecisionPendingClientsCount($admin));
        $this->assertSame(1, $snapshot['installation_appointments_today']);
        $this->assertSame(1, $snapshot['installed_free_clients']);
        $this->assertSame(1, $snapshot['decision_pending_clients']);

        $this->assertNotNull($installedClient);
        $this->assertNotNull($decisionClient);
    }

    private function makeClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'مطعم الاختبار',
            'phone' => '0793332211',
            'business_phone' => '064441122',
            'contact_person' => 'سامي',
            'city_area' => 'عمان - الدوار السابع',
            'city' => 'عمان',
            'area' => 'الدوار السابع',
            'business_category' => 'مطاعم',
            'business_type' => 'مطعم',
            'lead_source' => 'direct',
            'number_of_branches' => 1,
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ], $overrides));
    }

    private function makeAppointment(Client $client, array $overrides = []): Appointment
    {
        return Appointment::create(array_merge([
            'client_id' => $client->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '10:00',
            'appointment_type' => AppointmentTypes::INSTALLATION,
            'status' => 'scheduled',
            'location' => 'موقع العميل',
            'branch_name' => 'الرئيسي',
        ], $overrides));
    }
}
