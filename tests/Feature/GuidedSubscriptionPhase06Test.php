<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Product;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ContractPdfService;
use App\Services\ContractService;
use App\Support\ClientLifecycle;
use App\Support\FinancialPermissions;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GuidedSubscriptionPhase06Test extends TestCase
{
    use RefreshDatabase;

    protected User $founder;
    protected User $staff;
    protected Client $client;
    protected Product $productA;
    protected Product $productB;
    protected Plan $planA1;
    protected Plan $planA2;
    protected Plan $planB1;
    protected PlanPrice $monthlyPriceA1;
    protected PlanPrice $annualPriceA1;
    protected PlanPrice $monthlyPriceB1;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(SettingsSeeder::class);

        $this->founder = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => true,
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);

        $this->client = Client::create([
            'business_name' => 'مطعم الأصالة والضيافة',
            'contact_person' => 'أحمد العلي',
            'phone' => '0791112233',
            'city_area' => 'عمان - الجبيهة',
            'business_category' => 'مطاعم',
            'lead_source' => 'direct',
            'number_of_branches' => 3,
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
            'created_by' => $this->founder->id,
        ]);

        // Product A: Restaurant System
        $this->productA = Product::firstOrCreate(
            ['code' => 'restaurant_system'],
            [
                'name_ar' => 'نظام المطاعم',
                'name_en' => 'Restaurant System',
                'is_active' => true,
                'created_by' => $this->founder->id,
            ]
        );
        $this->productA->update(['is_active' => true]);

        // Plan A1 (Tier 1)
        $this->planA1 = Plan::firstOrCreate(
            ['code' => 'restaurant_basic'],
            [
                'product_id' => $this->productA->id,
                'name_ar' => 'باقة المطاعم الأساسية',
                'name_en' => 'Restaurant Basic Plan',
                'tier' => 1,
                'offer_type' => 'package',
                'is_active' => true,
                'created_by' => $this->founder->id,
            ]
        );
        $this->planA1->update(['product_id' => $this->productA->id, 'is_active' => true]);

        // Plan A2 (Tier 2)
        $this->planA2 = Plan::firstOrCreate(
            ['code' => 'restaurant_pro'],
            [
                'product_id' => $this->productA->id,
                'name_ar' => 'باقة المطاعم المتقدمة',
                'name_en' => 'Restaurant Pro Plan',
                'tier' => 2,
                'offer_type' => 'package',
                'is_active' => true,
                'created_by' => $this->founder->id,
            ]
        );
        $this->planA2->update(['product_id' => $this->productA->id, 'is_active' => true]);

        // Product B: Auto SMS System
        $this->productB = Product::firstOrCreate(
            ['code' => 'auto_sms_system'],
            [
                'name_ar' => 'نظام الرسائل النصية',
                'name_en' => 'Auto SMS System',
                'is_active' => true,
                'created_by' => $this->founder->id,
            ]
        );
        $this->productB->update(['is_active' => true]);

        $this->planB1 = Plan::firstOrCreate(
            ['code' => 'sms_standard'],
            [
                'product_id' => $this->productB->id,
                'name_ar' => 'باقة الرسائل القياسية',
                'name_en' => 'Standard SMS Plan',
                'tier' => 1,
                'offer_type' => 'package',
                'is_active' => true,
                'created_by' => $this->founder->id,
            ]
        );
        $this->planB1->update(['product_id' => $this->productB->id, 'is_active' => true]);

        // Services attached to Plan A1
        $service = Service::create([
            'key' => 'kitchen_display',
            'name_ar' => 'شاشة عرض المطبخ KDS',
            'name_en' => 'Kitchen Display System',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $this->planA1->services()->attach($service->id, ['sort_order' => 10, 'notes' => 'خدمة قياسية للمطاعم']);

        // Prices for Plan A1
        // Monthly: base 100 JOD (100,000 minor), setup fee 20 JOD (20,000 minor), included branches: 1, extra branch: 15 JOD (15,000 minor), tax: 16% (1600 bps)
        $this->monthlyPriceA1 = PlanPrice::create([
            'plan_id' => $this->planA1->id,
            'billing_interval' => PlanPrice::MONTHLY,
            'currency' => PlanPrice::CURRENCY,
            'amount_minor' => 100000,
            'setup_fee_minor' => 20000,
            'included_branch_quantity' => 1,
            'additional_branch_price_minor' => 15000,
            'default_tax_rate_bps' => 1600,
            'effective_from' => now()->subDays(5),
            'is_active' => true,
            'created_by' => $this->founder->id,
        ]);

        // Annual: base 1000 JOD (1,000,000 minor), setup fee 0, included branches: 1, extra branch: 120 JOD (120,000 minor), tax: 16%
        $this->annualPriceA1 = PlanPrice::create([
            'plan_id' => $this->planA1->id,
            'billing_interval' => PlanPrice::ANNUAL,
            'currency' => PlanPrice::CURRENCY,
            'amount_minor' => 1000000,
            'setup_fee_minor' => 0,
            'included_branch_quantity' => 1,
            'additional_branch_price_minor' => 120000,
            'default_tax_rate_bps' => 1600,
            'effective_from' => now()->subDays(5),
            'is_active' => true,
            'created_by' => $this->founder->id,
        ]);

        // Price for Plan B1 (Monthly only)
        $this->monthlyPriceB1 = PlanPrice::create([
            'plan_id' => $this->planB1->id,
            'billing_interval' => PlanPrice::MONTHLY,
            'currency' => PlanPrice::CURRENCY,
            'amount_minor' => 50000,
            'setup_fee_minor' => 0,
            'included_branch_quantity' => 1,
            'additional_branch_price_minor' => null,
            'default_tax_rate_bps' => 1600,
            'effective_from' => now()->subDays(5),
            'is_active' => true,
            'created_by' => $this->founder->id,
        ]);
    }

    /* ----------------------------------------------------------------------
     | 1. Catalog Selection & PlanPrice Resolution
     | ---------------------------------------------------------------------- */

    public function test_catalog_endpoint_returns_only_active_sellable_products_plans_and_available_intervals(): void
    {
        $response = $this->actingAs($this->founder)
            ->getJson(route('clients.guided-subscription.catalog', $this->client->id));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('client_branches', 3);

        $catalog = $response->json('catalog');
        $this->assertNotEmpty($catalog);

        // Product A has Plan A1 with both monthly and annual
        $prodA = collect($catalog)->firstWhere('code', 'restaurant_system');
        $this->assertNotNull($prodA);
        $planA1Data = collect($prodA['plans'])->firstWhere('code', 'restaurant_basic');
        $this->assertNotNull($planA1Data);
        $this->assertContains('monthly', $planA1Data['available_intervals']);
        $this->assertContains('annual', $planA1Data['available_intervals']);

        // Plan A2 has no prices attached, so it must NOT be offered
        $planA2Data = collect($prodA['plans'])->firstWhere('code', 'restaurant_pro');
        $this->assertNull($planA2Data);

        // Product B Plan B1 has only monthly
        $prodB = collect($catalog)->firstWhere('code', 'auto_sms_system');
        $this->assertNotNull($prodB);
        $planB1Data = collect($prodB['plans'])->firstWhere('code', 'sms_standard');
        $this->assertNotNull($planB1Data);
        $this->assertEquals(['monthly'], $planB1Data['available_intervals']);
    }

    public function test_guided_subscription_rejects_plan_not_belonging_to_product(): void
    {
        // Try selecting Plan B1 with Product A
        $response = $this->actingAs($this->founder)
            ->postJson(route('clients.guided-subscription.preview', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planB1->id,
                'billing_interval' => 'monthly',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_id']);
    }

    public function test_guided_subscription_rejects_billing_interval_without_effective_price(): void
    {
        // Plan B1 has only monthly price. Selecting annual must fail with price_unavailable validation error.
        $response = $this->actingAs($this->founder)
            ->postJson(route('clients.guided-subscription.preview', $this->client->id), [
                'product_id' => $this->productB->id,
                'plan_id' => $this->planB1->id,
                'billing_interval' => 'annual',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['billing_interval']);
    }

    public function test_raw_plan_price_id_is_ignored_and_cannot_bypass_server_resolution(): void
    {
        // Pass an inactive plan price ID in raw input; server must resolve solely from Product, Plan, and Interval
        $inactivePrice = PlanPrice::create([
            'plan_id' => $this->planA1->id,
            'billing_interval' => PlanPrice::MONTHLY,
            'currency' => PlanPrice::CURRENCY,
            'amount_minor' => 100, // Tampered cheap price
            'setup_fee_minor' => 0,
            'included_branch_quantity' => 1,
            'effective_from' => now()->subYear(),
            'is_active' => false, // Inactive!
            'created_by' => $this->founder->id,
        ]);

        $response = $this->actingAs($this->founder)
            ->postJson(route('clients.guided-subscription.preview', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'monthly',
                'plan_price_id' => $inactivePrice->id, // Attempting to bypass
            ]);

        $response->assertOk();
        // The authoritative price must be monthlyPriceA1 (100 JOD), NOT 1 JOD!
        $this->assertEquals('100.000', $response->json('unit_price_formatted'));
    }

    /* ----------------------------------------------------------------------
     | 2. Pricing Authority & Tamper Resistance
     | ---------------------------------------------------------------------- */

    public function test_browser_tampered_amounts_and_taxes_are_ignored(): void
    {
        // Attempting to send custom prices or taxes in confirmation POST
        $response = $this->actingAs($this->founder)
            ->post(route('clients.guided-subscription.store', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'monthly',
                'amount_minor' => 50,
                'tax_rate_bps' => 0,
                'total_minor' => 50,
                'quantity' => 999, // Tampered quantity
            ]);

        $response->assertRedirect(route('clients.show', $this->client->id));

        $subscription = Subscription::where('client_id', $this->client->id)->first();
        $this->assertNotNull($subscription);

        // Client has 3 branches. Base 100 JOD + (3 - 1) * 15 JOD = 130 JOD subtotal. Setup fee = 20 JOD.
        // Subtotal = 150 JOD (150,000 minor). Tax 16% = 24 JOD (24,000 minor). Total = 174 JOD (174,000 minor).
        $this->assertEquals(3, $subscription->quantity); // Strictly 3, not 999!
        $this->assertEquals(150000, $subscription->subtotal_minor);
        $this->assertEquals(1600, $subscription->tax_rate_bps);
        $this->assertEquals(24000, $subscription->tax_minor_v2);
        $this->assertEquals(174000, $subscription->total_minor);
    }

    public function test_same_product_subscription_conflict_is_rejected(): void
    {
        // First subscription to Product A succeeds
        $this->actingAs($this->founder)
            ->post(route('clients.guided-subscription.store', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'monthly',
            ])
            ->assertRedirect(route('clients.show', $this->client->id));

        $this->assertEquals(1, Subscription::where('client_id', $this->client->id)->count());

        // Attempting a second subscription to Product A for the same client must fail
        $response = $this->actingAs($this->founder)
            ->post(route('clients.guided-subscription.store', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'annual',
            ]);

        $response->assertSessionHasErrors('product_id');
        $this->assertEquals(1, Subscription::where('client_id', $this->client->id)->count());
    }

    /* ----------------------------------------------------------------------
     | 3. Monthly Subscription Flow
     | ---------------------------------------------------------------------- */

    public function test_monthly_subscription_creates_subscription_invoice_and_contract_without_payment(): void
    {
        $response = $this->actingAs($this->founder)
            ->post(route('clients.guided-subscription.store', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'monthly',
            ]);

        $response->assertRedirect(route('clients.show', $this->client->id));

        // 1. Subscription created
        $subscription = Subscription::where('client_id', $this->client->id)->first();
        $this->assertNotNull($subscription);
        $this->assertEquals('active', $subscription->status);
        $this->assertEquals('v2', $subscription->billing_engine_version);
        $this->assertEquals('monthly', $subscription->billing_interval_v2);

        // 2. Invoice created
        $invoice = Invoice::where('subscription_id', $subscription->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($subscription->total_minor, $invoice->total_minor);

        // 3. ZERO payments created automatically
        $this->assertEquals(0, Payment::where('client_id', $this->client->id)->count());

        // 4. Contract draft created automatically
        $contract = Contract::where('subscription_id', $subscription->id)->first();
        $this->assertNotNull($contract);
        $this->assertEquals('draft', $contract->status);
        $this->assertNotNull($contract->snapshot_data);
    }

    /* ----------------------------------------------------------------------
     | 4. Annual Full Flow
     | ---------------------------------------------------------------------- */

    public function test_annual_full_subscription_creates_one_annual_obligation_without_installment_schedule(): void
    {
        $response = $this->actingAs($this->founder)
            ->post(route('clients.guided-subscription.store', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'annual',
                'payment_terms' => 'full',
            ]);

        $response->assertRedirect(route('clients.show', $this->client->id));

        $subscription = Subscription::where('client_id', $this->client->id)->first();
        $this->assertNotNull($subscription);
        $this->assertEquals('annual', $subscription->billing_interval_v2);
        $this->assertEquals(1, $subscription->installments_count);

        // No payment schedule rows for full annual payment
        $schedulesCount = DB::table('payment_schedules')->where('subscription_id', $subscription->id)->count();
        $this->assertEquals(0, $schedulesCount);
    }

    /* ----------------------------------------------------------------------
     | 5. Annual Installments Flow
     | ---------------------------------------------------------------------- */

    public function test_annual_installments_creates_one_annual_subscription_with_matching_installment_schedule(): void
    {
        $response = $this->actingAs($this->founder)
            ->post(route('clients.guided-subscription.store', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'annual',
                'payment_terms' => 'installments',
                'installments_count' => 4,
                'installment_due_day' => 5,
            ]);

        $response->assertRedirect(route('clients.show', $this->client->id));

        // Exactly ONE subscription created
        $this->assertEquals(1, Subscription::where('client_id', $this->client->id)->count());
        $subscription = Subscription::where('client_id', $this->client->id)->first();
        $this->assertEquals('annual', $subscription->billing_interval_v2);
        $this->assertEquals(4, $subscription->installments_count);
        $this->assertEquals(5, $subscription->monthly_due_day);

        // Payment schedules exist and sum to the total invoice amount
        $schedules = DB::table('payment_schedules')->where('subscription_id', $subscription->id)->get();
        $this->assertCount(4, $schedules);
        $scheduleTotal = $schedules->sum('amount_due_minor');
        $this->assertEquals($subscription->total_minor, $scheduleTotal);
    }

    public function test_annual_installments_validates_due_day_and_count_boundaries(): void
    {
        // Invalid count 13
        $this->actingAs($this->founder)
            ->post(route('clients.guided-subscription.store', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'annual',
                'payment_terms' => 'installments',
                'installments_count' => 13,
                'installment_due_day' => 5,
            ])
            ->assertSessionHasErrors('installments_count');

        // Invalid due day 7
        $this->actingAs($this->founder)
            ->post(route('clients.guided-subscription.store', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'annual',
                'payment_terms' => 'installments',
                'installments_count' => 4,
                'installment_due_day' => 7,
            ])
            ->assertSessionHasErrors('installment_due_day');
    }

    /* ----------------------------------------------------------------------
     | 6. Quantity Policy
     | ---------------------------------------------------------------------- */

    public function test_quantity_is_derived_server_side_from_client_branches(): void
    {
        // Client has 3 branches
        $response = $this->actingAs($this->founder)
            ->postJson(route('clients.guided-subscription.preview', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'monthly',
                'quantity' => 1, // Browser attempt to pay for only 1 branch
            ]);

        $response->assertOk()
            ->assertJsonPath('quantity', 3)
            ->assertJsonPath('quantity_label', '3 فروع');
    }

    public function test_multi_branch_preview_confirmation_invoice_and_contract_snapshot_agree(): void
    {
        $preview = $this->actingAs($this->founder)
            ->postJson(route('clients.guided-subscription.preview', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'monthly',
                'quantity' => 1,
                'plan_price_id' => $this->monthlyPriceB1->id,
                'amount_minor' => 1,
                'tax_rate_bps' => 0,
                'total_minor' => 1,
            ]);

        $preview->assertOk()
            ->assertJsonPath('quantity', 3)
            ->assertJsonPath('unit_price_formatted', '100.000')
            ->assertJsonPath('setup_fee_formatted', '20.000')
            ->assertJsonPath('tax_formatted', '24.000')
            ->assertJsonPath('total_formatted', '174.000')
            ->assertJsonPath('total_minor', 174000);

        $this->actingAs($this->founder)
            ->post(route('clients.guided-subscription.store', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'monthly',
                'quantity' => 1,
                'plan_price_id' => $this->monthlyPriceB1->id,
                'amount_minor' => 1,
                'tax_rate_bps' => 0,
                'total_minor' => 1,
            ])
            ->assertRedirect(route('clients.show', $this->client->id));

        $subscription = Subscription::where('client_id', $this->client->id)->firstOrFail();
        $invoice = Invoice::where('subscription_id', $subscription->id)->firstOrFail();
        $contract = Contract::where('subscription_id', $subscription->id)->firstOrFail();

        $this->assertSame(3, (int) $subscription->quantity);
        $this->assertSame(150000, (int) $subscription->subtotal_minor);
        $this->assertSame(24000, (int) $subscription->tax_minor_v2);
        $this->assertSame(174000, (int) $subscription->total_minor);
        $this->assertSame(174000, (int) $invoice->total_minor);

        $line = $invoice->lines()->where('line_type', InvoiceLine::TYPE_SUBSCRIPTION)->firstOrFail();
        $this->assertSame(3, $line->metadata['branch_quantity']);
        $this->assertSame(1, $line->metadata['included_branch_quantity']);
        $this->assertSame(2, $line->metadata['extra_branch_quantity']);
        $this->assertSame(15000, $line->metadata['additional_branch_price_minor']);

        $snapshot = $contract->snapshot_data;
        $this->assertSame(3, $snapshot['pricing']['quantity']);
        $this->assertSame(150000, $snapshot['pricing']['subtotal_minor']);
        $this->assertSame(24000, $snapshot['pricing']['tax_minor']);
        $this->assertSame(174000, $snapshot['pricing']['total_minor']);
    }

    /* ----------------------------------------------------------------------
     | 7. Multi-Product Independence
     | ---------------------------------------------------------------------- */

    public function test_client_can_subscribe_to_two_different_products_independently(): void
    {
        // Subscribe to Product A
        $this->actingAs($this->founder)
            ->post(route('clients.guided-subscription.store', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'monthly',
            ])
            ->assertRedirect();

        // Subscribe to Product B (Different product!)
        $this->actingAs($this->founder)
            ->post(route('clients.guided-subscription.store', $this->client->id), [
                'product_id' => $this->productB->id,
                'plan_id' => $this->planB1->id,
                'billing_interval' => 'monthly',
            ])
            ->assertRedirect();

        $subscriptions = Subscription::where('client_id', $this->client->id)->get();
        $this->assertCount(2, $subscriptions);
        $this->assertEquals(2, Contract::where('client_id', $this->client->id)->count());
    }

    /* ----------------------------------------------------------------------
     | 8. Contract & PDF Generation
     | ---------------------------------------------------------------------- */

    public function test_contract_draft_contains_frozen_commercial_snapshot(): void
    {
        $this->actingAs($this->founder)
            ->post(route('clients.guided-subscription.store', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'monthly',
            ]);

        $contract = Contract::where('client_id', $this->client->id)->first();
        $this->assertNotNull($contract);
        $snapshot = $contract->snapshot_data;

        $this->assertEquals('restaurant_system', $snapshot['product']['code']);
        $this->assertEquals('restaurant_basic', $snapshot['package']['plan_code_snapshot']);
        $this->assertEquals(3, $snapshot['pricing']['quantity']);
        $this->assertEquals(1600, $snapshot['pricing']['tax_rate_bps']);
        $this->assertEquals('kitchen_display', $snapshot['services'][0]['code']);
    }

    public function test_contract_view_print_and_pdf_download_routes(): void
    {
        $this->actingAs($this->founder)
            ->post(route('clients.guided-subscription.store', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'monthly',
            ]);

        $contract = Contract::where('client_id', $this->client->id)->first();

        // 1. Preview HTML
        $this->actingAs($this->founder)
            ->get(route('contracts.preview', $contract->id))
            ->assertOk()
            ->assertSee($contract->contract_number);

        // 2. Print HTML
        $this->actingAs($this->founder)
            ->get(route('contracts.print', $contract->id))
            ->assertOk();

        // 3. Download PDF (dedicated route)
        $pdfResponse = $this->actingAs($this->founder)
            ->get(route('contracts.download-pdf', $contract->id));

        $pdfResponse->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $disposition = $pdfResponse->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('Notify-Contract-', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);
        $this->assertStringContainsString($contract->contract_number, $disposition);

        // 4. Download PDF (via format query parameter)
        $this->actingAs($this->founder)
            ->get(route('contracts.download', ['contract' => $contract->id, 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /* ----------------------------------------------------------------------
     | 9. Artifact Failure Isolation
     | ---------------------------------------------------------------------- */

    public function test_pdf_generation_failure_does_not_alter_subscription_invoice_or_contract(): void
    {
        $this->actingAs($this->founder)
            ->post(route('clients.guided-subscription.store', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'monthly',
            ]);

        $contract = Contract::where('client_id', $this->client->id)->first();
        $subscription = Subscription::where('client_id', $this->client->id)->first();
        $invoice = Invoice::where('subscription_id', $subscription->id)->first();

        // Mock PDF failure
        $mockPdf = Mockery::mock(ContractPdfService::class);
        $mockPdf->shouldReceive('downloadResponse')
            ->once()
            ->andThrow(new RuntimeException('Simulated PDF rendering engine crash'));
        $this->app->instance(ContractPdfService::class, $mockPdf);

        // Download fails gracefully with redirect and flash warning
        $response = $this->actingAs($this->founder)
            ->get(route('contracts.download-pdf', $contract->id));

        $response->assertRedirect(route('clients.show', $this->client->id))
            ->assertSessionHas('warning');

        // Subscription, invoice, and contract snapshot remain 100% intact
        $this->assertEquals('active', $subscription->fresh()->status);
        $this->assertEquals($subscription->total_minor, $invoice->fresh()->total_minor);
        $this->assertEquals('draft', $contract->fresh()->status);
        $this->assertNotNull($contract->fresh()->snapshot_data);
    }

    /* ----------------------------------------------------------------------
     | 10. Permissions Integrity
     | ---------------------------------------------------------------------- */

    public function test_staff_cannot_access_guided_subscription_routes(): void
    {
        $this->assertFalse(FinancialPermissions::allows($this->staff, FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING));

        // Catalog route forbidden for staff
        $this->actingAs($this->staff)
            ->getJson(route('clients.guided-subscription.catalog', $this->client->id))
            ->assertForbidden();

        // Preview route forbidden for staff
        $this->actingAs($this->staff)
            ->postJson(route('clients.guided-subscription.preview', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'monthly',
            ])
            ->assertForbidden();

        // Store route forbidden for staff
        $this->actingAs($this->staff)
            ->post(route('clients.guided-subscription.store', $this->client->id), [
                'product_id' => $this->productA->id,
                'plan_id' => $this->planA1->id,
                'billing_interval' => 'monthly',
            ])
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post(route('clients.guided-subscription.store', $this->client->id))
            ->assertRedirect(route('login'));

        $this->get(route('contracts.download-pdf', 1))
            ->assertRedirect(route('login'));
    }
}
