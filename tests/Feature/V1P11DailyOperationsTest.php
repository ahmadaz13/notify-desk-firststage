<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientReviewItem;
use App\Models\DailyNote;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CompletedWorkService;
use App\Services\PaymentReceiptService;
use App\Services\UnifiedOperationalWorkProjection;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P11 — Daily operations experience (§20): Today as the operational home.
 * Projection authority, Overdue/Next/Later, All/My Work, Owner vs Staff collections, P10 deep links,
 * return to Today, Daily Notes, completed-today, empty state, security and query bounds.
 */
class V1P11DailyOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $staff;

    private int $invoiceSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Amman'));
        $this->owner = User::factory()->create(['role' => User::ROLE_FOUNDER, 'name' => 'Owner One', 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF, 'name' => 'Sara Staff', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- fixtures

    private function client(string $name, array $attributes = []): Client
    {
        return Client::create(array_merge([
            'business_name' => $name,
            'business_category' => 'Restaurant',
            'phone' => '079'.random_int(1000000, 9999999),
            'city_area' => 'Amman',
            'lead_source' => 'Direct',
            'stage' => ClientLifecycle::CONTACTING,
            'status' => 'prospect',
        ], $attributes));
    }

    private function appointment(Client $client, string $date, string $time, array $attributes = [], array $attendees = []): Appointment
    {
        $appointment = Appointment::create(array_merge([
            'client_id' => $client->id,
            'appointment_date' => $date,
            'appointment_time' => $time,
            'appointment_type' => AppointmentTypes::PHYSICAL_VISIT,
            'status' => 'scheduled',
        ], $attributes));
        $appointment->users()->sync($attendees);

        return $appointment;
    }

    private function followUp(Client $client, string $at, array $attributes = []): int
    {
        return DB::table('follow_ups')->insertGetId(array_merge([
            'client_id' => $client->id,
            'user_id' => $this->owner->id,
            'method' => 'phone',
            'reason' => 'Price follow-up',
            'next_action' => 'Call back',
            'next_follow_up_date' => substr($at, 0, 10),
            'follow_up_date_time' => $at,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    private function invoice(Client $client, string $dueDate, int $totalMinor = 45000): Invoice
    {
        return Invoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-P11-'.(++$this->invoiceSeq),
            'status' => Invoice::STATUS_ISSUED,
            'total_minor' => $totalMinor,
            'subtotal_minor' => $totalMinor,
            'tax_minor' => 0,
            'discount_minor' => 0,
            'currency' => 'JOD',
            'issue_date' => Carbon::parse($dueDate)->subDays(5)->toDateString(),
            'due_date' => $dueDate,
        ]);
    }

    private function subscriber(string $name, array $attributes = []): Client
    {
        return $this->client($name, array_merge(['stage' => ClientLifecycle::SUBSCRIBER, 'status' => 'subscriber'], $attributes));
    }

    private function today(User $user, string $scope = 'all'): array
    {
        return app(UnifiedOperationalWorkProjection::class)->today($user, Carbon::now('Asia/Amman'), $scope);
    }

    private function ids(Collection $items): array
    {
        return $items->pluck('id')->all();
    }

    private function allIds(array $projection): array
    {
        return array_merge($this->ids($projection['overdue']), $this->ids($projection['next']), $this->ids($projection['later_today']));
    }

    private function find(array $projection, string $id): ?array
    {
        return collect([$projection['overdue'], $projection['next'], $projection['later_today']])->flatten(1)->firstWhere('id', $id);
    }

    // ---------------------------------------------------------------- projection

    public function test_today_projects_every_authoritative_source_into_one_queue(): void
    {
        $meeting = $this->appointment($this->client('Yasmin Restaurant'), '2026-09-24', '11:00:00');
        $install = $this->appointment($this->client('Fajr Bakery'), '2026-09-24', '15:00:00', ['appointment_type' => AppointmentTypes::INSTALLATION]);
        $followUp = $this->followUp($this->client('Rayhan Store'), '2026-09-24 09:00:00');
        $payer = $this->subscriber('Sham Sweets');
        $this->invoice($payer, '2026-09-20');
        $review = ClientReviewItem::create(['client_id' => $this->client('Noor Library')->id, 'type' => ClientReviewItem::TYPE_WRONG_INVALID, 'status' => ClientReviewItem::STATUS_PENDING, 'created_by' => $this->staff->id]);

        $today = $this->today($this->owner);

        $this->assertSame(['fu-'.$followUp, 'col-'.$payer->id], $this->ids($today['overdue']));
        $this->assertSame(['apt-'.$meeting->id, 'rev-'.$review->id], $this->ids($today['next']));
        $this->assertSame(['apt-'.$install->id], $this->ids($today['later_today']));
        $this->assertSame(5, $today['counts']['total']);

        $this->assertSame('installation', $this->find($today, 'apt-'.$install->id)['type']);
        $this->assertSame(45000, $this->find($today, 'col-'.$payer->id)['amount_minor']);
        $this->assertSame('overdue', $this->find($today, 'fu-'.$followUp)['timing']['state']);
    }

    public function test_completed_cancelled_resolved_and_future_work_is_not_actionable_today(): void
    {
        $client = $this->client('Madaba Visit');
        $this->appointment($client, '2026-09-24', '11:00:00', ['status' => 'completed']);
        $this->appointment($client, '2026-09-24', '12:00:00', ['status' => 'cancelled']);
        $this->appointment($client, '2026-09-25', '11:00:00');
        $this->followUp($client, '2026-09-24 09:00:00', ['completed_at' => now(), 'completed_by' => $this->owner->id]);
        $this->followUp($client, '2026-09-26 09:00:00');
        ClientReviewItem::create(['client_id' => $client->id, 'type' => ClientReviewItem::TYPE_NOT_INTERESTED, 'status' => ClientReviewItem::STATUS_RESOLVED, 'created_by' => $this->staff->id]);
        $this->invoice($this->subscriber('Future Payer'), '2026-10-05');
        $archived = $this->subscriber('Archived Payer', ['status' => 'archived']);
        $this->invoice($archived, '2026-09-01');

        $this->assertSame(0, $this->today($this->owner)['counts']['total']);
    }

    public function test_overdue_next_and_later_classification_with_in_progress_window(): void
    {
        $client = $this->client('Timing Client');
        $late = $this->appointment($client, '2026-09-24', '08:30:00');       // 90 min ago
        $inProgress = $this->appointment($client, '2026-09-24', '09:30:00'); // started 30 min ago
        $soon = $this->appointment($client, '2026-09-24', '11:45:00');       // within 2h
        $later = $this->appointment($client, '2026-09-24', '16:00:00');
        $callLate = $this->followUp($client, '2026-09-24 09:50:00');         // follow-ups have no grace

        $today = $this->today($this->owner);

        $this->assertSame(['apt-'.$late->id, 'fu-'.$callLate], $this->ids($today['overdue']));
        $this->assertSame(['apt-'.$inProgress->id, 'apt-'.$soon->id], $this->ids($today['next']));
        $this->assertSame(['apt-'.$later->id], $this->ids($today['later_today']));

        $this->assertSame('now', $this->find($today, 'apt-'.$inProgress->id)['timing']['state']);
        $this->assertSame('soon', $this->find($today, 'apt-'.$soon->id)['timing']['state']);
        $this->assertSame('متأخر ساعة', $this->find($today, 'apt-'.$late->id)['timing']['text']);
        $this->assertSame('بعد ساعة', $this->find($today, 'apt-'.$soon->id)['timing']['text']);
    }

    public function test_next_pulls_the_nearest_later_item_when_nothing_is_imminent(): void
    {
        $client = $this->client('Afternoon Client');
        $first = $this->appointment($client, '2026-09-24', '15:00:00');
        $second = $this->appointment($client, '2026-09-24', '17:00:00');

        $today = $this->today($this->owner);

        $this->assertSame(['apt-'.$first->id], $this->ids($today['next']));
        $this->assertSame(['apt-'.$second->id], $this->ids($today['later_today']));
    }

    public function test_overdue_orders_by_operational_importance_then_age(): void
    {
        $payer = $this->subscriber('Old Debt');
        $this->invoice($payer, '2026-09-01');
        $review = ClientReviewItem::create(['client_id' => $this->client('Review Client')->id, 'type' => ClientReviewItem::TYPE_NOT_INTERESTED, 'status' => ClientReviewItem::STATUS_PENDING, 'created_by' => $this->staff->id]);
        DB::table('client_review_items')->where('id', $review->id)->update(['created_at' => '2026-09-20 09:00:00']);
        $followUp = $this->followUp($this->client('Call Client'), '2026-09-22 09:00:00');
        $older = $this->appointment($this->client('Visit A'), '2026-09-21', '10:00:00');
        $newer = $this->appointment($this->client('Visit B'), '2026-09-23', '10:00:00');

        $this->assertSame(
            ['apt-'.$older->id, 'apt-'.$newer->id, 'fu-'.$followUp, 'col-'.$payer->id, 'rev-'.$review->id],
            $this->ids($this->today($this->owner)['overdue'])
        );
    }

    public function test_payment_due_today_is_not_late_and_follows_the_working_day(): void
    {
        $payer = $this->subscriber('Due Today');
        $this->invoice($payer, '2026-09-24');
        $this->appointment($this->client('Soon'), '2026-09-24', '10:30:00');

        $morning = $this->today($this->owner);
        $this->assertSame(['col-'.$payer->id], $this->ids($morning['later_today']));
        $this->assertSame('due_today', $this->find($morning, 'col-'.$payer->id)['timing']['state']);

        Carbon::setTestNow(Carbon::parse('2026-09-24 15:30:00', 'Asia/Amman'));
        $afternoon = $this->today($this->owner);
        $this->assertContains('col-'.$payer->id, $this->ids($afternoon['next']));
        $this->assertNotContains('col-'.$payer->id, $this->ids($afternoon['overdue']));
    }

    public function test_operational_day_boundary_is_asia_amman(): void
    {
        // 21:30 UTC on the 24th is 00:30 on the 25th in Amman.
        Carbon::setTestNow(Carbon::parse('2026-09-24 21:30:00', 'UTC'));
        $client = $this->client('Boundary Client');
        $lastNight = $this->appointment($client, '2026-09-24', '23:00:00');
        $tomorrowMorning = $this->appointment($client, '2026-09-25', '10:00:00');
        $dayAfter = $this->appointment($client, '2026-09-26', '10:00:00');

        $today = $this->today($this->owner);

        $this->assertSame(['apt-'.$lastNight->id], $this->ids($today['overdue']));
        $this->assertSame(['apt-'.$tomorrowMorning->id], $this->ids($today['next']));
        $this->assertNotContains('apt-'.$dayAfter->id, $this->allIds($today));

        $this->actingAs($this->owner)->putJson(route('daily-notes.save'), ['content' => 'after midnight'])->assertOk();
        $this->assertSame('2026-09-25', DailyNote::where('user_id', $this->owner->id)->first()->date->toDateString());
    }

    // ---------------------------------------------------------------- All / My Work

    public function test_my_work_uses_existing_assignment_semantics(): void
    {
        $staffAttends = $this->appointment($this->client('Attended'), '2026-09-24', '11:00:00', [], [$this->staff->id]);
        $staffOwnsClient = $this->appointment($this->client('Owned', ['primary_owner_id' => $this->staff->id]), '2026-09-24', '12:00:00', [], [$this->owner->id]);
        $notMine = $this->appointment($this->client('Someone Else'), '2026-09-24', '11:30:00', [], [$this->owner->id]);
        $myFollowUp = $this->followUp($this->client('Follow Mine'), '2026-09-24 11:00:00', ['user_id' => $this->staff->id]);
        $theirFollowUp = $this->followUp($this->client('Follow Theirs'), '2026-09-24 11:00:00');
        $myPayer = $this->subscriber('My Payer', ['primary_owner_id' => $this->staff->id]);
        $this->invoice($myPayer, '2026-09-20');
        $otherPayer = $this->subscriber('Other Payer');
        $this->invoice($otherPayer, '2026-09-20');

        $mine = $this->allIds($this->today($this->staff, 'my'));
        sort($mine);
        $expected = ['apt-'.$staffAttends->id, 'apt-'.$staffOwnsClient->id, 'col-'.$myPayer->id, 'fu-'.$myFollowUp];
        sort($expected);
        $this->assertSame($expected, $mine);

        $all = $this->allIds($this->today($this->staff, 'all'));
        foreach (['apt-'.$notMine->id, 'fu-'.$theirFollowUp, 'col-'.$otherPayer->id] as $id) {
            $this->assertContains($id, $all);
        }

        $myPage = $this->actingAs($this->staff)->get(route('dashboard', ['scope' => 'my']));
        $this->assertMatchesRegularExpression('/data-team-scope="my"\s+aria-current="true"/', $myPage->getContent());
        $myPage
            ->assertOk()
            ->assertSee('Attended')
            ->assertDontSee('Someone Else');
    }

    // ---------------------------------------------------------------- Owner vs Staff

    public function test_staff_gets_operational_collections_with_the_receipt_path_only(): void
    {
        $payer = $this->subscriber('Jerash Market');
        $this->invoice($payer, '2026-09-20', 75000);

        $staffPage = $this->actingAs($this->staff)->withSession(['locale' => 'ar'])->get(route('dashboard'))->assertOk();
        $staffPage->assertSee('Jerash Market')
            ->assertSee('data-card-primary-action="payment_receipt"', false)
            ->assertDontSee('data-card-primary-action="payment"', false)
            ->assertDontSee('data-today-signal="pending-confirmations"', false)
            ->assertSee('75.000');

        $ownerPage = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk();
        $ownerPage->assertSee('data-card-primary-action="payment"', false)
            ->assertDontSee('data-card-primary-action="payment_receipt"', false);

        // The owner-only confirmed-payment path stays closed to Staff.
        $this->actingAs($this->staff)
            ->post(route('clients.payments.normal.store', $payer), ['amount' => '10.000', 'payment_method' => 'cash', 'received_at' => '2026-09-24 09:00', '_idempotency_key' => 'p11-staff-denied'])
            ->assertForbidden();
    }

    public function test_pending_receipts_signal_and_collection_coverage(): void
    {
        $partly = $this->subscriber('Partly Received');
        $this->invoice($partly, '2026-09-20', 45000);
        $covered = $this->subscriber('Fully Received');
        $this->invoice($covered, '2026-09-20', 20000);
        $receipts = app(PaymentReceiptService::class);
        $receipts->submit($partly, ['amount' => '20.000', 'payment_method' => 'cash', 'received_at' => '2026-09-24 09:00'], $this->staff, 'p11-r1');
        $receipts->submit($covered, ['amount' => '20.000', 'payment_method' => 'cash', 'received_at' => '2026-09-24 09:00'], $this->staff, 'p11-r2');

        $today = $this->today($this->owner);
        $this->assertSame(45000, $this->find($today, 'col-'.$partly->id)['amount_minor'], 'pending receipts have no financial effect');
        $this->assertSame(20000, $this->find($today, 'col-'.$partly->id)['pending_minor']);
        $this->assertNull($this->find($today, 'col-'.$covered->id), 'fully covered by a pending receipt: waiting for confirmation, not action');

        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertSee('data-today-signal="pending-confirmations"', false)
            ->assertSee(route('finance.collections', ['tab' => 'pending']), false);
        $this->actingAs($this->staff)->get(route('dashboard'))
            ->assertSee('data-today-signal="my-pending-receipts"', false)
            ->assertDontSee('data-today-signal="pending-confirmations"', false);
    }

    public function test_today_never_shows_company_finance_to_staff(): void
    {
        $this->invoice($this->subscriber('Money Client'), '2026-09-20', 90000);
        $this->invoice($this->subscriber('Money Client 2'), '2026-09-21', 10000);

        $html = $this->actingAs($this->staff)->withSession(['locale' => 'en'])->get(route('dashboard'))->assertOk()->getContent();
        foreach (['100.000', 'MRR', 'ARR', 'Profit', 'Cash Snapshot', 'Receivables', 'INV-P11-'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html, "Staff Today must not show {$forbidden}");
        }
    }

    // ---------------------------------------------------------------- deep links

    public function test_cards_deep_link_to_canonical_workspace_sheets(): void
    {
        $meeting = $this->appointment($client = $this->client('Deep Link'), '2026-09-24', '11:00:00');
        $install = $this->appointment($client, '2026-09-24', '15:00:00', ['appointment_type' => AppointmentTypes::INSTALLATION]);
        $followUp = $this->followUp($client, '2026-09-24 09:00:00');
        $payer = $this->subscriber('Deep Payer');
        $this->invoice($payer, '2026-09-20');
        $review = ClientReviewItem::create(['client_id' => $client->id, 'type' => ClientReviewItem::TYPE_NOT_INTERESTED, 'status' => ClientReviewItem::STATUS_PENDING, 'created_by' => $this->staff->id]);

        $today = $this->today($this->owner);
        $href = fn (string $id) => $this->find($today, $id)['primary_action']['href'];

        $this->assertSame(route('clients.show', ['client' => $client->id, 'open' => 'appointment-result', 'appointment' => $meeting->id, 'from' => 'today']), $href('apt-'.$meeting->id));
        $this->assertSame(route('clients.show', ['client' => $client->id, 'open' => 'complete-installation', 'appointment' => $install->id, 'from' => 'today']), $href('apt-'.$install->id));
        $this->assertSame(route('clients.show', ['client' => $client->id, 'open' => 'follow-up', 'follow_up' => $followUp, 'from' => 'today']), $href('fu-'.$followUp));
        $this->assertSame(route('clients.show', ['client' => $payer->id, 'open' => 'record-payment', 'from' => 'today']), $href('col-'.$payer->id));
        $this->assertSame(route('clients.show', ['client' => $client->id, 'from' => 'today']).'#review', $href('rev-'.$review->id));

        // The business name opens the Client Workspace; scope is carried for the return trip.
        $mine = app(UnifiedOperationalWorkProjection::class)->today($this->owner, now(), 'my');
        $this->assertTrue($mine['next']->every(fn (array $item) => str_contains($item['client_href'], 'scope=my')));
        $this->assertStringContainsString('from=today', $this->find($today, 'apt-'.$meeting->id)['client_href']);
    }

    public function test_workspace_sheets_act_on_the_linked_appointment_and_follow_up(): void
    {
        $client = $this->client('Two Visits');
        $first = $this->appointment($client, '2026-09-24', '09:00:00');
        $second = $this->appointment($client, '2026-09-24', '15:00:00');
        $firstFollowUp = $this->followUp($client, '2026-09-24 08:00:00');
        $secondFollowUp = $this->followUp($client, '2026-09-24 16:00:00');

        $default = $this->actingAs($this->owner)->get(route('clients.show', ['client' => $client->id, 'open' => 'appointment-result']))->assertOk();
        $default->assertSee(route('appointments.compact-outcome.store', $first->id), false)
            ->assertSee(route('follow-ups.complete', $firstFollowUp), false)
            ->assertDontSee('data-return-to-today', false);

        $linked = $this->actingAs($this->owner)->get(route('clients.show', [
            'client' => $client->id, 'open' => 'appointment-result', 'appointment' => $second->id, 'follow_up' => $secondFollowUp, 'from' => 'today', 'scope' => 'my',
        ]))->assertOk();
        $linked->assertSee(route('appointments.compact-outcome.store', $second->id), false)
            ->assertDontSee(route('appointments.compact-outcome.store', $first->id), false)
            ->assertSee(route('follow-ups.complete', $secondFollowUp), false)
            ->assertSee('data-return-to-today', false)
            ->assertSee(route('dashboard', ['scope' => 'my']), false)
            ->assertViewHas('openSheet', 'modal-appointment-result');

        // Unknown ids fall back to the default record.
        $this->actingAs($this->owner)->get(route('clients.show', ['client' => $client->id, 'appointment' => 999999]))
            ->assertSee(route('appointments.compact-outcome.store', $first->id), false);
    }

    public function test_collection_link_opens_owner_payment_or_staff_receipt_sheet(): void
    {
        $payer = $this->subscriber('Sheet Payer');
        $this->invoice($payer, '2026-09-20');
        $url = route('clients.show', ['client' => $payer->id, 'open' => 'record-payment', 'from' => 'today']);

        $this->actingAs($this->owner)->get($url)->assertOk()
            ->assertViewHas('openSheet', 'modal-record-payment')
            ->assertSee(route('clients.payments.normal.store', $payer), false);
        $this->actingAs($this->staff)->get($url)->assertOk()
            ->assertViewHas('openSheet', 'modal-record-payment')
            ->assertSee(route('clients.payment-receipts.store', $payer), false)
            ->assertDontSee(route('clients.payments.normal.store', $payer), false);
    }

    // ---------------------------------------------------------------- completion feedback

    public function test_completing_from_today_returns_to_today_and_removes_the_item(): void
    {
        $client = $this->client('Return Client');
        $followUp = $this->followUp($client, '2026-09-24 09:00:00');
        $this->assertContains('fu-'.$followUp, $this->allIds($this->today($this->owner)));

        $this->actingAs($this->owner)
            ->from(route('clients.show', ['client' => $client->id, 'open' => 'follow-up', 'follow_up' => $followUp, 'from' => 'today']))
            ->post(route('follow-ups.complete', $followUp), [
                'outcome' => 'callback_later',
                'follow_up_date_time' => '2026-09-26 10:00:00',
                '_return_to' => 'today',
                '_return_scope' => 'my',
            ])
            ->assertRedirect(route('dashboard', ['scope' => 'my']))
            ->assertSessionHas('success');

        $this->assertNotContains('fu-'.$followUp, $this->allIds($this->today($this->owner)));
        $this->assertSame(1, app(CompletedWorkService::class)->forUser($this->owner)['breakdown']['follow_ups']);

        // back() targets (record call) also return; "All open" returns to the work mode.
        $this->actingAs($this->owner)
            ->from(route('clients.show', ['client' => $client->id, 'open' => 'record-call', 'from' => 'work']))
            ->post(route('clients.contact-attempts.store', $client), ['result' => 'no_answer_busy', '_return_to' => 'work'])
            ->assertRedirect(route('dashboard', ['mode' => 'work']));
    }

    public function test_return_to_today_never_hides_errors_or_next_steps(): void
    {
        $client = $this->client('Guard Client');
        $followUp = $this->followUp($client, '2026-09-24 09:00:00');
        $workspace = route('clients.show', ['client' => $client->id, 'open' => 'follow-up', 'from' => 'today']);

        // Validation failure: stays on the workspace with errors.
        $this->actingAs($this->owner)->from($workspace)
            ->post(route('follow-ups.complete', $followUp), ['outcome' => 'callback_later', '_return_to' => 'today'])
            ->assertRedirect($workspace)
            ->assertSessionHasErrors('follow_up_date_time');

        // A deliberate next step (ready to subscribe) keeps the user in the workspace.
        $response = $this->actingAs($this->owner)->from($workspace)
            ->post(route('follow-ups.complete', $followUp), ['outcome' => 'subscribe', '_return_to' => 'today']);
        $this->assertStringContainsString('/clients/'.$client->id, $response->headers->get('Location'));

        // Without the Today context nothing changes.
        $other = $this->followUp($client, '2026-09-24 09:30:00');
        $this->actingAs($this->owner)
            ->post(route('follow-ups.complete', $other), ['outcome' => 'callback_later', 'follow_up_date_time' => '2026-09-26 10:00:00'])
            ->assertRedirect(route('clients.show', $client->id));

        // Only the fixed dashboard route is ever used as a destination.
        $third = $this->followUp($client, '2026-09-24 09:40:00');
        $this->actingAs($this->owner)
            ->post(route('follow-ups.complete', $third), ['outcome' => 'callback_later', 'follow_up_date_time' => '2026-09-26 10:00:00', '_return_to' => 'https://evil.example'])
            ->assertRedirect(route('clients.show', $client->id));
    }

    // ---------------------------------------------------------------- completed today

    public function test_completed_today_counts_only_the_users_own_work_from_workflow_records(): void
    {
        $client = $this->client('Done Client');
        DB::table('contact_attempts')->insert([
            ['client_id' => $client->id, 'user_id' => $this->owner->id, 'method' => 'phone', 'result' => 'no_answer_busy', 'created_at' => '2026-09-24 08:00:00', 'updated_at' => now()],
            ['client_id' => $client->id, 'user_id' => $this->owner->id, 'method' => 'phone', 'result' => 'no_answer_busy', 'created_at' => '2026-09-23 18:00:00', 'updated_at' => now()],
            ['client_id' => $client->id, 'user_id' => $this->staff->id, 'method' => 'phone', 'result' => 'no_answer_busy', 'created_at' => '2026-09-24 08:30:00', 'updated_at' => now()],
        ]);
        $this->followUp($client, '2026-09-24 08:00:00', ['completed_at' => '2026-09-24 09:00:00', 'completed_by' => $this->owner->id]);
        $visit = $this->appointment($client, '2026-09-24', '08:00:00');
        DB::table('meeting_outcomes')->insert([
            'appointment_id' => $visit->id, 'client_id' => $client->id, 'user_id' => $this->owner->id, 'attendance_status' => 'attended',
            'meeting_date_time' => '2026-09-24 08:00:00', 'meeting_type' => 'physical_visit', 'interest_level' => 'high', 'next_action' => 'x',
            'created_at' => '2026-09-24 09:10:00', 'updated_at' => now(),
        ]);
        ClientReviewItem::create(['client_id' => $client->id, 'type' => ClientReviewItem::TYPE_NOT_INTERESTED, 'status' => ClientReviewItem::STATUS_RESOLVED, 'created_by' => $this->staff->id, 'resolved_by' => $this->owner->id, 'resolved_at' => '2026-09-24 09:20:00']);
        app(PaymentReceiptService::class)->submit($this->subscriber('Receipt Client'), ['amount' => '5.000', 'payment_method' => 'cash', 'received_at' => '2026-09-24 09:00'], $this->staff, 'p11-done');

        $owner = app(CompletedWorkService::class)->forUser($this->owner);
        $this->assertSame(['calls' => 1, 'appointments' => 1, 'installations' => 0, 'follow_ups' => 1, 'collections' => 0, 'reviews' => 1], $owner['breakdown']);
        $this->assertSame(4, $owner['total']);

        $staff = app(CompletedWorkService::class)->forUser($this->staff);
        $this->assertSame(1, $staff['breakdown']['calls']);
        $this->assertSame(1, $staff['breakdown']['collections'], 'Staff "Payment received" counts as completed work');
        $this->assertSame(2, $staff['total']);

        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertSee('data-completed-total="4"', false)
            ->assertSee('data-completed-type="reviews"', false);
    }

    // ---------------------------------------------------------------- empty state

    public function test_quiet_day_shows_a_calm_state_with_the_next_real_item(): void
    {
        $tomorrow = $this->appointment($this->client('Tomorrow Client'), '2026-09-25', '10:00:00');

        $this->actingAs($this->owner)->withSession(['locale' => 'ar'])->get(route('dashboard'))->assertOk()
            ->assertSee('data-today-empty', false)
            ->assertSee('لا توجد مهام مستحقة الآن')
            ->assertSee('data-today-next-upcoming', false)
            ->assertSee('Tomorrow Client')
            ->assertDontSee('data-today-section=', false)
            ->assertSee('data-daily-notes', false);

        $tomorrow->update(['status' => 'cancelled']);
        $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()
            ->assertSee('data-today-empty', false)
            ->assertDontSee('data-today-next-upcoming', false);
    }

    // ---------------------------------------------------------------- Daily Notes

    public function test_daily_notes_are_personal_and_date_bounded(): void
    {
        DailyNote::create(['user_id' => $this->staff->id, 'date' => '2026-09-24', 'content' => 'Staff private plan']);

        $this->actingAs($this->owner)->putJson(route('daily-notes.save'), ['content' => 'Owner plan', 'date' => '2026-09-24'])
            ->assertOk()->assertJsonPath('note.user_id', $this->owner->id);
        $this->assertSame('Staff private plan', DailyNote::where('user_id', $this->staff->id)->value('content'));

        // A tab left open past midnight may still save yesterday; nothing older or in the future.
        $this->actingAs($this->owner)->putJson(route('daily-notes.save'), ['content' => 'Yesterday', 'date' => '2026-09-23'])->assertOk();
        $this->actingAs($this->owner)->putJson(route('daily-notes.save'), ['content' => 'Old', 'date' => '2026-09-20'])->assertStatus(422);
        $this->actingAs($this->owner)->putJson(route('daily-notes.save'), ['content' => 'Future', 'date' => '2026-09-25'])->assertStatus(422);
        $this->assertSame(2, DailyNote::where('user_id', $this->owner->id)->count());

        $page = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk();
        $page->assertSee('Owner plan')
            ->assertDontSee('Staff private plan')
            ->assertDontSee('Yesterday')
            ->assertSee('@input.debounce.500ms', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee(route('daily-notes.save'), false);
    }

    // ---------------------------------------------------------------- page contract

    public function test_today_page_contract_and_work_mode(): void
    {
        $this->appointment($this->client('Contract Client'), '2026-09-24', '11:00:00');

        $html = $this->actingAs($this->owner)->withSession(['locale' => 'en'])->get(route('dashboard'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('Thursday, 24 September 2026', $html);
        $this->assertStringContainsString('data-card-primary-action="appointment_outcome"', $html);
        $this->assertStringContainsString('aria-describedby="work-item-apt-', $html);
        $this->assertStringNotContainsString('notify.today_board.', $html);

        $work = $this->actingAs($this->staff)->withSession(['locale' => 'en'])->get(route('dashboard', ['mode' => 'work', 'scope' => 'my']))->assertOk();
        $this->assertMatchesRegularExpression('/data-today-tab="work"\s+aria-current="page"/', $work->getContent());
        $work->assertSee('Open Work')
            ->assertSee('data-today-tab="work"', false)
            ->assertDontSee('data-daily-notes', false);
    }

    // ---------------------------------------------------------------- performance

    public function test_today_query_count_does_not_grow_with_the_queue(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->owner)->get(route('dashboard'))->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $seed = function (int $n): void {
            for ($i = 0; $i < $n; $i++) {
                $client = $this->client('Load '.$i.'-'.random_int(1, 1_000_000), ['primary_owner_id' => $this->staff->id]);
                $this->appointment($client, '2026-09-24', '11:00:00', [], [$this->staff->id]);
                $this->followUp($client, '2026-09-24 12:00:00');
                ClientReviewItem::create(['client_id' => $client->id, 'type' => ClientReviewItem::TYPE_NOT_INTERESTED, 'status' => ClientReviewItem::STATUS_PENDING, 'created_by' => $this->staff->id]);
                $payer = $this->subscriber('Load payer '.$i.'-'.random_int(1, 1_000_000), ['primary_owner_id' => $this->staff->id]);
                $this->invoice($payer, '2026-09-20');
            }
        };

        $seed(2);
        $small = $count();
        $seed(8);
        $large = $count();

        $this->assertLessThanOrEqual($small, $large, "Today queries grew from {$small} to {$large} (N+1)");
    }
}
