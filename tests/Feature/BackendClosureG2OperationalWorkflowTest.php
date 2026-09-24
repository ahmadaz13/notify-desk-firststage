<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientReviewItem;
use App\Models\User;
use App\Services\OperationalQueueService;
use App\Support\ClientLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackendClosureG2OperationalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_client_creation_starts_as_prospect_with_no_financial_records(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);

        $this->actingAs($admin)->post(route('clients.store'), [
            'business_name' => 'G2 Prospect',
            'phone' => '0791112233',
            'primary_phone_type' => 'business',
            'city_area' => 'Amman',
            'business_category' => 'Retail',
            'lead_source' => 'Direct',
        ])->assertRedirect();

        $client = Client::where('business_name', 'G2 Prospect')->firstOrFail();
        $this->assertSame(ClientLifecycle::PROSPECT, $client->stage);
        $this->assertSame('prospect', $client->status);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_schedules', 0);
    }

    public function test_contact_appointment_outcome_creates_real_appointment_and_stage(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->client();

        $this->actingAs($admin)->post(route('clients.contact-attempts.store', $client), [
            'method' => 'phone',
            'result' => 'appointment',
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '11:30',
            'appointment_type' => 'physical_visit',
        ])->assertRedirect();

        $this->assertSame(ClientLifecycle::APPOINTMENT, $client->fresh()->stage);
        $this->assertDatabaseHas('appointments', [
            'client_id' => $client->id,
            'appointment_time' => '11:30',
            'status' => 'scheduled',
        ]);
        $this->assertDatabaseHas('activity_logs', ['client_id' => $client->id, 'type' => 'appointment_scheduled']);
    }

    public function test_callback_later_requires_time_and_excludes_client_from_immediate_queue_until_due(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->client(['stage' => ClientLifecycle::CONTACTING]);

        $this->actingAs($admin)
            ->from(route('clients.show', $client))
            ->post(route('clients.contact-attempts.store', $client), [
                'method' => 'phone',
                'result' => 'callback_later',
            ])
            ->assertRedirect(route('clients.show', $client))
            ->assertSessionHasErrors('follow_up_date_time');

        $callbackAt = now()->addDay()->setTime(14, 0);
        $this->actingAs($admin)->post(route('clients.contact-attempts.store', $client), [
            'method' => 'phone',
            'result' => 'callback_later',
            'follow_up_date_time' => $callbackAt->toDateTimeString(),
            'next_action' => 'Call back',
        ])->assertRedirect();

        $queues = app(OperationalQueueService::class)->queues($admin, now());
        $this->assertFalse($queues['active_contact_queue']->contains('id', $client->id));

        $dueQueues = app(OperationalQueueService::class)->queues($admin, $callbackAt->copy()->endOfDay());
        $this->assertTrue($dueQueues['callbacks_due']->contains('client_id', $client->id));
    }

    public function test_no_answer_busy_moves_client_later_in_active_contact_queue(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $first = $this->client(['business_name' => 'First queue client', 'stage' => ClientLifecycle::CONTACTING]);
        $later = $this->client(['business_name' => 'Later queue client', 'phone' => '0799990001', 'stage' => ClientLifecycle::CONTACTING]);

        $this->actingAs($admin)->post(route('clients.contact-attempts.store', $later), [
            'method' => 'phone',
            'result' => 'no_answer_busy',
        ])->assertRedirect();

        $queueIds = app(OperationalQueueService::class)->queues($admin)['active_contact_queue']->pluck('id')->values()->all();
        $this->assertSame($first->id, $queueIds[0]);
        $this->assertSame($later->id, end($queueIds));
    }

    public function test_wrong_invalid_and_not_interested_create_pending_review_items_without_closure(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $wrong = $this->client(['phone' => '0791000001']);
        $notInterested = $this->client(['phone' => '0791000002']);

        $this->actingAs($admin)->post(route('clients.contact-attempts.store', $wrong), [
            'method' => 'phone',
            'result' => 'wrong_invalid',
            'note' => 'Invalid phone',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('clients.contact-attempts.store', $notInterested), [
            'method' => 'phone',
            'result' => 'not_interested',
            'note' => 'Not interested now',
        ])->assertRedirect();

        $this->assertDatabaseHas('client_review_items', [
            'client_id' => $wrong->id,
            'type' => ClientReviewItem::TYPE_WRONG_INVALID,
            'status' => ClientReviewItem::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('client_review_items', [
            'client_id' => $notInterested->id,
            'type' => ClientReviewItem::TYPE_NOT_INTERESTED,
            'status' => ClientReviewItem::STATUS_PENDING,
        ]);
        $this->assertNotSame(ClientLifecycle::CLOSED, $wrong->fresh()->stage);
        $this->assertNotSame(ClientLifecycle::CLOSED, $notInterested->fresh()->stage);
    }

    public function test_reviewed_decline_can_close_and_reopen_preserves_history(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->client(['stage' => ClientLifecycle::DECISION_PENDING]);

        $this->actingAs($admin)->patch(route('clients.stage.update', $client), [
            'stage' => ClientLifecycle::CLOSED,
            'closed_reason_code' => 'not_interested',
            'closed_reason' => 'Declined after trial',
        ])->assertRedirect();

        $this->assertSame(ClientLifecycle::CLOSED, $client->fresh()->stage);
        $this->assertDatabaseHas('activity_logs', ['client_id' => $client->id, 'type' => 'client_closed']);

        $this->actingAs($admin)->post(route('clients.reopen', $client), [
            'stage' => ClientLifecycle::CONTACTING,
            'reason' => 'Customer returned later',
        ])->assertRedirect();

        $this->assertSame(ClientLifecycle::CONTACTING, $client->fresh()->stage);
        $this->assertDatabaseHas('activity_logs', ['client_id' => $client->id, 'type' => 'client_reopened']);
    }

    public function test_meeting_completion_can_schedule_installation_without_subscription(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->client(['stage' => ClientLifecycle::APPOINTMENT]);
        $appointment = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '12:00',
            'appointment_type' => 'physical_visit',
            'status' => 'scheduled',
        ]);

        $this->actingAs($admin)->post(route('appointments.outcome.store', $appointment), [
            'attendance_status' => 'attended',
            'meeting_type' => 'physical_visit',
            'interest_level' => 'high',
            'next_action' => 'Schedule installation',
            'outcome_result' => 'installation_scheduled',
            'installation_appointment_date' => now()->addDay()->toDateString(),
            'installation_appointment_time' => '15:00',
        ])->assertRedirect();

        $this->assertSame(ClientLifecycle::INSTALLATION_SCHEDULED, $client->fresh()->stage);
        $this->assertDatabaseHas('appointments', [
            'client_id' => $client->id,
            'appointment_type' => 'installation',
            'appointment_time' => '15:00',
        ]);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_next_action_projection_prioritizes_review_followup_decision_and_active_call(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $reviewClient = $this->client(['phone' => '0792000001']);
        $followUpClient = $this->client(['phone' => '0792000002']);
        $decisionClient = $this->client(['phone' => '0792000003', 'stage' => ClientLifecycle::DECISION_PENDING]);
        $callClient = $this->client(['phone' => '0792000004', 'stage' => ClientLifecycle::CONTACTING]);

        ClientReviewItem::create([
            'client_id' => $reviewClient->id,
            'type' => ClientReviewItem::TYPE_WRONG_INVALID,
            'status' => ClientReviewItem::STATUS_PENDING,
            'created_by' => $admin->id,
        ]);
        DB::table('follow_ups')->insert([
            'client_id' => $followUpClient->id,
            'user_id' => $admin->id,
            'method' => 'phone',
            'reason' => 'Callback',
            'next_action' => 'Callback',
            'next_follow_up_date' => now()->toDateString(),
            'follow_up_date_time' => now()->toDateTimeString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $queue = app(OperationalQueueService::class);
        $this->assertSame('Needs review', $queue->nextActionFor($reviewClient)['label']);
        $this->assertSame('Callback at date/time', $queue->nextActionFor($followUpClient)['label']);
        $this->assertSame('Waiting for decision', $queue->nextActionFor($decisionClient)['label']);
        $this->assertSame('Call client', $queue->nextActionFor($callClient)['label']);
    }

    private function client(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'G2 Client',
            'phone' => '0791234567',
            'business_phone' => '0791234567',
            'city_area' => 'Amman',
            'city' => 'Amman',
            'business_category' => 'Retail',
            'business_type' => 'Retail',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ], $overrides));
    }
}
