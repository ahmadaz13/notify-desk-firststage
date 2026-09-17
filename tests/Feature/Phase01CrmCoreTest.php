<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientReviewItem;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ClientLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase01CrmCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_client_defaults_to_prospect_and_keeps_business_fields_separate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('clients.store'), [
            'business_name' => 'صيدلية النور',
            'phone' => '0791112233',
            'business_phone' => '064455667',
            'city_area' => 'عمان - تلاع العلي',
            'city' => 'عمان',
            'area' => 'تلاع العلي',
            'business_category' => 'صيدليات',
            'business_type' => 'صيدلية',
            'lead_source' => 'referral',
            'source_reference' => 'عميل سابق',
            'number_of_branches' => 2,
            'contact_person' => 'ليان',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'business_name' => 'صيدلية النور',
            'phone' => '0791112233',
            'business_phone' => '064455667',
            'business_type' => 'صيدلية',
            'city' => 'عمان',
            'area' => 'تلاع العلي',
            'source_reference' => 'عميل سابق',
            'number_of_branches' => 2,
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ]);

        $client = Client::where('business_name', 'صيدلية النور')->firstOrFail();
        $this->assertDatabaseHas('client_contacts', [
            'client_id' => $client->id,
            'name' => 'ليان',
            'is_primary' => true,
        ]);
    }

    public function test_all_canonical_lifecycle_stages_are_accepted_and_logged(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->makeClient();

        $manualStages = array_diff(ClientLifecycle::STAGES, [ClientLifecycle::SUBSCRIBER]);
        foreach ($manualStages as $stage) {
            $this->actingAs($admin)
                ->patch(route('clients.stage.update', $client->id), [
                    'stage' => $stage,
                    'closed_reason' => $stage === ClientLifecycle::CLOSED ? 'انتهت المتابعة' : null,
                ])
                ->assertRedirect();

            $this->assertSame($stage, $client->fresh()->stage);
        }

        $this->actingAs($admin)
            ->from(route('clients.show', $client->id))
            ->patch(route('clients.stage.update', $client->id), ['stage' => ClientLifecycle::SUBSCRIBER])
            ->assertRedirect(route('clients.show', $client->id))
            ->assertSessionHasErrors('stage');

        $this->assertSame(
            count($manualStages) - 2,
            DB::table('activity_logs')
                ->where('client_id', $client->id)
                ->where('type', 'client_stage_changed')
                ->count()
        );
        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $client->id,
            'type' => 'client_stage_changed',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $client->id,
            'type' => 'client_closed',
        ]);
    }

    public function test_legacy_archived_status_maps_to_closed_and_remains_accessible(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->makeClient([
            'status' => 'archived',
            'stage' => ClientLifecycle::CLOSED,
            'closed_at' => now(),
        ]);

        $this->assertSame(ClientLifecycle::CLOSED, ClientLifecycle::normalizeStage(null, 'archived'));

        $this->actingAs($admin)
            ->get(route('clients.show', $client->id))
            ->assertOk()
            ->assertSee('مغلق');

        $this->actingAs($admin)
            ->get(route('clients.index', ['status' => 'archived']))
            ->assertOk()
            ->assertSee($client->business_name);
    }

    public function test_client_can_have_multiple_contacts_with_one_primary_contact(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->makeClient();

        $this->actingAs($admin)
            ->post(route('clients.contacts.store', $client->id), [
                'name' => 'رامي',
                'role' => 'مالك',
                'primary_phone' => '0790000001',
                'is_primary' => true,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('clients.contacts.store', $client->id), [
                'name' => 'سارة',
                'role' => 'محاسبة',
                'primary_phone' => '0790000002',
                'is_primary' => true,
            ])
            ->assertRedirect();

        $this->assertSame(2, $client->contacts()->count());
        $this->assertSame(1, $client->contacts()->where('is_primary', true)->count());
        $this->assertDatabaseHas('client_contacts', [
            'client_id' => $client->id,
            'name' => 'سارة',
            'is_primary' => true,
        ]);
    }

    public function test_contact_outcome_preserves_stage_and_can_create_follow_up(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->makeClient(['stage' => ClientLifecycle::CONTACTING]);

        $this->actingAs($admin)
            ->post(route('clients.contact-attempts.store', $client->id), [
                'method' => 'phone',
                'result' => 'call_later',
                'note' => 'طلب التواصل غداً',
                'next_action' => 'إعادة الاتصال',
                'next_follow_up_date' => now()->addDay()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(ClientLifecycle::CONTACTING, $client->fresh()->stage);
        $this->assertDatabaseHas('contact_attempts', [
            'client_id' => $client->id,
            'result' => 'callback_later',
            'next_action' => 'إعادة الاتصال',
        ]);
        $this->assertDatabaseHas('follow_ups', [
            'client_id' => $client->id,
            'next_action' => 'إعادة الاتصال',
        ]);
    }

    public function test_not_interested_creates_review_without_deleting_or_closing_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->makeClient();

        Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '10:00',
            'appointment_type' => 'phone_call',
            'status' => 'scheduled',
        ]);

        $subscription = Subscription::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => 100,
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        Payment::create([
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'amount' => 100,
            'payment_method' => 'cash',
            'paid_at' => now(),
            'recorded_by' => $admin->id,
        ]);

        DB::table('activity_logs')->insert([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'type' => 'seeded_history',
            'description' => 'سجل سابق',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('clients.contact-attempts.store', $client->id), [
                'method' => 'phone',
                'result' => 'not_interested',
                'note' => 'لا يرغب حالياً',
                'close_client' => '1',
                'closed_reason' => 'غير مهتم حالياً',
            ])
            ->assertRedirect();

        $client->refresh();
        $this->assertSame(ClientLifecycle::CONTACTING, $client->stage);
        $this->assertSame('prospect', $client->status);
        $this->assertNotNull(Client::find($client->id));
        $this->assertDatabaseHas('appointments', ['client_id' => $client->id]);
        $this->assertDatabaseHas('subscriptions', ['client_id' => $client->id]);
        $this->assertDatabaseHas('payments', ['client_id' => $client->id]);
        $this->assertDatabaseHas('activity_logs', ['client_id' => $client->id, 'type' => 'seeded_history']);
        $this->assertDatabaseHas('client_review_items', [
            'client_id' => $client->id,
            'type' => ClientReviewItem::TYPE_NOT_INTERESTED,
            'status' => ClientReviewItem::STATUS_PENDING,
        ]);
        $this->assertDatabaseMissing('activity_logs', ['client_id' => $client->id, 'type' => 'client_closed']);
    }

    private function makeClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'مخبز الندى',
            'phone' => '0792223344',
            'business_phone' => '064440000',
            'contact_person' => 'نادر',
            'city_area' => 'عمان - خلدا',
            'city' => 'عمان',
            'area' => 'خلدا',
            'business_category' => 'مخابز',
            'business_type' => 'مخبز',
            'lead_source' => 'direct',
            'number_of_branches' => 1,
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ], $overrides));
    }
}
