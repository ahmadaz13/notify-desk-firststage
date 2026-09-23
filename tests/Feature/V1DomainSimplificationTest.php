<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionMetricEvent;
use App\Models\User;
use App\Services\SubscriptionBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class V1DomainSimplificationTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;
    private Client $client;
    private Product $smartLink;
    private Product $autoSms;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->founder = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true]);
        $this->client = Client::create([
            'business_name' => 'عميل V1', 'phone' => '0790000000', 'city_area' => 'عمان',
            'business_category' => 'تجاري', 'lead_source' => 'Referral', 'status' => 'prospect',
            'stage' => 'prospect', 'created_by' => $this->founder->id,
        ]);
        $this->smartLink = Product::create(['code' => 'smart-link', 'name_ar' => 'الرابط الذكي', 'name_en' => 'Smart Link', 'is_active' => true]);
        $this->autoSms = Product::create(['code' => 'auto-sms', 'name_ar' => 'الرسائل التلقائية', 'name_en' => 'Auto SMS', 'is_active' => true]);
    }

    public function test_system_code_is_generated_and_never_required_from_user(): void
    {
        $this->actingAs($this->founder)->post(route('commercial-catalog.products.store'), [
            'name_ar' => 'المتجر الإلكتروني', 'name_en' => 'E Store',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['name_en' => 'E Store', 'code' => 'e-store']);
    }

    public function test_referral_is_optional_metadata_and_only_owner_can_change_commission(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true]);
        $payload = $this->clientPayload() + ['referred_by_name' => 'سارة', 'referral_note' => 'إحالة معرض'];
        $this->actingAs($staff)->post(route('clients.store'), $payload)->assertSessionHasNoErrors();
        $created = Client::where('business_name', $payload['business_name'])->firstOrFail();
        $this->assertNull($created->referral_commission_bps);

        $this->actingAs($this->founder)->put(route('clients.update', $created), $payload + [
            'referral_commission_percentage' => '12.50',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1250, $created->fresh()->referral_commission_bps);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_free_system_access_creates_no_paid_or_financial_record(): void
    {
        $this->actingAs($this->founder)->post(route('clients.system-access.store', $this->client), [
            'system_ids' => [$this->smartLink->id],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('client_system', ['client_id' => $this->client->id, 'product_id' => $this->smartLink->id, 'access_type' => 'free']);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('subscription_metric_events', 0);
    }

    public function test_monthly_agreed_subscription_creates_invoice_metric_and_contract(): void
    {
        $this->convert(['system_ids' => [$this->smartLink->id, $this->autoSms->id], 'billing_interval' => 'monthly', 'agreed_value_jod' => '125.750']);
        $subscription = Subscription::with(['systems', 'contract'])->sole();

        $this->assertSame('v1_simple', $subscription->billing_engine_version);
        $this->assertSame(125750, $subscription->agreed_value_minor);
        $this->assertCount(2, $subscription->systems);
        $this->assertNotNull($subscription->contract);
        $this->assertSame(125750, Invoice::sole()->total_minor);
        $this->assertSame(1509000, SubscriptionMetricEvent::sole()->arr_after_minor);
        $this->assertSame(collect([$this->autoSms->id, $this->smartLink->id])->sort()->values()->all(), $subscription->contract->snapshot_data['systems'] ? collect($subscription->contract->snapshot_data['systems'])->pluck('id')->sort()->values()->all() : []);
    }

    public function test_annual_full_and_installment_flows_have_identical_economics_and_contract_endpoints(): void
    {
        $this->convert(['system_ids' => [$this->smartLink->id], 'billing_interval' => 'annual', 'agreed_value_jod' => '1200.000', 'payment_terms' => 'full']);
        $full = Subscription::with('contract')->firstOrFail();
        $this->assertSame(1200000, $full->agreed_value_minor);
        $this->assertDatabaseCount('payment_schedules', 0);

        $other = Client::create($this->clientPayload() + ['business_name' => 'عميل أقساط']);
        $this->client = $other;
        $this->convert(['system_ids' => [$this->smartLink->id], 'billing_interval' => 'annual', 'agreed_value_jod' => '1200.000', 'payment_terms' => 'installments', 'installments_count' => 4, 'installment_due_day' => 15]);
        $installment = Subscription::with('contract')->latest('id')->firstOrFail();
        $this->assertSame($full->agreed_value_minor, $installment->agreed_value_minor);
        $this->assertSame(1200000, (int) DB::table('payment_schedules')->where('subscription_id', $installment->id)->sum('amount_due_minor'));
        $this->assertSame($full->metricEvents()->firstOrFail()->arr_after_minor, $installment->metricEvents()->firstOrFail()->arr_after_minor);

        $contract = $installment->contract;
        $this->actingAs($this->founder)->get(route('contracts.preview', $contract))->assertOk();
        $this->actingAs($this->founder)->get(route('contracts.print', $contract))->assertOk();
        $this->actingAs($this->founder)->get(route('contracts.download-pdf', $contract))->assertOk();
    }

    public function test_agreed_value_subscription_renews_with_the_same_systems_and_value(): void
    {
        $this->convert(['system_ids' => [$this->smartLink->id], 'billing_interval' => 'monthly', 'agreed_value_jod' => '75.250']);
        $subscription = Subscription::sole();

        $result = app(SubscriptionBillingService::class)->generateRenewals('2026-10-23', false, $this->founder->id);

        $this->assertSame(1, $result['renewed']);
        $this->assertDatabaseCount('invoices', 2);
        $this->assertSame([75250, 75250], Invoice::orderBy('id')->pluck('total_minor')->map(fn ($value) => (int) $value)->all());
        $this->assertSame('2026-11-23', $subscription->fresh()->next_billing_date->toDateString());
        $this->assertDatabaseCount('subscription_billing_periods', 2);
    }

    public function test_agreed_value_subscription_can_be_cancelled_at_period_end(): void
    {
        $this->convert(['system_ids' => [$this->smartLink->id], 'billing_interval' => 'monthly', 'agreed_value_jod' => '75.250']);
        $subscription = Subscription::sole();

        $this->actingAs($this->founder)->post(route('subscriptions.cancel', $subscription), [
            'cancellation_reason' => 'Customer request',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($subscription->fresh()->cancel_at_period_end);

        $result = app(SubscriptionBillingService::class)->generateRenewals('2026-10-22', false, $this->founder->id);
        $this->assertSame(1, $result['cancelled']);
        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertDatabaseCount('invoices', 1);
    }

    private function convert(array $overrides): void
    {
        $this->actingAs($this->founder)->post(route('clients.guided-subscription.store', $this->client), $overrides + [
            '_idempotency_key' => (string) Str::uuid(),
            'start_date' => '2026-09-23',
        ])->assertSessionHasNoErrors();
    }

    private function clientPayload(): array
    {
        return [
            'business_name' => 'عميل إحالة جديد', 'phone' => '0791111111', 'primary_phone_type' => 'business', 'city_area' => 'عمان',
            'business_category' => 'تجاري', 'lead_source' => 'Referral', 'number_of_branches' => 1,
        ];
    }
}
