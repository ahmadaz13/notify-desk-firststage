<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CommercialPricingService;
use App\Services\PlanPriceService;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Database\Seeders\CommercialCatalogSeeder;
use Database\Seeders\ServiceCatalogSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePhaseBTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->seed(ServiceCatalogSeeder::class);
        $this->seed(CommercialCatalogSeeder::class);
    }

    public function test_admin_can_create_plan_and_include_existing_services(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = Service::first();

        $this->actingAs($admin)
            ->get(route('commercial-catalog.index'))
            ->assertOk()
            ->assertSee('إدارة الباقات والأسعار');

        $response = $this->actingAs($admin)->post(route('commercial-catalog.plans.store'), [
            'code' => 'custom_growth',
            'name_ar' => 'باقة النمو',
            'name_en' => 'Growth',
            'services' => [$service->id],
        ]);

        $response->assertSessionHas('success');
        $plan = Plan::where('code', 'custom_growth')->first();
        $this->assertNotNull($plan);
        $this->assertDatabaseHas('plan_service', [
            'plan_id' => $plan->id,
            'service_id' => $service->id,
        ]);
        $this->assertDatabaseHas('activity_logs', ['type' => 'plan_created']);
    }

    public function test_partner_cannot_manage_plans(): void
    {
        [, $partnerUser] = $this->createPartnerUser();

        $this->actingAs($partnerUser)->get(route('commercial-catalog.index'))->assertForbidden();
        $this->actingAs($partnerUser)->post(route('commercial-catalog.plans.store'), [
            'code' => 'blocked',
            'name_ar' => 'مرفوض',
        ])->assertForbidden();
    }

    public function test_seeded_prices_store_exact_minor_units(): void
    {
        $packageOne = Plan::where('code', 'restaurant_package_1')->firstOrFail();
        $monthly = $packageOne->prices()->where('billing_interval', PlanPrice::MONTHLY)->first();
        $annual = $packageOne->prices()->where('billing_interval', PlanPrice::ANNUAL)->first();

        $this->assertSame(15000, $monthly->amount_minor);
        $this->assertSame(144000, $annual->amount_minor);
    }

    public function test_new_price_version_does_not_alter_historical_price_and_overlap_is_prevented(): void
    {
        Carbon::setTestNow('2026-09-14 10:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $plan = Plan::where('code', 'restaurant_package_2')->firstOrFail();
        $oldPrice = $plan->prices()->where('billing_interval', PlanPrice::MONTHLY)->where('is_active', true)->firstOrFail();

        $response = $this->actingAs($admin)->post(route('commercial-catalog.prices.store', $plan), [
            'billing_interval' => PlanPrice::MONTHLY,
            'amount_jod' => '30.000',
            'setup_fee_jod' => '0.000',
            'included_branch_quantity' => 1,
            'effective_from' => '2026-10-01 00:00:00',
        ]);

        $response->assertSessionHas('success');
        $oldPrice->refresh();
        $newPrice = $plan->prices()->where('billing_interval', PlanPrice::MONTHLY)->where('is_active', true)->firstOrFail();

        $this->assertSame(25000, $oldPrice->amount_minor);
        $this->assertFalse($oldPrice->is_active);
        $this->assertSame(30000, $newPrice->amount_minor);

        $overlap = $this->actingAs($admin)->post(route('commercial-catalog.prices.store', $plan), [
            'billing_interval' => PlanPrice::MONTHLY,
            'amount_jod' => '31.000',
            'included_branch_quantity' => 1,
            'effective_from' => '2026-09-20 00:00:00',
        ]);

        $overlap->assertSessionHasErrors('effective_from');
    }

    public function test_v2_monthly_subscription_snapshots_price_and_creates_first_period_invoice_only(): void
    {
        Carbon::setTestNow('2026-09-14 12:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient(['stage' => ClientLifecycle::INSTALLED_FREE, 'status' => 'prospect']);
        $price = $this->createPlanWithPrice('snapshot_monthly', '15.000', PlanPrice::MONTHLY);

        $response = $this->actingAs($admin)->post(route('clients.paid-subscriptions.store', $client), [
            'plan_price_id' => $price->id,
            'quantity' => 1,
            'start_date' => '2026-09-14',
        ]);

        $response->assertSessionHas('success');
        $subscription = Subscription::where('client_id', $client->id)->where('billing_engine_version', 'v2')->firstOrFail();
        $invoice = Invoice::where('subscription_id', $subscription->id)->firstOrFail();

        $this->assertSame('snapshot_monthly', $subscription->plan_code_snapshot);
        $this->assertSame(15000, $subscription->unit_price_minor);
        $this->assertSame(15000, $subscription->total_minor);
        $this->assertSame('2026-09-14', $subscription->current_period_start->toDateString());
        $this->assertSame('2026-10-13', $subscription->current_period_end->toDateString());
        $this->assertSame('2026-10-14', $subscription->next_billing_date->toDateString());
        $this->assertSame(15000, $invoice->total_minor);
        $this->assertSame(1, $invoice->lines()->where('line_type', InvoiceLine::TYPE_SUBSCRIPTION)->count());
        $this->assertSame(0, Invoice::where('subscription_id', $subscription->id)->where('id', '!=', $invoice->id)->count());
        $this->assertDatabaseMissing('payments', ['client_id' => $client->id]);

        $client->refresh();
        $this->assertSame('subscriber', $client->status);
        $this->assertSame(ClientLifecycle::SUBSCRIBER, $client->stage);
    }

    public function test_annual_subscription_creates_one_annual_invoice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $price = $this->createPlanWithPrice('snapshot_annual', '144.000', PlanPrice::ANNUAL);

        $this->actingAs($admin)->post(route('clients.paid-subscriptions.store', $client), [
            'plan_price_id' => $price->id,
            'quantity' => 1,
            'start_date' => '2026-01-01',
        ])->assertSessionHas('success');

        $subscription = Subscription::where('client_id', $client->id)->where('billing_engine_version', 'v2')->firstOrFail();
        $invoice = Invoice::where('subscription_id', $subscription->id)->firstOrFail();

        $this->assertSame(144000, $invoice->total_minor);
        $this->assertSame('2026-01-01', $invoice->billing_period_start->toDateString());
        $this->assertSame('2026-12-31', $invoice->billing_period_end->toDateString());
        $this->assertSame(1, Invoice::where('subscription_id', $subscription->id)->count());
    }

    public function test_branch_setup_discount_and_tax_arithmetic_is_exact(): void
    {
        $plan = Plan::create(['code' => 'exact_math', 'name_ar' => 'حساب دقيق', 'is_active' => true]);
        $price = PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => PlanPrice::MONTHLY,
            'currency' => 'JOD',
            'amount_minor' => 15000,
            'setup_fee_minor' => 5000,
            'included_branch_quantity' => 1,
            'additional_branch_price_minor' => 3000,
            'default_tax_rate_bps' => 1000,
            'effective_from' => now()->subMinute(),
            'is_active' => true,
        ]);
        $price->load('plan');

        $pricing = app(CommercialPricingService::class)->calculateSubscription($price, 3, '1.000');

        $this->assertSame(26000, $pricing['subtotal_minor']);
        $this->assertSame(1000, $pricing['discount_minor']);
        $this->assertSame(2500, $pricing['tax_minor']);
        $this->assertSame(27500, $pricing['total_minor']);
    }

    public function test_changing_current_plan_price_does_not_change_existing_subscription_or_invoice_snapshot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $price = $this->createPlanWithPrice('immutable_snapshot', '25.000', PlanPrice::MONTHLY);

        $this->actingAs($admin)->post(route('clients.paid-subscriptions.store', $client), [
            'plan_price_id' => $price->id,
            'quantity' => 1,
            'start_date' => now()->toDateString(),
        ])->assertSessionHas('success');

        $subscription = Subscription::where('client_id', $client->id)->where('billing_engine_version', 'v2')->firstOrFail();
        $invoice = Invoice::where('subscription_id', $subscription->id)->firstOrFail();

        app(PlanPriceService::class)->createVersion($price->plan, [
            'billing_interval' => PlanPrice::MONTHLY,
            'amount_jod' => '35.000',
            'included_branch_quantity' => 1,
            'effective_from' => now()->addDay()->format('Y-m-d H:i:s'),
        ], $admin->id);

        $subscription->refresh();
        $invoice->refresh();

        $this->assertSame(25000, $subscription->unit_price_minor);
        $this->assertSame(25000, $subscription->total_minor);
        $this->assertSame(25000, $invoice->total_minor);
    }

    public function test_archived_plan_remains_readable_historically_and_snapshot_survives_name_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $price = $this->createPlanWithPrice('archive_snapshot', '40.000', PlanPrice::MONTHLY);

        $this->actingAs($admin)->post(route('clients.paid-subscriptions.store', $client), [
            'plan_price_id' => $price->id,
            'quantity' => 1,
            'start_date' => now()->toDateString(),
        ])->assertSessionHas('success');

        $subscription = Subscription::where('client_id', $client->id)->where('billing_engine_version', 'v2')->firstOrFail();
        $line = InvoiceLine::whereHas('invoice', fn ($query) => $query->where('subscription_id', $subscription->id))->firstOrFail();

        $price->plan->update(['name_ar' => 'اسم جديد', 'is_active' => false, 'archived_at' => now()]);

        $subscription->refresh();
        $line->refresh();

        $this->assertSame('archive_snapshot', $subscription->plan_code_snapshot);
        $this->assertNotSame('اسم جديد', $subscription->plan_name_snapshot);
        $this->assertStringContainsString($subscription->plan_name_snapshot, $line->description_snapshot);
    }

    public function test_invoice_number_is_unique_and_totals_match_lines(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $clientA = $this->createClient(['business_name' => 'A']);
        $clientB = $this->createClient(['business_name' => 'B', 'phone' => '0791111111']);
        $price = $this->createPlanWithPrice('unique_invoice', '15.000', PlanPrice::MONTHLY);

        foreach ([$clientA, $clientB] as $client) {
            $this->actingAs($admin)->post(route('clients.paid-subscriptions.store', $client), [
                'plan_price_id' => $price->id,
                'quantity' => 1,
                'start_date' => now()->toDateString(),
            ])->assertSessionHas('success');
        }

        $numbers = Invoice::pluck('invoice_number')->all();
        $this->assertCount(2, array_unique($numbers));

        Invoice::with('lines')->get()->each(function (Invoice $invoice) {
            $this->assertSame($invoice->total_minor, $invoice->lines->sum('total_minor'));
            $this->assertSame($invoice->subtotal_minor, $invoice->lines->sum('subtotal_minor'));
        });
    }

    public function test_one_time_invoice_can_be_created_without_subscription_or_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();

        $response = $this->actingAs($admin)->post(route('clients.one-time-invoices.store', $client), [
            'issue_date' => '2026-09-14',
            'due_date' => '2026-09-14',
            'description' => 'Additional work',
            'lines' => [
                [
                    'line_type' => InvoiceLine::TYPE_ONE_TIME_SERVICE,
                    'description' => 'Website',
                    'quantity' => 2,
                    'unit_price_jod' => '50.000',
                    'discount_jod' => '5.000',
                    'tax_rate_bps' => 1600,
                ],
                [
                    'line_type' => InvoiceLine::TYPE_CUSTOM,
                    'description' => 'Training',
                    'quantity' => 1,
                    'unit_price_jod' => '20.000',
                ],
            ],
        ]);

        $response->assertSessionHas('success');
        $invoice = Invoice::where('client_id', $client->id)->firstOrFail();

        $this->assertNull($invoice->subscription_id);
        $this->assertSame(120000, $invoice->subtotal_minor);
        $this->assertSame(5000, $invoice->discount_minor);
        $this->assertSame(15200, $invoice->tax_minor);
        $this->assertSame(130200, $invoice->total_minor);
        $this->assertSame(2, $invoice->lines()->count());
        $this->assertDatabaseMissing('subscriptions', ['client_id' => $client->id]);
        $this->assertDatabaseMissing('payments', ['client_id' => $client->id]);
    }

    public function test_partner_cannot_create_one_time_invoice_or_start_paid_subscription(): void
    {
        [$partner, $partnerUser] = $this->createPartnerUser();
        $client = $this->createClient(['partner_id' => $partner->id]);
        $price = $this->createPlanWithPrice('partner_blocked', '15.000', PlanPrice::MONTHLY);

        $this->actingAs($partnerUser)->post(route('clients.paid-subscriptions.store', $client), [
            'plan_price_id' => $price->id,
            'quantity' => 1,
            'start_date' => now()->toDateString(),
        ])->assertForbidden();

        $this->actingAs($partnerUser)->post(route('clients.one-time-invoices.store', $client), [
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'lines' => [
                ['description' => 'Blocked', 'quantity' => 1, 'unit_price_jod' => '1.000'],
            ],
        ])->assertForbidden();
    }

    public function test_void_invoice_requires_reason_and_preserves_lines(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $price = $this->createPlanWithPrice('void_invoice', '15.000', PlanPrice::MONTHLY);

        $this->actingAs($admin)->post(route('clients.paid-subscriptions.store', $client), [
            'plan_price_id' => $price->id,
            'quantity' => 1,
            'start_date' => now()->toDateString(),
        ])->assertSessionHas('success');

        $invoice = Invoice::firstOrFail();
        $lineId = $invoice->lines()->firstOrFail()->id;

        $this->actingAs($admin)->post(route('invoices.void', $invoice), [])->assertSessionHasErrors('void_reason');
        $this->actingAs($admin)->post(route('invoices.void', $invoice), [
            'void_reason' => 'Commercial correction',
        ])->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_VOIDED, $invoice->status);
        $this->assertDatabaseHas('invoice_lines', ['id' => $lineId]);
        $this->assertDatabaseHas('activity_logs', ['type' => 'invoice_voided']);
    }

    public function test_issued_invoice_cannot_be_hard_deleted_through_normal_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $price = $this->createPlanWithPrice('no_delete_invoice', '15.000', PlanPrice::MONTHLY);

        $this->actingAs($admin)->post(route('clients.paid-subscriptions.store', $client), [
            'plan_price_id' => $price->id,
            'quantity' => 1,
            'start_date' => now()->toDateString(),
        ])->assertSessionHas('success');

        $invoice = Invoice::firstOrFail();
        $this->actingAs($admin)->delete('/invoices/'.$invoice->id)->assertNotFound();
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => Invoice::STATUS_ISSUED]);
    }

    public function test_legacy_subscriptions_and_contracts_remain_readable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient(['status' => 'subscriber', 'stage' => ClientLifecycle::SUBSCRIBER]);
        $subscription = Subscription::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => 50.000,
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);
        Contract::create([
            'contract_number' => 'ND-2026-0001',
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'generated_by' => $admin->id,
            'template_version' => '1.0',
            'status' => 'draft',
            'legal_review_status' => 'pending',
            'snapshot_data' => ['legacy' => true],
        ]);

        $this->actingAs($admin)->get(route('clients.show', $client))->assertOk()->assertSee('ND-2026-0001');
    }

    private function createClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'Phase B Client',
            'phone' => '0790000000',
            'city_area' => 'Amman',
            'business_category' => 'Restaurant',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ], $overrides));
    }

    private function createPlanWithPrice(string $code, string $amountJod, string $interval): PlanPrice
    {
        $plan = Plan::create([
            'code' => $code,
            'name_ar' => 'باقة '.$code,
            'is_active' => true,
        ]);

        return PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => $interval,
            'currency' => 'JOD',
            'amount_minor' => \App\Support\Money::fromJod($amountJod)->minorUnits(),
            'setup_fee_minor' => 0,
            'included_branch_quantity' => 1,
            'effective_from' => now()->subMinute(),
            'is_active' => true,
        ])->load('plan');
    }

    private function createPartnerUser(): array
    {
        $partner = Partner::create([
            'company_name' => 'Phase B Partner',
            'email' => 'phase-b-partner@example.com',
        ]);

        $user = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        return [$partner, $user];
    }
}
