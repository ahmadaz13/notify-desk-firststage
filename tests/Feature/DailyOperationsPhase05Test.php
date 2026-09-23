<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientReviewItem;
use App\Models\Installation;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use App\Services\DailyOperationalService;
use App\Services\FollowUpService;
use App\Services\NotificationService;
use App\Services\OperationalQueueService;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use App\Support\FinancialPermissions;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyOperationsPhase05Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);
    }

    private function createTestClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'مطعم القدس الحديث',
            'phone' => '0791234567',
            'business_category' => 'restaurants',
            'city_area' => 'عمان - الجبيهة',
            'stage' => ClientLifecycle::PROSPECT,
            'status' => 'prospect',
            'lead_source' => 'direct',
            'primary_owner_id' => $this->staff->id,
        ], $overrides));
    }

    /* ----------------------------------------------------------------------
     | 1. Record Call outcome-first flow
     | ---------------------------------------------------------------------- */

    public function test_record_call_no_answer_requires_no_unnecessary_fields(): void
    {
        $client = $this->createTestClient();

        $response = $this->actingAs($this->staff)
            ->post(route('clients.contact-attempts.store', $client->id), [
                'result' => 'no_answer_busy',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contact_attempts', [
            'client_id' => $client->id,
            'result' => 'no_answer_busy',
            'method' => 'phone',
        ]);
        // Client stage transitions to contacting
        $this->assertEquals(ClientLifecycle::CONTACTING, $client->fresh()->stage);
    }

    public function test_record_call_interested_records_canonical_answered_outcome(): void
    {
        $client = $this->createTestClient();

        $response = $this->actingAs($this->staff)
            ->post(route('clients.contact-attempts.store', $client->id), [
                'result' => 'answered',
                'note' => 'العميل مهتم جداً ويرغب بجدولة موعد',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contact_attempts', [
            'client_id' => $client->id,
            'result' => 'answered',
            'note' => 'العميل مهتم جداً ويرغب بجدولة موعد',
        ]);
    }

    public function test_record_call_appointment_creates_appointment_with_conditional_inputs(): void
    {
        $client = $this->createTestClient();

        $response = $this->actingAs($this->staff)
            ->post(route('clients.contact-attempts.store', $client->id), [
                'result' => 'appointment',
                'appointment_date' => now()->addDays(2)->toDateString(),
                'appointment_time' => '14:30',
                'appointment_type' => 'sales_meeting',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('appointments', [
            'client_id' => $client->id,
            'appointment_time' => '14:30',
            'status' => 'scheduled',
        ]);
        $this->assertEquals(ClientLifecycle::APPOINTMENT, $client->fresh()->stage);
    }

    public function test_record_call_callback_later_creates_authoritative_follow_up(): void
    {
        $client = $this->createTestClient();
        $callbackTime = now()->addDay()->setTime(11, 0);

        $response = $this->actingAs($this->staff)
            ->post(route('clients.contact-attempts.store', $client->id), [
                'result' => 'callback_later',
                'follow_up_date_time' => $callbackTime->toDateTimeString(),
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('follow_ups', [
            'client_id' => $client->id,
            'reason' => 'معاودة اتصال',
            'completed_at' => null,
        ]);
    }

    public function test_record_call_not_interested_creates_review_and_does_not_close_client(): void
    {
        $client = $this->createTestClient();

        $response = $this->actingAs($this->staff)
            ->post(route('clients.contact-attempts.store', $client->id), [
                'result' => 'not_interested',
                'note' => 'العميل يرى أن التكلفة مرتفعة حالياً للمراجعة',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('client_review_items', [
            'client_id' => $client->id,
            'type' => ClientReviewItem::TYPE_NOT_INTERESTED,
            'status' => ClientReviewItem::STATUS_PENDING,
        ]);
        // Never auto-closed!
        $this->assertNotEquals(ClientLifecycle::CLOSED, $client->fresh()->stage);
        $this->assertNull($client->fresh()->closed_at);
    }

    public function test_record_call_wrong_invalid_creates_review_and_does_not_close_client(): void
    {
        $client = $this->createTestClient();

        $response = $this->actingAs($this->staff)
            ->post(route('clients.contact-attempts.store', $client->id), [
                'result' => 'wrong_invalid',
                'note' => 'الرقم مغلق دائماً والمحل تحت الصيانة',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('client_review_items', [
            'client_id' => $client->id,
            'type' => ClientReviewItem::TYPE_WRONG_INVALID,
            'status' => ClientReviewItem::STATUS_PENDING,
        ]);
        $this->assertNotEquals(ClientLifecycle::CLOSED, $client->fresh()->stage);
    }

    /* ----------------------------------------------------------------------
     | 2. Create Appointment & Compact Appointment Result Adapter (UX-D07)
     | ---------------------------------------------------------------------- */

    public function test_normal_appointment_created_with_three_fields_only(): void
    {
        $client = $this->createTestClient();

        $response = $this->actingAs($this->staff)
            ->post(route('appointments.store'), [
                'client_id' => $client->id,
                'appointment_date' => now()->addDay()->toDateString(),
                'appointment_time' => '10:00',
                'appointment_type' => 'physical_visit',
            ]);

        $response->assertRedirect();
        $appointment = Appointment::where('client_id', $client->id)->first();
        $this->assertNotNull($appointment);
        $this->assertTrue($appointment->users->contains($this->staff->id));
    }

    public function test_compact_appointment_outcome_no_show_preserves_history(): void
    {
        $client = $this->createTestClient(['stage' => ClientLifecycle::APPOINTMENT]);
        $appointment = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '11:00',
            'appointment_type' => 'sales_meeting',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->staff)
            ->post(route('appointments.compact-outcome.store', $appointment->id), [
                'first_decision' => 'no_show',
                'note' => 'العميل لم يحضر ولم يرد على الهاتف',
            ]);

        $response->assertRedirect(route('clients.show', $client->id));
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('meeting_outcomes', [
            'appointment_id' => $appointment->id,
            'attendance_status' => 'no_show',
        ]);
    }

    public function test_compact_appointment_outcome_reschedule_updates_date_and_preserves_history(): void
    {
        $client = $this->createTestClient(['stage' => ClientLifecycle::APPOINTMENT]);
        $appointment = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '11:00',
            'appointment_type' => 'sales_meeting',
            'status' => 'scheduled',
        ]);

        $newDate = now()->addDays(3)->toDateString();
        $response = $this->actingAs($this->staff)
            ->post(route('appointments.compact-outcome.store', $appointment->id), [
                'first_decision' => 'reschedule',
                'reschedule_date' => $newDate,
                'reschedule_time' => '15:00',
            ]);

        $response->assertRedirect(route('clients.show', $client->id));
        $fresh = $appointment->fresh();
        $this->assertEquals($newDate, $fresh->appointment_date->toDateString());
        $this->assertEquals('15:00', $fresh->appointment_time);
        $this->assertEquals('scheduled', $fresh->status);
    }

    public function test_compact_appointment_outcome_attended_installation_schedules_free_installation_without_subscription(): void
    {
        $client = $this->createTestClient(['stage' => ClientLifecycle::APPOINTMENT]);
        $appointment = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '11:00',
            'appointment_type' => 'sales_meeting',
            'status' => 'scheduled',
        ]);

        $installDate = now()->addDays(2)->toDateString();
        $response = $this->actingAs($this->staff)
            ->post(route('appointments.compact-outcome.store', $appointment->id), [
                'first_decision' => 'attended',
                'attended_choice' => 'installation',
                'installation_date' => $installDate,
                'installation_time' => '12:00',
            ]);

        $response->assertRedirect(route('clients.show', $client->id));
        $this->assertEquals(ClientLifecycle::INSTALLATION_SCHEDULED, $client->fresh()->stage);
        $installationAppt = Appointment::where('client_id', $client->id)
            ->where('appointment_type', AppointmentTypes::INSTALLATION)
            ->first();
        $this->assertNotNull($installationAppt);
        $this->assertEquals($installDate, $installationAppt->appointment_date->toDateString());
        $this->assertEquals('scheduled', $installationAppt->status);
        // Zero subscriptions created!
        $this->assertEquals(0, Subscription::where('client_id', $client->id)->count());
    }

    public function test_compact_appointment_outcome_attended_start_subscription_does_not_auto_create_subscription(): void
    {
        $client = $this->createTestClient(['stage' => ClientLifecycle::APPOINTMENT]);
        $appointment = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '11:00',
            'appointment_type' => 'sales_meeting',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->staff)
            ->post(route('appointments.compact-outcome.store', $appointment->id), [
                'first_decision' => 'attended',
                'attended_choice' => 'start_subscription',
            ]);

        $response->assertRedirect();
        $this->assertEquals(ClientLifecycle::DECISION_PENDING, $client->fresh()->stage);
        $this->assertEquals(0, Subscription::where('client_id', $client->id)->count());
    }

    public function test_compact_appointment_outcome_attended_not_interested_creates_review(): void
    {
        $client = $this->createTestClient(['stage' => ClientLifecycle::APPOINTMENT]);
        $appointment = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '11:00',
            'appointment_type' => 'sales_meeting',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->staff)
            ->post(route('appointments.compact-outcome.store', $appointment->id), [
                'first_decision' => 'attended',
                'attended_choice' => 'not_interested',
                'reason' => 'العميل اختار تأجيل المشروع بالكامل لأجل غير مسمى',
            ]);

        $response->assertRedirect(route('clients.show', $client->id));
        $this->assertDatabaseHas('client_review_items', [
            'client_id' => $client->id,
            'type' => ClientReviewItem::TYPE_NOT_INTERESTED,
            'status' => ClientReviewItem::STATUS_PENDING,
        ]);
        $this->assertNotEquals(ClientLifecycle::CLOSED, $client->fresh()->stage);
    }

    /* ----------------------------------------------------------------------
     | 3. Simplified Free Installation Completion (UX-D09)
     | ---------------------------------------------------------------------- */

    public function test_installation_completion_with_catalog_service_and_automatic_follow_up(): void
    {
        $client = $this->createTestClient(['stage' => ClientLifecycle::INSTALLATION_SCHEDULED]);
        $appointment = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '10:00',
            'appointment_type' => AppointmentTypes::INSTALLATION,
            'status' => 'scheduled',
        ]);

        $service = Service::create([
            'key' => 'whatsapp_bot_' . uniqid(),
            'name_ar' => 'خدمة بوت واتساب',
            'name_en' => 'WhatsApp Bot',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->staff)
            ->post(route('clients.installations.complete', $client->id), [
                'service_ids' => [$service->id],
                'notes' => 'تم التركيب بنجاح وشرح النظام',
            ]);

        $response->assertRedirect();
        $this->assertEquals(ClientLifecycle::INSTALLED_FREE, $client->fresh()->stage);

        // Appointment marked completed
        $this->assertEquals('completed', $appointment->fresh()->status);

        // Installation record created with current actor as installed_by
        $installation = Installation::where('client_id', $client->id)->first();
        $this->assertNotNull($installation);
        $this->assertEquals($this->staff->id, $installation->installed_by);

        // Automatic 3-day follow-up scheduled
        $this->assertDatabaseHas('follow_ups', [
            'client_id' => $client->id,
            'installation_id' => $installation->id,
            'reason' => 'متابعة ما بعد التركيب المجاني',
            'completed_at' => null,
        ]);

        // Zero subscriptions created!
        $this->assertEquals(0, Subscription::where('client_id', $client->id)->count());
    }

    public function test_installation_completion_ambiguous_appointments_requires_explicit_id(): void
    {
        $client = $this->createTestClient(['stage' => ClientLifecycle::INSTALLATION_SCHEDULED]);

        // Create TWO active installation appointments
        Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '10:00',
            'appointment_type' => AppointmentTypes::INSTALLATION,
            'status' => 'scheduled',
        ]);
        Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '14:00',
            'appointment_type' => AppointmentTypes::INSTALLATION,
            'status' => 'scheduled',
        ]);

        $service = Service::create([
            'key' => 'pos_connector_' . uniqid(),
            'name_ar' => 'موصل نقاط البيع',
            'name_en' => 'POS Connector',
            'is_active' => true,
        ]);

        // Submitting without appointment_id when ambiguous must fail validation
        $response = $this->actingAs($this->staff)
            ->post(route('clients.installations.complete', $client->id), [
                'service_ids' => [$service->id],
            ]);

        $response->assertSessionHasErrors('appointment_id');
    }

    /* ----------------------------------------------------------------------
     | 4. Follow-up Authoritative Completion & Orchestration (UX-D03, UX-D08)
     | ---------------------------------------------------------------------- */

    public function test_open_follow_up_has_completed_at_null_and_completes_with_canonical_outcome(): void
    {
        $client = $this->createTestClient(['stage' => ClientLifecycle::INSTALLED_FREE]);
        $followUpId = DB::table('follow_ups')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $this->staff->id,
            'method' => 'phone',
            'reason' => 'متابعة ما بعد التركيب',
            'next_action' => 'معاودة الاتصال',
            'next_follow_up_date' => now()->toDateString(),
            'follow_up_date_time' => now()->toDateTimeString(),
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Complete with wait / call later
        $response = $this->actingAs($this->staff)
            ->post(route('follow-ups.complete', $followUpId), [
                'outcome' => FollowUpService::OUTCOME_CALLBACK_LATER,
                'follow_up_date_time' => now()->addDays(2)->toDateTimeString(),
                'notes' => 'العميل في اجتماع، الاتصال بعد يومين',
            ]);

        $response->assertRedirect(route('clients.show', $client->id));

        // Original follow-up is completed
        $completedRow = DB::table('follow_ups')->where('id', $followUpId)->first();
        $this->assertNotNull($completedRow->completed_at);
        $this->assertEquals($this->staff->id, $completedRow->completed_by);
        $this->assertEquals(FollowUpService::OUTCOME_CALLBACK_LATER, $completedRow->completion_outcome);

        // Next follow-up is created transactionally
        $this->assertDatabaseHas('follow_ups', [
            'client_id' => $client->id,
            'reason' => 'متابعة لاحقة',
            'completed_at' => null,
        ]);
    }

    public function test_follow_up_outcome_appointment_creates_appointment_and_completes_follow_up(): void
    {
        $client = $this->createTestClient(['stage' => ClientLifecycle::CONTACTING]);
        $followUpId = DB::table('follow_ups')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $this->staff->id,
            'method' => 'phone',
            'reason' => 'متابعة رغبة العميل',
            'next_action' => 'الاتصال',
            'next_follow_up_date' => now()->toDateString(),
            'follow_up_date_time' => now()->toDateTimeString(),
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->staff)
            ->post(route('follow-ups.complete', $followUpId), [
                'outcome' => FollowUpService::OUTCOME_APPOINTMENT,
                'appointment_date' => now()->addDay()->toDateString(),
                'appointment_time' => '11:30',
                'appointment_type' => 'sales_meeting',
            ]);

        $response->assertRedirect(route('clients.show', $client->id));
        $this->assertEquals(ClientLifecycle::APPOINTMENT, $client->fresh()->stage);
        $this->assertNotNull(DB::table('follow_ups')->where('id', $followUpId)->value('completed_at'));
        $this->assertDatabaseHas('appointments', [
            'client_id' => $client->id,
            'appointment_time' => '11:30',
            'status' => 'scheduled',
        ]);
    }

    public function test_follow_up_outcome_subscribe_completes_follow_up_without_auto_creating_subscription(): void
    {
        $client = $this->createTestClient(['stage' => ClientLifecycle::INSTALLED_FREE]);
        $followUpId = DB::table('follow_ups')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $this->staff->id,
            'method' => 'phone',
            'reason' => 'قرار التجربة المجانية',
            'next_action' => 'متابعة القرار',
            'next_follow_up_date' => now()->toDateString(),
            'follow_up_date_time' => now()->toDateTimeString(),
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->staff)
            ->post(route('follow-ups.complete', $followUpId), [
                'outcome' => FollowUpService::OUTCOME_SUBSCRIBE,
            ]);

        $response->assertRedirect(route('clients.show', ['client' => $client->id, '#collapsible-management']));
        $this->assertEquals(ClientLifecycle::DECISION_PENDING, $client->fresh()->stage);
        $this->assertNotNull(DB::table('follow_ups')->where('id', $followUpId)->value('completed_at'));
        $this->assertEquals(0, Subscription::where('client_id', $client->id)->count());
    }

    public function test_follow_up_outcome_no_answer_requires_callback_datetime(): void
    {
        $client = $this->createTestClient();
        $followUpId = DB::table('follow_ups')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $this->staff->id,
            'method' => 'phone',
            'reason' => 'متابعة روتينية',
            'next_action' => 'اتصال',
            'next_follow_up_date' => now()->toDateString(),
            'follow_up_date_time' => now()->toDateTimeString(),
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempting no_answer without next callback time must fail
        $response = $this->actingAs($this->staff)
            ->post(route('follow-ups.complete', $followUpId), [
                'outcome' => FollowUpService::OUTCOME_NO_ANSWER,
            ]);

        $response->assertSessionHasErrors('follow_up_date_time');
        // Must remain open!
        $this->assertNull(DB::table('follow_ups')->where('id', $followUpId)->value('completed_at'));
    }

    /* ----------------------------------------------------------------------
     | 5. Transactional Integrity & Queue Exclusion Tests (Correction 8)
     | ---------------------------------------------------------------------- */

    public function test_completed_follow_up_is_excluded_from_operational_queues_and_daily_snapshot(): void
    {
        $client = $this->createTestClient();
        $followUpId = DB::table('follow_ups')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $this->staff->id,
            'method' => 'phone',
            'reason' => 'متابعة قائمة',
            'next_action' => 'اتصال بالعميل',
            'next_follow_up_date' => Carbon::today()->toDateString(),
            'follow_up_date_time' => Carbon::now()->toDateTimeString(),
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $queueService = app(OperationalQueueService::class);
        $dailyService = app(DailyOperationalService::class);

        // Before completion: present in pending follow ups
        $pendingBefore = $dailyService->getPendingFollowUps($this->staff);
        $this->assertTrue($pendingBefore->contains('id', $followUpId));

        // Mark completed
        DB::table('follow_ups')->where('id', $followUpId)->update([
            'completed_at' => now(),
            'completed_by' => $this->staff->id,
            'completion_outcome' => FollowUpService::OUTCOME_SUBSCRIBE,
        ]);

        // After completion: strictly EXCLUDED from open pending follow ups
        $pendingAfter = $dailyService->getPendingFollowUps($this->staff);
        $this->assertFalse($pendingAfter->contains('id', $followUpId));

        // And next action in queueService does not treat it as open callback
        $nextAction = $queueService->nextActionFor($client);
        $this->assertNotEquals('Callback at date/time', $nextAction['label'] ?? null);
    }

    public function test_failure_creating_dependent_work_rolls_back_follow_up_completion(): void
    {
        $client = $this->createTestClient();
        $followUpId = DB::table('follow_ups')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $this->staff->id,
            'method' => 'phone',
            'reason' => 'متابعة لاختبار التراجع عند الخطأ',
            'next_action' => 'اتصال',
            'next_follow_up_date' => now()->toDateString(),
            'follow_up_date_time' => now()->toDateTimeString(),
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt appointment outcome with missing appointment_time
        try {
            app(FollowUpService::class)->completeFollowUp($followUpId, $this->staff, FollowUpService::OUTCOME_APPOINTMENT, [
                'appointment_date' => now()->addDay()->toDateString(),
                'appointment_time' => '', // Triggers exception!
            ]);
            $this->fail('Expected ValidationException was not thrown');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Expected
        }

        // Must still be open!
        $followUp = DB::table('follow_ups')->where('id', $followUpId)->first();
        $this->assertNull($followUp->completed_at);
        $this->assertNull($followUp->completion_outcome);
    }

    /* ----------------------------------------------------------------------
     | 6. Close Client & Reopen Client Guided Actions
     | ---------------------------------------------------------------------- */

    public function test_close_client_with_valid_reason_code_preserves_history(): void
    {
        $client = $this->createTestClient();

        $response = $this->actingAs($this->staff)
            ->post(route('clients.close', $client->id), [
                'closed_reason_code' => 'price',
                'closed_reason' => 'السعر أعلى من الميزانية المحددة للعميل',
            ]);

        $response->assertRedirect();
        $this->assertEquals(ClientLifecycle::CLOSED, $client->fresh()->stage);
        $this->assertEquals('archived', $client->fresh()->status);
        $this->assertNotNull($client->fresh()->closed_at);
        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $client->id,
            'type' => 'client_closed',
        ]);
    }

    public function test_close_client_with_reason_other_requires_note(): void
    {
        $client = $this->createTestClient();

        $response = $this->actingAs($this->staff)
            ->post(route('clients.close', $client->id), [
                'closed_reason_code' => 'other',
                'closed_reason' => '', // Required for 'other'!
            ]);

        $response->assertSessionHasErrors('closed_reason');
        $this->assertNotEquals(ClientLifecycle::CLOSED, $client->fresh()->stage);
    }

    public function test_reopen_client_requires_reason_and_resets_to_prospect(): void
    {
        $client = $this->createTestClient([
            'stage' => ClientLifecycle::CLOSED,
            'status' => 'archived',
            'closed_at' => now(),
            'closed_reason' => 'price',
        ]);

        $response = $this->actingAs($this->staff)
            ->post(route('clients.reopen', $client->id), [
                'reason' => 'العميل تواصل مجدداً بعد توفر ميزانية جديدة لديه',
            ]);

        $response->assertRedirect();
        $this->assertEquals(ClientLifecycle::PROSPECT, $client->fresh()->stage);
        $this->assertEquals('prospect', $client->fresh()->status);
        $this->assertNull($client->fresh()->closed_at);
        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $client->id,
            'type' => 'client_reopened',
        ]);
    }

    /* ----------------------------------------------------------------------
     | 7. Permissions Integrity
     | ---------------------------------------------------------------------- */

    public function test_guest_is_blocked_from_all_operational_write_routes(): void
    {
        $client = $this->createTestClient();

        $this->post(route('clients.contact-attempts.store', $client->id))->assertRedirect(route('login'));
        $this->post(route('appointments.store'))->assertRedirect(route('login'));
        $this->post(route('clients.installations.complete', $client->id))->assertRedirect(route('login'));
        $this->post(route('clients.close', $client->id))->assertRedirect(route('login'));
        $this->post(route('clients.reopen', $client->id))->assertRedirect(route('login'));
    }

    public function test_inactive_user_is_blocked(): void
    {
        $inactive = User::factory()->create([
            'is_active' => false,
            'role' => 'staff',
        ]);
        $client = $this->createTestClient();

        $this->actingAs($inactive)
            ->post(route('clients.contact-attempts.store', $client->id), [
                'result' => 'no_answer_busy',
            ])
            ->assertForbidden();
    }

    public function test_staff_does_not_gain_start_subscription_or_payment_permission(): void
    {
        $this->assertFalse(FinancialPermissions::allows($this->staff, FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING));
        $this->assertFalse(FinancialPermissions::allows($this->staff, FinancialPermissions::RECORD_PAYMENT));

        $client = $this->createTestClient();

        // Direct write route to start subscription is forbidden for staff
        $this->actingAs($this->staff)
            ->post(route('clients.guided-subscription.store', $client->id), [])
            ->assertForbidden();

        // Direct payment route is forbidden for staff
        $this->actingAs($this->staff)
            ->post(route('clients.collections.payments.store', $client->id), [])
            ->assertForbidden();
    }
}
