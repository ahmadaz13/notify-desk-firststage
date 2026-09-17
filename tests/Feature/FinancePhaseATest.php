<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ExpenseCategory;
use App\Models\Partner;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ClientLifecycle;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancePhaseATest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    public function test_payment_cannot_create_subscription(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient(['status' => 'prospect', 'stage' => ClientLifecycle::PROSPECT]);

        $response = $this->actingAs($admin)->post(route('payments.store'), [
            'client_id' => $client->id,
            'amount' => '15.000',
            'payment_method' => 'cash',
            'paid_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(410);
        $this->assertDatabaseMissing('subscriptions', ['client_id' => $client->id]);
        $this->assertDatabaseMissing('payments', ['client_id' => $client->id]);
        $this->assertDatabaseMissing('activity_logs', [
            'client_id' => $client->id,
            'type' => 'payment_received',
        ]);
    }

    public function test_payment_cannot_change_prospect_to_subscriber(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient(['status' => 'prospect', 'stage' => ClientLifecycle::PROSPECT]);

        $this->actingAs($admin)->post(route('payments.store'), [
            'client_id' => $client->id,
            'amount' => '15.000',
            'payment_method' => 'cash',
            'paid_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $client->refresh();
        $this->assertSame('prospect', $client->status);
        $this->assertSame(ClientLifecycle::PROSPECT, $client->stage);
    }

    public function test_payment_cannot_change_installed_free_to_subscriber(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient([
            'status' => 'prospect',
            'stage' => ClientLifecycle::INSTALLED_FREE,
        ]);

        $this->actingAs($admin)->post(route('payments.store'), [
            'client_id' => $client->id,
            'amount' => '15.000',
            'payment_method' => 'cash',
            'paid_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $client->refresh();
        $this->assertSame('prospect', $client->status);
        $this->assertSame(ClientLifecycle::INSTALLED_FREE, $client->stage);
    }

    public function test_legacy_subscriber_payment_route_is_deprecated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient(['status' => 'subscriber', 'stage' => ClientLifecycle::SUBSCRIBER]);
        $subscription = $this->createSubscription($client, $admin);

        $response = $this->actingAs($admin)->post(route('payments.store'), [
            'client_id' => $client->id,
            'amount' => '50.000',
            'payment_method' => 'cliq',
            'paid_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(410);
        $this->assertDatabaseMissing('payments', [
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'payment_method' => 'cliq',
        ]);
        $this->assertDatabaseMissing('activity_logs', [
            'client_id' => $client->id,
            'type' => 'payment_received',
        ]);
    }

    public function test_partner_cannot_record_payment(): void
    {
        [$partner, $partnerUser] = $this->createPartnerUser();
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient([
            'partner_id' => $partner->id,
            'status' => 'subscriber',
            'stage' => ClientLifecycle::SUBSCRIBER,
        ]);
        $this->createSubscription($client, $admin);

        $response = $this->actingAs($partnerUser)->post(route('payments.store'), [
            'client_id' => $client->id,
            'amount' => '50.000',
            'payment_method' => 'cash',
            'paid_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('payments', ['client_id' => $client->id]);
        $this->assertDatabaseMissing('activity_logs', [
            'client_id' => $client->id,
            'type' => 'payment_received',
        ]);
    }

    public function test_partner_cannot_manage_subscription_billing(): void
    {
        [$partner, $partnerUser] = $this->createPartnerUser();
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient([
            'partner_id' => $partner->id,
            'status' => 'subscriber',
            'stage' => ClientLifecycle::SUBSCRIBER,
        ]);
        $subscription = $this->createSubscription($client, $admin);

        $convertResponse = $this->actingAs($partnerUser)->post(route('clients.convert', $client->id), [
            'billing_type' => 'monthly',
            'start_date' => now()->toDateString(),
        ]);

        $cancelResponse = $this->actingAs($partnerUser)->post(route('subscriptions.cancel', $subscription->id), [
            'cancellation_reason' => 'not allowed',
        ]);

        $convertResponse->assertForbidden();
        $cancelResponse->assertForbidden();
    }

    public function test_partner_cannot_create_expenses(): void
    {
        [, $partnerUser] = $this->createPartnerUser();
        $category = ExpenseCategory::create([
            'key' => 'other',
            'name' => 'أخرى',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($partnerUser)->post(route('expenses.store'), [
            'amount' => '10.000',
            'category_id' => $category->id,
            'date' => now()->toDateString(),
            'visibility' => 'shared',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('expenses', ['amount' => '10.000']);
    }

    public function test_partner_cannot_access_financial_reports_or_settings_mutation(): void
    {
        [, $partnerUser] = $this->createPartnerUser();

        $this->actingAs($partnerUser)->get(route('settings.export'))->assertForbidden();
        $this->actingAs($partnerUser)->post(route('settings.update'), [
            'operational_cost_percentage' => 20,
            'market_valuation_multiplier' => 5,
        ])->assertForbidden();
    }

    public function test_partner_cannot_access_investments_or_capital_expenses(): void
    {
        [, $partnerUser] = $this->createPartnerUser();

        $this->actingAs($partnerUser)->post(route('investments.store'), [
            'investor_name' => 'Blocked investor',
            'amount' => '100.000',
            'entry_date' => now()->toDateString(),
        ])->assertForbidden();

        $this->actingAs($partnerUser)->post(route('capital-expenses.store'), [
            'description' => 'Blocked capex',
            'amount' => '100.000',
            'expense_date' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_internal_admin_retains_approved_financial_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient(['status' => 'subscriber', 'stage' => ClientLifecycle::SUBSCRIBER]);
        $this->createSubscription($client, $admin);
        $category = ExpenseCategory::create([
            'key' => 'other',
            'name' => 'أخرى',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('payments.store'), [
            'client_id' => $client->id,
            'amount' => '50.000',
            'payment_method' => 'zain_cash',
            'paid_at' => now()->format('Y-m-d H:i:s'),
        ])->assertStatus(410);

        $this->actingAs($admin)->post(route('expenses.store'), [
            'amount' => '10.000',
            'category_id' => $category->id,
            'date' => now()->toDateString(),
            'visibility' => 'shared',
        ])->assertStatus(410);

        $this->actingAs($admin)->get(route('settings.export'))->assertOk();
    }

    private function createClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'Phase A Client',
            'phone' => '0790000000',
            'city_area' => 'Amman',
            'business_category' => 'Retail',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ], $overrides));
    }

    private function createSubscription(Client $client, User $user): Subscription
    {
        return Subscription::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'billing_type' => 'monthly',
            'total_price' => 50.000,
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);
    }

    private function createPartnerUser(): array
    {
        $partner = Partner::create([
            'company_name' => 'Phase A Partner',
            'email' => 'phase-a-partner@example.com',
        ]);

        $user = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        return [$partner, $user];
    }
}
