<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ClientOperationalWorkflowService;
use App\Services\SubscriptionBillingService;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * P3 — former_subscriber lifecycle (§4, FROZEN D-03) and the Staff-started subscription
 * notification (§7, FROZEN D-06).
 */
class V1P3LifecycleAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;
    private User $admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-24 10:00:00');
        $this->seed(SettingsSeeder::class);
        $this->founder = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Lifecycle ───────────────────────────────────────────────────────

    public function test_former_subscriber_is_a_lifecycle_stage_with_localized_labels(): void
    {
        $this->assertContains(ClientLifecycle::FORMER_SUBSCRIBER, ClientLifecycle::STAGES);
        $this->assertSame('بانتظار التجديد / مشترك سابق', ClientLifecycle::label('former_subscriber'));
        $this->assertSame('Renewal / Former Subscriber', trans('notify.clients.stages.former_subscriber', [], 'en'));
        $this->assertSame('بانتظار التجديد / مشترك سابق', trans('notify.clients.stages.former_subscriber', [], 'ar'));
    }

    public function test_last_active_subscription_ending_moves_client_to_former_subscriber_not_closed(): void
    {
        $client = $this->client();
        $subscription = $this->startSubscription($client, $this->admin, 'monthly');
        $this->assertSame(ClientLifecycle::SUBSCRIBER, $client->fresh()->stage);

        $this->actingAs($this->admin)->post(route('subscriptions.cancel', $subscription), ['cancellation_reason' => 'Budget'])->assertSessionHas('success');
        $this->assertSame(ClientLifecycle::SUBSCRIBER, $client->fresh()->stage, 'Scheduling a cancellation does not end the subscription.');

        $counts = app(SubscriptionBillingService::class)->generateRenewals('2026-11-01', false, $this->admin->id);

        $this->assertSame(1, $counts['cancelled']);
        $this->assertSame('cancelled', $subscription->fresh()->status);
        $client->refresh();
        $this->assertSame(ClientLifecycle::FORMER_SUBSCRIBER, $client->stage);
        $this->assertNull($client->closed_at);
        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $client->id,
            'type' => 'client_former_subscriber',
            'description' => __('notify.clients.activity_former_subscriber'),
        ]);

        // Re-running the scheduler is a no-op.
        app(SubscriptionBillingService::class)->generateRenewals('2026-11-01', false, $this->admin->id);
        $this->assertSame(1, DB::table('activity_logs')->where('client_id', $client->id)->where('type', 'client_former_subscriber')->count());
    }

    public function test_client_stays_subscriber_while_another_paid_subscription_is_active(): void
    {
        $client = $this->client();
        [$first, $second] = Product::sellable()->orderBy('id')->take(2)->get();
        $ending = $this->startSubscription($client, $this->admin, 'monthly', [$first->id]);
        $this->startSubscription($client, $this->admin, 'annual', [$second->id]);

        app(SubscriptionBillingService::class)->scheduleCancellation($ending, 'Drop one system', $this->admin->id);
        app(SubscriptionBillingService::class)->generateRenewals('2026-11-01', false, $this->admin->id);

        $this->assertSame('cancelled', $ending->fresh()->status);
        $this->assertSame(ClientLifecycle::SUBSCRIBER, $client->fresh()->stage);
    }

    public function test_closed_client_stays_closed_when_subscription_ends(): void
    {
        $client = $this->client();
        $subscription = $this->startSubscription($client, $this->admin, 'monthly');
        app(SubscriptionBillingService::class)->scheduleCancellation($subscription, null, $this->admin->id);
        $client->update(['stage' => ClientLifecycle::CLOSED, 'status' => 'archived', 'closed_at' => now()]);

        app(SubscriptionBillingService::class)->generateRenewals('2026-11-01', false, $this->admin->id);

        $this->assertSame(ClientLifecycle::CLOSED, $client->fresh()->stage);
    }

    public function test_new_paid_subscription_returns_former_subscriber_to_subscriber(): void
    {
        $client = $this->client();
        $subscription = $this->startSubscription($client, $this->admin, 'monthly');
        app(SubscriptionBillingService::class)->scheduleCancellation($subscription, null, $this->admin->id);
        app(SubscriptionBillingService::class)->generateRenewals('2026-11-01', false, $this->admin->id);
        $this->assertSame(ClientLifecycle::FORMER_SUBSCRIBER, $client->fresh()->stage);

        $this->startSubscription($client->fresh(), $this->staff, 'annual');

        $this->assertSame(ClientLifecycle::SUBSCRIBER, $client->fresh()->stage);
        $this->assertSame(2, Subscription::where('client_id', $client->id)->count());
    }

    public function test_former_subscriber_cannot_be_set_manually(): void
    {
        $client = $this->client();

        $this->actingAs($this->admin)
            ->patch(route('clients.stage.update', $client), ['stage' => ClientLifecycle::FORMER_SUBSCRIBER])
            ->assertSessionHasErrors('stage');

        $this->expectException(ValidationException::class);
        app(ClientOperationalWorkflowService::class)->transition($client, ClientLifecycle::FORMER_SUBSCRIBER, $this->admin);
    }

    public function test_call_outcomes_do_not_pull_subscribers_or_former_subscribers_back_into_the_pipeline(): void
    {
        $workflow = app(ClientOperationalWorkflowService::class);

        foreach ([ClientLifecycle::SUBSCRIBER, ClientLifecycle::FORMER_SUBSCRIBER] as $stage) {
            $client = $this->client(['stage' => $stage, 'status' => ClientOperationalWorkflowService::legacyStatusFor($stage)]);
            $workflow->recordContactOutcome($client, $this->staff, ['result' => 'no_answer_busy', 'method' => 'call']);
            $this->assertSame($stage, $client->fresh()->stage, $stage);
        }

        $prospect = $this->client();
        $workflow->recordContactOutcome($prospect, $this->staff, ['result' => 'no_answer_busy', 'method' => 'call']);
        $this->assertSame(ClientLifecycle::CONTACTING, $prospect->fresh()->stage);
    }

    public function test_reopening_a_closed_client_with_subscription_history_returns_former_subscriber(): void
    {
        $workflow = app(ClientOperationalWorkflowService::class);
        $client = $this->client();
        $subscription = $this->startSubscription($client, $this->admin, 'monthly');
        app(SubscriptionBillingService::class)->scheduleCancellation($subscription, null, $this->admin->id);
        app(SubscriptionBillingService::class)->generateRenewals('2026-11-01', false, $this->admin->id);

        $workflow->closeClient($client->fresh(), $this->admin, 'other', 'Paused');
        $workflow->reopen($client->fresh(), $this->admin, ClientLifecycle::PROSPECT, 'Wants to renew');
        $this->assertSame(ClientLifecycle::FORMER_SUBSCRIBER, $client->fresh()->stage);

        $neverSubscribed = $this->client(['business_name' => 'Fresh lead']);
        $workflow->closeClient($neverSubscribed, $this->admin, 'other', 'Paused');
        $workflow->reopen($neverSubscribed->fresh(), $this->admin, ClientLifecycle::PROSPECT, 'Try again');
        $this->assertSame(ClientLifecycle::PROSPECT, $neverSubscribed->fresh()->stage);
    }

    // ── Staff-started subscription notification ─────────────────────────

    public function test_staff_started_paid_subscription_notifies_active_owner_level_users_once(): void
    {
        $inactiveAdmin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => false]);
        $client = $this->client(['business_name' => 'Staff Sale Cafe']);
        $system = Product::sellable()->firstOrFail();
        $payload = [
            '_idempotency_key' => 'staff-start-1',
            'system_ids' => [$system->id],
            'billing_interval' => 'annual',
            'agreed_value_jod' => '240.000',
            'start_date' => '2026-09-24',
        ];

        $this->actingAs($this->staff)->post(route('clients.guided-subscription.store', $client), $payload)->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->post(route('clients.guided-subscription.store', $client), $payload); // retry

        $subscription = Subscription::where('client_id', $client->id)->sole();
        $notifications = DB::table('notifications')->where('type', 'staff_subscription_started')->get();

        $this->assertEqualsCanonicalizing([$this->founder->id, $this->admin->id], $notifications->pluck('user_id')->map(fn ($id) => (int) $id)->all());
        $this->assertNotContains($inactiveAdmin->id, $notifications->pluck('user_id')->all());
        $this->assertTrue($notifications->every(fn ($n) => $n->source_type === 'subscription' && (int) $n->source_id === $subscription->id));
        $this->assertStringContainsString('Staff Sale Cafe', $notifications->first()->message);
        $this->assertStringContainsString('240.000', $notifications->first()->message);
        $this->assertStringContainsString($this->staff->name, $notifications->first()->message);
    }

    public function test_owner_started_subscription_sends_no_staff_notification(): void
    {
        $this->startSubscription($this->client(), $this->admin, 'monthly');

        $this->assertSame(0, DB::table('notifications')->where('type', 'staff_subscription_started')->count());
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function client(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'P3 Client',
            'phone' => '0790000003',
            'city_area' => 'Amman',
            'business_category' => 'Cafe',
            'lead_source' => 'Referral',
            'status' => 'prospect',
            'stage' => ClientLifecycle::DECISION_PENDING,
        ], $overrides));
    }

    private function startSubscription(Client $client, User $actor, string $interval, ?array $systemIds = null): Subscription
    {
        $systems = Product::sellable()->whereIn('id', $systemIds ?? [Product::sellable()->orderBy('id')->value('id')])->get();

        [$subscription] = app(SubscriptionBillingService::class)->startAgreedSubscription($client, $systems, [
            'billing_interval' => $interval,
            'agreed_value_minor' => 100000,
            'start_date' => '2026-09-24',
            'payment_terms' => 'full',
        ], $actor->id);

        return $subscription;
    }
}
