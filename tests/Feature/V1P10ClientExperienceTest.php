<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientSystemCredential;
use App\Models\ContactAttempt;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\PaymentReceiptConfirmation;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ClientCredentialService;
use App\Services\ClientOperationalWorkflowService;
use App\Services\PaymentReceiptService;
use App\Services\SubscriptionBillingService;
use App\Support\ClientLifecycle;
use App\Support\ClientSegments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * P10 — Client experience (§4, §5, §19). Segments come from clients.stage only; the workspace derives
 * one deterministic next step and exposes only the actions the P2 matrix allows.
 */
class V1P10ClientExperienceTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'P10-Plaintext-Secret-42';

    private User $founder;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->founder = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true]);
    }

    // ----- Segmentation -------------------------------------------------------

    public function test_default_segment_is_subscribers_for_owners_and_prospects_for_staff(): void
    {
        $this->client('Pipeline Cafe', ClientLifecycle::CONTACTING);
        $this->client('Paying Bakery', ClientLifecycle::SUBSCRIBER);

        foreach ([$this->founder, $this->admin] as $owner) {
            $this->actingAs($owner)->get(route('clients.index'))
                ->assertOk()
                ->assertViewHas('list', fn ($list) => $list->segment === ClientSegments::SUBSCRIBERS)
                ->assertSee('Paying Bakery')
                ->assertDontSee('Pipeline Cafe');
        }

        $this->actingAs($this->staff)->get(route('clients.index'))
            ->assertOk()
            ->assertViewHas('list', fn ($list) => $list->segment === ClientSegments::PROSPECTS)
            ->assertSee('Pipeline Cafe')
            ->assertDontSee('Paying Bakery');
    }

    public function test_explicit_segment_is_remembered_for_the_session(): void
    {
        $this->client('Ended Salon', ClientLifecycle::FORMER_SUBSCRIBER);

        $this->actingAs($this->admin)->get(route('clients.index', ['view' => 'renewal']))->assertSee('Ended Salon');
        $this->actingAs($this->admin)->get(route('clients.index'))
            ->assertViewHas('list', fn ($list) => $list->segment === ClientSegments::RENEWAL)
            ->assertSee('Ended Salon');

        // Unknown values fall back to the remembered/default segment.
        $this->actingAs($this->admin)->get(route('clients.index', ['view' => 'everything']))
            ->assertViewHas('list', fn ($list) => $list->segment === ClientSegments::RENEWAL);
    }

    public function test_segments_follow_the_lifecycle_stage_only(): void
    {
        $byStage = collect(ClientLifecycle::STAGES)->mapWithKeys(fn (string $stage) => [$stage => $this->client('Stage '.$stage, $stage)]);
        // Legacy status is compatibility data only: a "subscriber" status on a prospect stage is a Prospect.
        $legacy = $this->client('Legacy Status Shop', ClientLifecycle::PROSPECT, ['status' => 'subscriber']);

        $expect = [
            'prospects' => array_merge(ClientSegments::PROSPECT_STAGES, []),
            'subscribers' => [ClientLifecycle::SUBSCRIBER],
            'renewal' => [ClientLifecycle::FORMER_SUBSCRIBER],
            'closed' => [ClientLifecycle::CLOSED],
            'all' => ClientLifecycle::STAGES,
        ];

        foreach ($expect as $segment => $stages) {
            $ids = $this->actingAs($this->admin)->get(route('clients.index', ['view' => $segment]))
                ->assertOk()
                ->viewData('list')->clients->getCollection()->pluck('id')->all();

            foreach ($byStage as $stage => $client) {
                $this->assertSame(in_array($stage, $stages, true), in_array($client->id, $ids, true), "{$stage} in {$segment}");
            }
            $this->assertSame(in_array($segment, ['prospects', 'all'], true), in_array($legacy->id, $ids, true), "legacy status in {$segment}");
        }

        $counts = collect($this->actingAs($this->admin)->get(route('clients.index', ['view' => 'all']))->viewData('list')->segments)->pluck('count', 'key');
        $this->assertSame(7, $counts['prospects']);
        $this->assertSame(1, $counts['subscribers']);
        $this->assertSame(1, $counts['renewal']);
        $this->assertSame(1, $counts['closed']);
        $this->assertSame(10, $counts['all']);
    }

    public function test_prospect_stage_chip_filter_and_category_area_filters(): void
    {
        $this->client('Contacting One', ClientLifecycle::CONTACTING, ['business_category' => 'Cafe', 'city_area' => 'Abdoun']);
        $this->client('Decision Two', ClientLifecycle::DECISION_PENDING, ['business_category' => 'Clinic', 'city_area' => 'Marka']);

        $this->actingAs($this->staff)->get(route('clients.index', ['view' => 'prospects', 'stage' => ClientLifecycle::DECISION_PENDING]))
            ->assertSee('Decision Two')->assertDontSee('Contacting One');
        $this->actingAs($this->staff)->get(route('clients.index', ['view' => 'prospects', 'category' => 'Cafe']))
            ->assertSee('Contacting One')->assertDontSee('Decision Two');
        $this->actingAs($this->staff)->get(route('clients.index', ['view' => 'prospects', 'area' => 'Marka']))
            ->assertSee('Decision Two')->assertDontSee('Contacting One');
    }

    // ----- List ---------------------------------------------------------------

    public function test_search_matches_business_phone_digits_and_contact_details(): void
    {
        $target = $this->client('Blue Harbour Grill', ClientLifecycle::CONTACTING, ['phone' => '079 812 3456']);
        $target->contacts()->create(['name' => 'Rania Haddad', 'primary_phone' => '0777000111', 'is_primary' => false]);
        $this->client('Other Place', ClientLifecycle::CONTACTING, ['phone' => '0790000999']);

        foreach (['Harbour', '079 812', '0798123456', 'Rania', '0777000111'] as $term) {
            $this->actingAs($this->staff)->get(route('clients.index', ['view' => 'all', 'q' => $term]))
                ->assertOk()
                ->assertSee('Blue Harbour Grill')
                ->assertDontSee('Other Place');
        }

        $this->actingAs($this->staff)->get(route('clients.index', ['view' => 'all', 'q' => 'nothing-matches']))
            ->assertSee(__('notify.client_hub.empty.search_title'));
    }

    public function test_rows_show_one_segment_specific_signal_and_quick_action(): void
    {
        $prospect = $this->client('Visit Cafe', ClientLifecycle::APPOINTMENT);
        Appointment::create(['client_id' => $prospect->id, 'appointment_date' => now()->addDay()->toDateString(), 'appointment_time' => '11:00', 'appointment_type' => 'physical_visit', 'status' => 'scheduled']);

        $subscriber = $this->client('Due Bistro', ClientLifecycle::SUBSCRIBER);
        Invoice::create(['client_id' => $subscriber->id, 'invoice_number' => 'INV-P10-1', 'status' => Invoice::STATUS_ISSUED, 'currency' => 'JOD', 'total_minor' => 75000, 'issue_date' => now()->subDays(20), 'due_date' => now()->subDays(5)]);

        $former = $this->client('Former Studio', ClientLifecycle::FORMER_SUBSCRIBER);

        $rows = collect($this->actingAs($this->staff)->get(route('clients.index', ['view' => 'all']))->viewData('list')->rows)->keyBy('id');

        $this->assertSame(__('notify.client_hub.signals.appointment_on'), $rows[$prospect->id]['signal']['text']);
        $this->assertStringStartsWith('tel:', $rows[$prospect->id]['action']['href']);

        $this->assertSame('danger', $rows[$subscriber->id]['signal']['tone']);
        $this->assertSame(75000, $rows[$subscriber->id]['signal']['money_minor']);
        $this->assertSame(__('notify.client_hub.actions.payment_received'), $rows[$subscriber->id]['action']['label']);
        $this->assertStringEndsWith('?open=record-payment', $rows[$subscriber->id]['action']['href']);

        $this->assertSame(__('notify.client_hub.actions.renew'), $rows[$former->id]['action']['label']);
        $this->assertStringEndsWith('?open=start-subscription', $rows[$former->id]['action']['href']);

        $ownerRows = collect($this->actingAs($this->admin)->get(route('clients.index', ['view' => 'all']))->viewData('list')->rows)->keyBy('id');
        $this->assertSame(__('notify.client_hub.actions.record_payment'), $ownerRows[$subscriber->id]['action']['label']);
    }

    public function test_list_signals_are_batched_not_queried_per_row(): void
    {
        $this->client('Seed', ClientLifecycle::SUBSCRIBER);
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin)->get(route('clients.index', ['view' => 'all']))->assertOk();

            return count(DB::getQueryLog());
        };

        $few = $count();
        foreach (range(1, 12) as $i) {
            $client = $this->client('Batch '.$i, $i % 2 ? ClientLifecycle::SUBSCRIBER : ClientLifecycle::CONTACTING);
            Appointment::create(['client_id' => $client->id, 'appointment_date' => now()->addDay()->toDateString(), 'appointment_time' => '10:00', 'appointment_type' => 'physical_visit', 'status' => 'scheduled']);
        }
        $many = $count();

        $this->assertLessThanOrEqual($few + 2, $many, 'Row signals must not add queries per client.');
    }

    public function test_staff_list_has_no_owner_finance_destinations(): void
    {
        $subscriber = $this->client('Staff View Bistro', ClientLifecycle::SUBSCRIBER);
        Invoice::create(['client_id' => $subscriber->id, 'invoice_number' => 'INV-P10-2', 'status' => Invoice::STATUS_ISSUED, 'currency' => 'JOD', 'total_minor' => 5000, 'issue_date' => now(), 'due_date' => now()->addDays(5)]);

        $html = $this->actingAs($this->staff)->get(route('clients.index', ['view' => 'subscribers']))->assertOk()->getContent();
        $main = str($html)->betweenFirst('<main', '</main>')->toString();

        $this->assertStringNotContainsString(route('finance.collections'), $main);
        $this->assertStringNotContainsString(route('finance.index'), $main);
        $this->assertStringContainsString('Staff View Bistro', $main);
    }

    // ----- Workspace: next step ----------------------------------------------

    public function test_prospect_next_step_follows_workflow_state(): void
    {
        $client = $this->client('Journey Cafe', ClientLifecycle::PROSPECT);
        $this->assertState($client, 'first_contact', 'record-call');

        ContactAttempt::create(['client_id' => $client->id, 'user_id' => $this->staff->id, 'method' => 'phone', 'result' => 'no_answer_busy']);
        $client->update(['stage' => ClientLifecycle::CONTACTING]);
        $this->assertState($client, 'schedule_appointment', 'create-appointment');

        $appointment = Appointment::create(['client_id' => $client->id, 'appointment_date' => now()->addDays(2)->toDateString(), 'appointment_time' => '12:00', 'appointment_type' => 'physical_visit', 'status' => 'scheduled']);
        $this->assertState($client, 'appointment_upcoming', 'appointment-result');

        $appointment->update(['appointment_date' => now()->toDateString()]);
        $this->assertState($client, 'appointment_result', 'appointment-result');

        $appointment->update(['status' => 'completed']);
        Appointment::create(['client_id' => $client->id, 'appointment_date' => now()->toDateString(), 'appointment_time' => '15:00', 'appointment_type' => 'installation', 'status' => 'scheduled']);
        $this->assertState($client, 'installation_due', 'complete-installation');
    }

    public function test_follow_up_and_decision_states(): void
    {
        $client = $this->client('Trial Shop', ClientLifecycle::INSTALLED_FREE);
        $this->assertState($client, 'decision', 'start-subscription');
        $this->assertState($client, 'decision', 'start-subscription', $this->staff);

        DB::table('follow_ups')->insert([
            'client_id' => $client->id, 'user_id' => $this->admin->id, 'method' => 'phone', 'reason' => 'Trial', 'next_action' => 'Call back',
            'next_follow_up_date' => now()->subDay()->toDateString(), 'follow_up_date_time' => now()->subHour()->toDateTimeString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertState($client, 'follow_up_due', 'follow-up');
    }

    public function test_subscriber_former_and_closed_states(): void
    {
        $client = $this->client('Subscribed Kitchen', ClientLifecycle::DECISION_PENDING);
        $subscription = $this->startSubscription($client);
        $this->assertSame(ClientLifecycle::SUBSCRIBER, $client->fresh()->stage);

        // The first invoice is due: owners record the payment, staff report it for approval.
        $this->assertState($client, 'payment_due', 'record-payment');
        $this->assertState($client, 'payment_due', 'record-payment', $this->staff);
        $this->actingAs($this->staff)->get(route('clients.show', $client))
            ->assertSee(route('clients.payment-receipts.store', $client), false)
            ->assertDontSee(route('clients.payments.normal.store', $client), false);
        $this->actingAs($this->admin)->get(route('clients.show', $client))
            ->assertSee(route('clients.payments.normal.store', $client), false);

        Invoice::where('client_id', $client->id)->update(['status' => Invoice::STATUS_VOIDED]);
        $this->assertState($client, 'subscriber', 'record-call');

        app(SubscriptionBillingService::class)->scheduleCancellation($subscription, 'Budget', $this->admin->id);
        $this->assertState($client, 'subscriber_ending', 'record-call');
        app(SubscriptionBillingService::class)->generateRenewals(now()->addMonths(3), false, $this->admin->id);
        $this->assertSame(ClientLifecycle::FORMER_SUBSCRIBER, $client->fresh()->stage);

        $response = $this->assertState($client, 'renewal', 'start-subscription');
        $response->assertSee(__('notify.client_hub.actions.renew'))->assertSee('بانتظار التجديد / مشترك سابق');

        $closed = $this->client('Shut Shop', ClientLifecycle::CONTACTING);
        app(ClientOperationalWorkflowService::class)->closeClient($closed, $this->admin, 'not_interested', null);
        $this->assertState($closed, 'closed', 'reopen-client');
    }

    public function test_active_paid_subscription_blocks_manual_close(): void
    {
        $client = $this->client('Cannot Close Cafe', ClientLifecycle::DECISION_PENDING);
        $this->startSubscription($client);

        $html = $this->actingAs($this->admin)->get(route('clients.show', $client))->getContent();
        $this->assertStringNotContainsString('data-open-sheet="modal-close-client"', $html);

        $this->actingAs($this->admin)->post(route('clients.close', $client), ['closed_reason_code' => 'not_interested'])
            ->assertSessionHasErrors('closed_reason_code');
        $this->actingAs($this->admin)->delete(route('clients.destroy', $client))->assertSessionHasErrors('closed_reason_code');
        $this->assertSame(ClientLifecycle::SUBSCRIBER, $client->fresh()->stage);

        $this->expectException(ValidationException::class);
        app(ClientOperationalWorkflowService::class)->closeClient($client->fresh(), $this->admin, 'other', 'test');
    }

    public function test_renewing_a_former_subscriber_returns_them_to_subscriber(): void
    {
        $client = $this->client('Returning Salon', ClientLifecycle::FORMER_SUBSCRIBER);
        $system = Product::sellable()->firstOrFail();

        $this->actingAs($this->staff)->post(route('clients.guided-subscription.store', $client), [
            '_idempotency_key' => 'p10-renew-1',
            'system_ids' => [$system->id],
            'billing_interval' => 'monthly',
            'agreed_value_jod' => '30.000',
            'start_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(ClientLifecycle::SUBSCRIBER, $client->fresh()->stage);
        $this->assertSame(2, DB::table('notifications')->where('type', 'staff_subscription_started')->count());
    }

    // ----- Workspace: actions per role ---------------------------------------

    public function test_staff_workspace_hides_owner_only_controls(): void
    {
        $client = $this->client('Role Split Cafe', ClientLifecycle::DECISION_PENDING, [
            'referred_by_name' => 'Samir', 'referral_note' => 'Friend of owner', 'referral_commission_bps' => 1250,
        ]);
        $this->startSubscription($client);
        $contract = Contract::where('client_id', $client->id)->first();
        $this->assertNotNull($contract, 'a draft contract is created with the subscription');

        $staff = $this->actingAs($this->staff)->get(route('clients.show', $client))->assertOk();
        $staff->assertSee('Samir')
            ->assertSee('Friend of owner')
            ->assertDontSee('12.50%')
            ->assertDontSee('data-referral-commission', false)
            ->assertDontSee(route('contracts.issue', $contract), false)
            ->assertDontSee('data-subscription-cancel', false)
            ->assertDontSee(route('finance.collections', ['client_id' => $client->id]), false)
            ->assertSee(route('contracts.preview', $contract), false)
            ->assertSee(route('contracts.download-pdf', $contract), false)
            ->assertSee(__('notify.client_hub.actions.payment_received'));

        $owner = $this->actingAs($this->admin)->get(route('clients.show', $client))->assertOk();
        $owner->assertSee('12.50%')
            ->assertSee(route('contracts.issue', $contract), false)
            ->assertSee('data-subscription-cancel', false)
            ->assertSee(route('finance.collections', ['client_id' => $client->id]), false)
            ->assertSee(__('notify.client_hub.contract.draft'));
    }

    public function test_owner_can_stop_and_resume_a_subscription_from_the_workspace(): void
    {
        $client = $this->client('Lifecycle Cafe', ClientLifecycle::DECISION_PENDING);
        $subscription = $this->startSubscription($client);

        $this->actingAs($this->admin)->get(route('clients.show', $client))->assertSee(route('subscriptions.cancel', $subscription), false);
        $this->actingAs($this->admin)->post(route('subscriptions.cancel', $subscription), ['cancellation_reason' => 'Test'])->assertSessionHasNoErrors();
        $this->assertTrue($subscription->fresh()->cancel_at_period_end);
        $this->actingAs($this->admin)->get(route('clients.show', $client))
            ->assertSee(route('subscriptions.cancel.undo', $subscription), false)
            ->assertSee(__('notify.client_hub.subscription.status_ending'));

        $this->actingAs($this->staff)->post(route('subscriptions.cancel.undo', $subscription))->assertForbidden();
    }

    public function test_issued_contract_shows_number_without_issue_action(): void
    {
        $client = $this->client('Issued Contract Co', ClientLifecycle::DECISION_PENDING);
        $this->startSubscription($client);
        $contract = Contract::where('client_id', $client->id)->firstOrFail();
        $contract->forceFill(['status' => 'issued', 'contract_number' => 'ND-2026-0042', 'issued_at' => now()])->saveQuietly();

        $this->actingAs($this->admin)->get(route('clients.show', $client))
            ->assertSee('ND-2026-0042')
            ->assertDontSee(route('contracts.issue', $contract), false)
            ->assertDontSee('contracts/'.$contract->id.'/print', false);
    }

    // ----- Systems & credentials ---------------------------------------------

    public function test_systems_show_access_type_and_staff_can_grant_and_revoke_free_access(): void
    {
        $client = $this->client('Access Shop', ClientLifecycle::CONTACTING);
        $system = Product::sellable()->where('requires_credentials', false)->firstOrFail();

        $html = $this->actingAs($this->staff)->get(route('clients.show', $client))->assertOk()->getContent();
        $this->assertStringContainsString('data-grant-access', $html);
        $this->assertStringContainsString('id="modal-grant-access"', $html);
        $this->assertStringContainsString(route('clients.system-access.store', $client), $html);

        $this->actingAs($this->staff)->post(route('clients.system-access.store', $client), ['system_ids' => [$system->id]])->assertSessionHasNoErrors();
        // Granting twice never duplicates the row.
        $this->actingAs($this->staff)->post(route('clients.system-access.store', $client), ['system_ids' => [$system->id]])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('client_system')->where('client_id', $client->id)->where('product_id', $system->id)->count());

        $html = $this->actingAs($this->staff)->get(route('clients.show', $client))->getContent();
        $this->assertSame(1, substr_count($html, 'data-system-row="'.$system->id.'"'));
        $this->assertStringContainsString(route('clients.system-access.destroy', [$client, $system]), $html);
        $this->assertStringContainsString(__('notify.client_hub.systems.free'), $html);

        $this->actingAs($this->staff)->delete(route('clients.system-access.destroy', [$client, $system]))->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('client_system')->where('client_id', $client->id)->where('product_id', $system->id)->value('revoked_at'));
        $this->assertSame(0, DB::table('payments')->count());
    }

    public function test_paid_access_has_no_revoke_and_credentials_sit_with_capable_systems_only(): void
    {
        $client = $this->client('Credential Cafe', ClientLifecycle::DECISION_PENDING);
        $capable = Product::sellable()->where('requires_credentials', true)->firstOrFail();
        $plain = Product::sellable()->where('requires_credentials', false)->firstOrFail();
        $this->startSubscription($client, [$capable->id, $plain->id]);
        $credential = app(ClientCredentialService::class)->store($client->fresh(), $capable, [
            'login_url' => 'https://login.example.test', 'username' => 'owner@example.test', 'secret' => self::SECRET, 'note' => 'Private note P10',
        ], $this->admin);

        $html = $this->actingAs($this->staff)->get(route('clients.show', $client))->assertOk()->getContent();

        $this->assertStringNotContainsString(self::SECRET, $html);
        $this->assertStringNotContainsString('Private note P10', $html);
        $this->assertStringNotContainsString(route('clients.system-access.destroy', [$client, $capable]), $html);
        $this->assertStringContainsString(__('notify.client_hub.systems.paid'), $html);

        $capableRow = str($html)->betweenFirst('data-system-row="'.$capable->id.'"', '</article>')->toString();
        $plainRow = str($html)->betweenFirst('data-system-row="'.$plain->id.'"', '</article>')->toString();
        $this->assertStringContainsString('data-credential-card', $capableRow);
        $this->assertStringContainsString(route('clients.credentials.reveal', [$client, $credential]), $capableRow);
        $this->assertStringNotContainsString('data-credential-reveal', $plainRow);
        $this->assertStringNotContainsString('name="credential_secret"', $plainRow);
        $this->assertInstanceOf(ClientSystemCredential::class, $credential);
    }

    // ----- Money --------------------------------------------------------------

    public function test_money_card_shows_due_pending_receipts_and_no_finance_totals_for_staff(): void
    {
        $client = $this->client('Money Bistro', ClientLifecycle::DECISION_PENDING);
        $this->startSubscription($client);
        app(PaymentReceiptService::class)->submit($client->fresh(), [
            'amount' => '10.000', 'payment_method' => 'cash', 'received_at' => now()->subHour()->format('Y-m-d H:i'),
        ], $this->staff, 'rcpt_'.Str::uuid());

        $staff = $this->actingAs($this->staff)->get(route('clients.show', $client))->assertOk();
        $staff->assertSee('data-client-money', false)
            ->assertSee('data-amount-due', false)
            ->assertSee('data-client-pending-receipts', false)
            ->assertSee(__('notify.client_hub.money.pending_title'))
            ->assertSee(route('payment-receipts.cancel', PaymentReceiptConfirmation::sole()), false)
            ->assertDontSee(__('notify.client_hub.money.credit'))
            ->assertDontSee('data-money-details', false);

        $this->actingAs($this->admin)->get(route('clients.show', $client))
            ->assertSee('data-money-details', false)
            ->assertSee('data-workspace-action="review-receipts"', false)
            ->assertSee(e(route('finance.collections', ['tab' => 'pending', 'client_id' => $client->id])), false);
    }

    // ----- Activity, deep links, contact -------------------------------------

    public function test_activity_is_human_readable_and_hides_engine_events(): void
    {
        $client = $this->client('Timeline Cafe', ClientLifecycle::CONTACTING);
        DB::table('activity_logs')->insert([
            ['client_id' => $client->id, 'user_id' => $this->admin->id, 'type' => 'payment_allocated', 'description' => 'ALLOCATION-ENGINE-ROW', 'created_at' => now(), 'updated_at' => now()],
            ['client_id' => $client->id, 'user_id' => $this->admin->id, 'type' => 'revenue_schedule_created', 'description' => 'REVENUE-SCHEDULE-ROW', 'created_at' => now(), 'updated_at' => now()],
            ['client_id' => $client->id, 'user_id' => $this->admin->id, 'type' => 'payment_received_v2', 'description' => 'تم تسجيل دفعة V2 بقيمة 10.000 د.أ', 'created_at' => now(), 'updated_at' => now()],
            ['client_id' => $client->id, 'user_id' => $this->staff->id, 'type' => 'client_created', 'description' => 'created', 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()],
        ]);

        $html = $this->actingAs($this->admin)->get(route('clients.show', $client))->assertOk()->getContent();
        $activity = str($html)->betweenFirst('data-client-activity', '</section>')->toString();

        $this->assertStringNotContainsString('ALLOCATION-ENGINE-ROW', $activity);
        $this->assertStringNotContainsString('REVENUE-SCHEDULE-ROW', $activity);
        $this->assertStringNotContainsString('V2', $activity);
        $this->assertStringNotContainsString('payment_received_v2', $activity);
        $this->assertStringContainsString(__('notify.client_hub.activity.types.payment_received_v2'), $activity);
        $this->assertStringContainsString(__('notify.client_hub.activity.types.client_created'), $activity);
        $this->assertStringContainsString(__('notify.client_hub.activity.by', ['name' => $this->staff->name]), $activity);
    }

    public function test_deep_links_open_the_matching_sheet(): void
    {
        $client = $this->client('Deep Link Cafe', ClientLifecycle::SUBSCRIBER);

        $this->actingAs($this->admin)->get(route('clients.show', ['client' => $client->id, 'open' => 'record-payment']))
            ->assertOk()
            ->assertViewHas('openSheet', 'modal-record-payment');
        $this->actingAs($this->admin)->get(route('clients.show', ['client' => $client->id, 'open' => 'not-a-sheet']))
            ->assertViewHas('openSheet', null);

        // Today work items link with hash anchors; the workspace maps them to sheets.
        $html = $this->actingAs($this->admin)->get(route('clients.show', $client))->getContent();
        foreach (["'#call': 'modal-record-call'", "'#payment': 'modal-record-payment'", "'#follow-up': 'modal-follow-up'"] as $mapping) {
            $this->assertStringContainsString($mapping, $html);
        }
    }

    public function test_business_and_contact_are_separate_and_a_name_is_optional(): void
    {
        $client = $this->client('Owner Phone Shop', ClientLifecycle::CONTACTING, ['phone' => '0781112222', 'primary_phone_type' => 'owner', 'business_phone' => null]);
        $client->contacts()->create(['name' => null, 'role' => 'owner', 'primary_phone' => '0781112222', 'is_primary' => true]);

        $html = $this->actingAs($this->staff)->get(route('clients.show', $client))->assertOk()->getContent();
        $business = str($html)->betweenFirst('data-business-details', 'data-contact-details')->toString();
        $contact = str($html)->betweenFirst('data-contact-details', '</section>')->toString();

        $this->assertStringContainsString(__('notify.clients.contact_model.phone_types.owner'), $business);
        $this->assertStringContainsString(__('notify.client_hub.details.not_set'), $business);
        $this->assertStringContainsString(__('notify.clients.contact_model.unnamed_contact'), $contact);
        $this->assertStringContainsString('0781112222', $contact);

        $empty = $this->client('No Contact Shop', ClientLifecycle::CONTACTING, ['phone' => '0781119999']);
        $this->actingAs($this->staff)->get(route('clients.show', $empty))
            ->assertSee('data-no-contact', false)
            ->assertSee(__('notify.client_hub.details.add_contact'));
    }

    public function test_workspace_never_embeds_custom_projects_or_legacy_partials(): void
    {
        $client = $this->client('Boundary Cafe', ClientLifecycle::CONTACTING);

        $this->actingAs($this->admin)->get(route('clients.show', $client))
            ->assertOk()
            ->assertDontSee(route('custom-projects.create', ['client_id' => $client->id]), false)
            ->assertDontSee('id="collapsible-', false)
            ->assertDontSee('sec-system-access', false)
            ->assertDontSee('DAILY_CALL_01')
            ->assertDontSee('notify.client_hub.', false);

        foreach (['subscription-billing', 'history', 'management-finance', 'credentials'] as $partial) {
            $this->assertFileDoesNotExist(resource_path('views/clients/workspace/'.$partial.'.blade.php'));
        }
    }

    public function test_client_hub_lang_keys_have_parity(): void
    {
        $ar = require lang_path('ar/notify.php');
        $en = require lang_path('en/notify.php');

        $this->assertSame(array_keys(\Illuminate\Support\Arr::dot($ar['client_hub'])), array_keys(\Illuminate\Support\Arr::dot($en['client_hub'])));
    }

    // ----- helpers ------------------------------------------------------------

    private function assertState(Client $client, string $state, string $primarySheet, ?User $actor = null)
    {
        $response = $this->actingAs($actor ?? $this->admin)->get(route('clients.show', $client))->assertOk();
        $workspace = $response->viewData('workspace');

        $this->assertSame($state, $workspace->state['key'], 'state');
        $this->assertSame($primarySheet, $workspace->primaryAction['key'] ?? null, 'primary action for '.$state);
        $this->assertLessThanOrEqual(2, count($workspace->secondaryActions));

        return $response;
    }

    private function client(string $name, string $stage, array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => $name,
            'phone' => '079'.random_int(1000000, 9999999),
            'city_area' => 'Amman',
            'business_category' => 'Cafe',
            'lead_source' => 'Referral',
            'status' => 'prospect',
            'stage' => $stage,
        ], $overrides));
    }

    private function startSubscription(Client $client, ?array $systemIds = null): Subscription
    {
        $systems = Product::sellable()->whereIn('id', $systemIds ?? [Product::sellable()->orderBy('id')->value('id')])->get();

        [$subscription] = app(SubscriptionBillingService::class)->startAgreedSubscription($client, $systems, [
            'billing_interval' => 'monthly',
            'agreed_value_minor' => 50000,
            'start_date' => now()->toDateString(),
            'payment_terms' => 'full',
        ], $this->admin->id);

        return $subscription;
    }
}
