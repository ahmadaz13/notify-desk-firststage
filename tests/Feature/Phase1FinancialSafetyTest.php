<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\FundingSource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AccountingSetupService;
use App\Services\PlanPriceService;
use App\Support\FinancialPermissions;
use App\Support\Money;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase1FinancialSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected User $nullRoleUser;
    protected Client $client;
    protected Product $product;
    protected Plan $plan;
    protected PlanPrice $monthlyPrice;
    protected FinancialAccount $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        app(AccountingSetupService::class)->ensureSeeded();

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);

        $this->client = Client::create([
            'business_name' => 'Financial Safety Client',
            'business_category' => 'Retail',
            'phone' => '0799991111',
            'city_area' => 'Amman',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'created_by' => $this->admin->id,
        ]);

        $this->product = Product::create([
            'name_ar' => 'منتج تجريبي',
            'name_en' => 'Test Product',
            'code' => 'TEST_PROD',
            'is_active' => true,
        ]);

        $this->plan = Plan::create([
            'product_id' => $this->product->id,
            'name_ar' => 'باقة تجريبية',
            'name_en' => 'Test Plan',
            'code' => 'TEST_PLAN',
            'is_active' => true,
        ]);

        $this->monthlyPrice = PlanPrice::create([
            'plan_id' => $this->plan->id,
            'billing_interval' => PlanPrice::MONTHLY,
            'amount_minor' => 50000,
            'currency' => 'JOD',
            'effective_from' => now()->subDay(),
            'is_active' => true,
        ]);

        $this->cashAccount = FinancialAccount::firstOrCreate(
            ['code' => 'TEST_CASH'],
            [
                'name_ar' => 'صندوق تجريبي',
                'name_en' => 'Test Cash Box',
                'type' => 'cash',
                'currency' => 'JOD',
                'is_active' => true,
                'created_by' => $this->admin->id,
            ]
        );
    }

    public function test_normal_payment_submission_idempotency_prevents_duplicate_payments(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            '_idempotency_key' => $idempotencyKey,
            'amount' => '50.000',
            'payment_method' => 'cash',
            'financial_account_id' => $this->cashAccount->id,
            'reference' => 'REF-IDEM-01',
            'paid_at' => now()->toDateString(),
        ];

        // First submission (creates transaction)
        $response1 = $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client->id), $payload);

        $response1->assertRedirect();
        $this->assertDatabaseCount('payments', 1);

        $payment = Payment::first();
        $this->assertNotNull($payment);
        $this->assertEquals(50000, $payment->amount_minor);

        // Second submission (same key & payload - rapid double click or network retry)
        $response2 = $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client->id), $payload);

        $response2->assertRedirect();

        // Exactly one payment, one cash movement remain
        $this->assertDatabaseCount('payments', 1);
        $this->assertEquals(1, Payment::where('client_id', $this->client->id)->count());
    }

    public function test_payment_idempotency_payload_conflict_returns_409(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $payload1 = [
            '_idempotency_key' => $idempotencyKey,
            'amount' => '50.000',
            'payment_method' => 'cash',
            'financial_account_id' => $this->cashAccount->id,
            'paid_at' => now()->toDateString(),
        ];

        $payload2 = [
            '_idempotency_key' => $idempotencyKey,
            'amount' => '100.000', // Different payload!
            'payment_method' => 'cash',
            'financial_account_id' => $this->cashAccount->id,
            'paid_at' => now()->toDateString(),
        ];

        $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client->id), $payload1)
            ->assertRedirect();

        // Same key, different payload -> 409 Conflict
        $responseConflict = $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client->id), $payload2);

        $responseConflict->assertStatus(409);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_guided_subscription_idempotency_prevents_duplicate_subscriptions(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            '_idempotency_key' => $idempotencyKey,
            'product_id' => $this->product->id,
            'plan_id' => $this->plan->id,
            'billing_interval' => 'monthly',
            'quantity' => 1,
            'start_date' => now()->toDateString(),
        ];

        // First submission
        $response1 = $this->actingAs($this->admin)
            ->post(route('clients.guided-subscription.store', $this->client->id), $payload);

        $response1->assertRedirect();
        $this->assertDatabaseCount('subscriptions', 1);

        // Immediate second submission (double click)
        $response2 = $this->actingAs($this->admin)
            ->post(route('clients.guided-subscription.store', $this->client->id), $payload);

        $response2->assertRedirect();

        // Strictly 1 subscription and 1 initial invoice
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_operating_expense_idempotency_prevents_duplicate_expenses(): void
    {
        $category = ExpenseCategory::firstOrCreate(
            ['key' => 'office_supplies'],
            ['name' => 'Office Supplies', 'name_ar' => 'قرطاسية', 'name_en' => 'Office Supplies', 'is_active' => true]
        );

        $idempotencyKey = (string) Str::uuid();

        $payload = [
            '_idempotency_key' => $idempotencyKey,
            'amount' => '35.500',
            'category_id' => $category->id,
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $this->cashAccount->id,
            'incurred_on' => now()->toDateString(),
            'paid_at' => now()->toDateString(),
            'description' => 'Test Idempotent Expense',
        ];

        // First submission
        $this->actingAs($this->admin)
            ->post(route('operating-expenses.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('expenses', 1);

        // Second submission with same token
        $this->actingAs($this->admin)
            ->post(route('operating-expenses.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_capital_funding_idempotency_prevents_duplicate_funding(): void
    {
        $fundingSource = FundingSource::firstOrCreate(
            ['name' => 'Founder Personal Capital'],
            ['type' => 'founder', 'is_active' => true]
        );

        $idempotencyKey = (string) Str::uuid();

        $payload = [
            '_idempotency_key' => $idempotencyKey,
            'funding_type' => 'founder_contribution',
            'funding_source_id' => $fundingSource->id,
            'financial_account_id' => $this->cashAccount->id,
            'amount' => '1000.000',
            'received_at' => now()->toDateString(),
            'notes' => 'Test Capital Funding',
        ];

        // First submission
        $this->actingAs($this->admin)
            ->post(route('capital-funding-transactions.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('capital_funding_transactions', 1);

        // Second submission
        $this->actingAs($this->admin)
            ->post(route('capital-funding-transactions.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('capital_funding_transactions', 1);
    }

    public function test_unauthorized_staff_cannot_record_payment(): void
    {
        $payload = [
            'amount' => '50.000',
            'payment_method' => 'cash',
            'financial_account_id' => $this->cashAccount->id,
            'paid_at' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->staff)
            ->post(route('clients.payments.normal.store', $this->client->id), $payload);

        $response->assertStatus(403);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_unauthorized_staff_cannot_create_subscription(): void
    {
        $payload = [
            'product_id' => $this->product->id,
            'plan_id' => $this->plan->id,
            'billing_interval' => 'monthly',
            'quantity' => 1,
            'start_date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->staff)
            ->post(route('clients.guided-subscription.store', $this->client->id), $payload);

        $response->assertStatus(403);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_null_or_unknown_role_is_denied_privileged_financial_access_by_default(): void
    {
        $nullUser = new User(['role' => null]);
        $this->assertFalse($nullUser->isAdmin());
        $this->assertFalse($nullUser->isOwnerLevelInternalUser());
        $this->assertFalse(FinancialPermissions::allows($nullUser, FinancialPermissions::RECORD_PAYMENT));
        $this->assertFalse(FinancialPermissions::allows($nullUser, FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING));

        $unknownUser = new User(['role' => 'unsupported_role_xyz']);
        $this->assertFalse($unknownUser->isAdmin());
        $this->assertFalse($unknownUser->isOwnerLevelInternalUser());
        $this->assertFalse(FinancialPermissions::allows($unknownUser, FinancialPermissions::RECORD_PAYMENT));

        $employee = User::factory()->create([
            'role' => User::ROLE_EMPLOYEE,
            'is_active' => true,
        ]);
        $this->assertFalse($employee->isAdmin());
        $this->assertFalse(FinancialPermissions::allows($employee, FinancialPermissions::RECORD_PAYMENT));

        $response = $this->actingAs($employee)
            ->post(route('clients.payments.normal.store', $this->client->id), [
                'amount' => '10.000',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_client_with_financial_history_cannot_be_hard_deleted(): void
    {
        // Add an invoice to the client
        Invoice::create([
            'invoice_number' => 'INV-TEST-DELETE-01',
            'client_id' => $this->client->id,
            'currency' => 'JOD',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal_minor' => 50000,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 50000,
            'created_by' => $this->admin->id,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot delete client with existing financial history');

        $this->client->delete();

        $this->assertDatabaseHas('clients', ['id' => $this->client->id]);
    }

    public function test_payment_and_allocation_and_issued_invoice_cannot_be_hard_deleted(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-DEL-02',
            'client_id' => $this->client->id,
            'currency' => 'JOD',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal_minor' => 50000,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 50000,
            'created_by' => $this->admin->id,
        ]);

        $payment = Payment::create([
            'client_id' => $this->client->id,
            'amount' => '50.000',
            'amount_minor' => 50000,
            'currency' => 'JOD',
            'payment_engine_version' => 'v2',
            'payment_method' => 'cash',
            'paid_at' => now(),
            'recorded_by' => $this->admin->id,
        ]);

        $allocation = PaymentAllocation::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'client_id' => $this->client->id,
            'amount_minor' => 50000,
            'allocated_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        // Attempt deleting payment
        try {
            $payment->delete();
            $this->fail('Expected DomainException for payment deletion');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // Attempt deleting allocation
        try {
            $allocation->delete();
            $this->fail('Expected DomainException for payment allocation deletion');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // Attempt deleting issued invoice
        try {
            $invoice->delete();
            $this->fail('Expected DomainException for invoice deletion');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        }
    }

    public function test_validation_error_on_plan_price_does_not_leak_raw_snake_case_message(): void
    {
        // Post without plan_price_id and without fallback adapter params
        $response = $this->actingAs($this->admin)
            ->post(route('clients.paid-subscriptions.store', $this->client->id), [
                'quantity' => 1,
                'start_date' => now()->toDateString(),
            ]);

        $response->assertSessionHasErrors('plan_price_id');
        $errorMessage = session('errors')->first('plan_price_id');

        // Verify that raw "The plan price id field is required." is NOT emitted
        $this->assertStringNotContainsString('The plan price id field is required.', $errorMessage);
        $this->assertStringNotContainsString('plan_price_id', $errorMessage);
    }

    public function test_today_and_work_action_labels_render_translated_strings_not_raw_keys(): void
    {
        app()->setLocale('ar');
        $this->assertEquals('تسجيل النتيجة', __('notify.actions.record_outcome'));
        $this->assertEquals('فتح ملف العميل', __('notify.actions.open_client'));
        $this->assertEquals('إكمال التركيب', __('notify.actions.complete_installation'));
        $this->assertEquals('تسجيل المتابعة', __('notify.actions.record_follow_up'));

        app()->setLocale('en');
        $this->assertEquals('Record Outcome', __('notify.actions.record_outcome'));
        $this->assertEquals('Open Client', __('notify.actions.open_client'));
        $this->assertEquals('Complete Installation', __('notify.actions.complete_installation'));
        $this->assertEquals('Record Follow-up', __('notify.actions.record_follow_up'));
    }

    public function test_money_precision_integer_fils_invariant(): void
    {
        $money = Money::fromJod('12.345');
        $this->assertEquals(12345, $money->minorUnits());
        $this->assertEquals('12.345', $money->format());

        // Arithmetic with Money
        $added = $money->add(Money::fromMinorUnits(655));
        $this->assertEquals(13000, $added->minorUnits());
        $this->assertEquals('13.000', $added->format());
    }

    public function test_accounting_idempotency_prevents_duplicate_journal_entries(): void
    {
        $postingService = app(\App\Services\JournalPostingService::class);
        $debitAccount = \App\Models\ChartAccount::where('allow_direct_posting', true)->where('account_type', 'asset')->firstOrFail();
        $creditAccount = \App\Models\ChartAccount::where('allow_direct_posting', true)->where('account_type', 'revenue')->firstOrFail();

        $entryData = [
            'event_type' => 'invoice_issued',
            'event_key' => 'inv_issued_test_idem_01',
            'entry_date' => now()->toDateString(),
            'description' => 'Test Idempotent Invoice Accounting Entry',
        ];

        $lines = [
            ['chart_account_id' => $debitAccount->id, 'debit_minor' => 50000, 'credit_minor' => 0],
            ['chart_account_id' => $creditAccount->id, 'debit_minor' => 0, 'credit_minor' => 50000],
        ];

        $journal1 = $postingService->post($entryData, $lines);
        $this->assertNotNull($journal1);
        $this->assertDatabaseCount('journal_entries', 1);

        // Repost with same event_key
        $journal2 = $postingService->post($entryData, $lines);
        $this->assertEquals($journal1->id, $journal2->id);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_missing_idempotency_key_does_not_silently_bypass_financial_idempotency(): void
    {
        $category = ExpenseCategory::firstOrCreate(
            ['key' => 'office_supplies_unkeyed'],
            ['name' => 'Office Supplies Unkeyed', 'name_ar' => 'قرطاسية بدون مفتاح', 'is_active' => true]
        );

        $payload = [
            'amount' => '42.000',
            'category_id' => $category->id,
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $this->cashAccount->id,
            'incurred_on' => now()->toDateString(),
            'paid_at' => now()->toDateString(),
            'description' => 'Unkeyed Protected Expense',
        ];

        // Simulate an in-flight unkeyed request currently processing
        $cleaned = collect($payload)->except(['_token', '_idempotency_key', 'idempotency_key'])->sortKeys()->toArray();
        $synKey = 'syn_'.substr(hash('sha256', $this->admin->id.'|POST|operating-expenses|'.json_encode($cleaned)), 0, 48);
        $hash = hash('sha256', $this->admin->id.'|POST|operating-expenses|'.json_encode($cleaned));

        DB::table('idempotency_keys')->insert([
            'key' => $synKey,
            'request_hash' => $hash,
            'status' => 'processing',
            'user_id' => $this->admin->id,
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // A concurrent unkeyed request arriving now sees the in-flight claim and is blocked from creating a duplicate!
        $res = $this->actingAs($this->admin)
            ->post(route('operating-expenses.store'), $payload);

        $res->assertStatus(409);
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_successful_unkeyed_financial_request_cannot_execute_again_after_first_request_completed(): void
    {
        $category = ExpenseCategory::firstOrCreate(
            ['key' => 'unkeyed_replay_prevent_cat'],
            ['name' => 'Unkeyed Replay Prevention', 'name_ar' => 'منع تكرار بدون مفتاح', 'is_active' => true]
        );

        $payload = [
            'amount' => '65.000',
            'category_id' => $category->id,
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $this->cashAccount->id,
            'incurred_on' => now()->toDateString(),
            'paid_at' => now()->toDateString(),
            'description' => 'Unkeyed Initial Expense',
        ];

        // 1. First unkeyed request executes and completes successfully
        $res1 = $this->actingAs($this->admin)
            ->post(route('operating-expenses.store'), $payload);
        $res1->assertRedirect();
        $this->assertDatabaseCount('expenses', 1);

        // Verify that the completed synthetic record persists with 24h TTL
        $cleaned = collect($payload)->except(['_token', '_idempotency_key', 'idempotency_key'])->sortKeys()->toArray();
        $synKey = 'syn_'.substr(hash('sha256', $this->admin->id.'|POST|operating-expenses|'.json_encode($cleaned)), 0, 48);

        $this->assertDatabaseHas('idempotency_keys', [
            'key' => $synKey,
            'status' => 'completed',
            'user_id' => $this->admin->id,
        ]);

        // 2. Second unkeyed request with the SAME user + route + payload arrives AFTER first completed
        $res2 = $this->actingAs($this->admin)
            ->post(route('operating-expenses.store'), $payload);
        $res2->assertRedirect();

        // 3. Must NOT execute again or create a second financial transaction!
        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_transaction_crash_window_blocks_retry_if_committed_with_error(): void
    {
        $category = ExpenseCategory::firstOrCreate(
            ['key' => 'crash_window_cat'],
            ['name' => 'Crash Window Test', 'name_ar' => 'اختبار نافذة الانهيار', 'is_active' => true]
        );

        $key = 'crash-window-test-key-'.Str::uuid();
        $payload = [
            '_idempotency_key' => $key,
            'amount' => '15.000',
            'category_id' => $category->id,
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $this->cashAccount->id,
            'incurred_on' => now()->toDateString(),
            'paid_at' => now()->toDateString(),
            'description' => 'Crash Window Expense',
        ];

        // Simulate committed_error state: business transaction committed, but outcome was marked committed_error
        $cleaned = collect($payload)->except(['_token', '_idempotency_key', 'idempotency_key'])->sortKeys()->toArray();
        $hash = hash('sha256', $this->admin->id.'|POST|operating-expenses|'.json_encode($cleaned));

        DB::table('idempotency_keys')->insert([
            'key' => $key,
            'request_hash' => $hash,
            'status' => 'committed_error',
            'user_id' => $this->admin->id,
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // When a retry arrives, it must be rejected with 409 and never re-execute to create a second expense
        $response = $this->actingAs($this->admin)
            ->post(route('operating-expenses.store'), $payload);

        $response->assertStatus(409);
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_cross_user_idempotency_key_isolation(): void
    {
        $admin2 = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $category = ExpenseCategory::firstOrCreate(
            ['key' => 'cross_user_cat'],
            ['name' => 'Cross User Test', 'name_ar' => 'اختبار عزل المستخدمين', 'is_active' => true]
        );

        $sharedKey = 'shared-key-isolation-'.Str::uuid();
        $payload = [
            '_idempotency_key' => $sharedKey,
            'amount' => '25.000',
            'category_id' => $category->id,
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $this->cashAccount->id,
            'incurred_on' => now()->toDateString(),
            'paid_at' => now()->toDateString(),
            'description' => 'Cross User Expense',
        ];

        // Admin 1 successfully executes
        $this->actingAs($this->admin)
            ->post(route('operating-expenses.store'), $payload)
            ->assertRedirect();
        $this->assertDatabaseCount('expenses', 1);

        // Admin 2 tries to use the same key
        $response = $this->actingAs($admin2)
            ->post(route('operating-expenses.store'), $payload);

        $response->assertStatus(409);
        // Expense count must still be 1 (Admin 2's request was blocked)
        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_cross_route_idempotency_key_isolation(): void
    {
        $category = ExpenseCategory::firstOrCreate(
            ['key' => 'cross_route_cat'],
            ['name' => 'Cross Route Test', 'name_ar' => 'اختبار عزل المسارات', 'is_active' => true]
        );

        $key = 'cross-route-key-'.Str::uuid();
        $expensePayload = [
            '_idempotency_key' => $key,
            'amount' => '10.000',
            'category_id' => $category->id,
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $this->cashAccount->id,
            'incurred_on' => now()->toDateString(),
            'paid_at' => now()->toDateString(),
            'description' => 'Route A Expense',
        ];

        // Route A executes
        $this->actingAs($this->admin)
            ->post(route('operating-expenses.store'), $expensePayload)
            ->assertRedirect();

        // Same key sent to Route B (financial transfer)
        $transferAccount = FinancialAccount::create([
            'code' => 'TEST_BANK_CROSS_ROUTE',
            'name_ar' => 'حساب بنكي تجريبي',
            'name_en' => 'Test Bank Account',
            'type' => 'bank',
            'currency' => 'JOD',
            'is_active' => true,
        ]);

        $transferPayload = [
            '_idempotency_key' => $key,
            'from_account_id' => $this->cashAccount->id,
            'to_account_id' => $transferAccount->id,
            'amount' => '5.000',
            'transferred_at' => now()->toDateString(),
            'notes' => 'Route B Transfer with same key',
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('financial-transfers.store'), $transferPayload);

        $response->assertStatus(409);
        $this->assertDatabaseCount('financial_transfers', 0);
    }

    public function test_validation_localization_integrity_for_arabic_and_english(): void
    {
        // Test Arabic
        app()->setLocale('ar');

        $validatorAr = validator([
            'amount' => 'not-a-number',
            'start_date' => 'invalid-date',
            'client_id' => 999999,
        ], [
            'amount' => 'required|numeric',
            'start_date' => 'required|date',
            'client_id' => 'required|exists:clients,id',
            'plan_price_id' => 'required|exists:plan_prices,id',
        ]);

        $this->assertTrue($validatorAr->fails());
        $errorsAr = $validatorAr->errors()->all();

        foreach ($errorsAr as $msg) {
            $this->assertStringNotContainsString('validation.', $msg, "Arabic validation leaked raw translation key: {$msg}");
        }

        $this->assertStringContainsString('رقماً', $validatorAr->errors()->first('amount'));
        $this->assertStringContainsString('تاريخاً', $validatorAr->errors()->first('start_date'));
        $this->assertStringContainsString('غير صالحة', $validatorAr->errors()->first('client_id'));
        $this->assertEquals('يرجى اختيار خطة وسعر الاشتراك.', $validatorAr->errors()->first('plan_price_id'));

        // Test English
        app()->setLocale('en');

        $validatorEn = validator([
            'amount' => 'not-a-number',
            'start_date' => 'invalid-date',
            'client_id' => 999999,
        ], [
            'amount' => 'required|numeric',
            'start_date' => 'required|date',
            'client_id' => 'required|exists:clients,id',
            'plan_price_id' => 'required|exists:plan_prices,id',
        ]);

        $this->assertTrue($validatorEn->fails());
        $errorsEn = $validatorEn->errors()->all();

        foreach ($errorsEn as $msg) {
            $this->assertStringNotContainsString('validation.', $msg, "English validation leaked raw translation key: {$msg}");
        }

        $this->assertStringContainsString('must be a number', $validatorEn->errors()->first('amount'));
        $this->assertStringContainsString('must be a valid date', $validatorEn->errors()->first('start_date'));
        $this->assertStringContainsString('invalid', $validatorEn->errors()->first('client_id'));
        $this->assertEquals('Please select a subscription plan and price.', $validatorEn->errors()->first('plan_price_id'));

        app()->setLocale('ar');
    }

    public function test_safe_subscription_adapter_enforces_product_matching_and_planprice_authority(): void
    {
        $product2 = Product::create([
            'name_ar' => 'منتج ثان',
            'name_en' => 'Second Product',
            'code' => 'PROD_2',
            'is_active' => true,
        ]);

        // Attempting to subscribe with plan_price_id belonging to Product 1 while specifying Product 2
        $response = $this->actingAs($this->admin)
            ->post(route('clients.paid-subscriptions.store', $this->client), [
                '_idempotency_key' => (string) Str::uuid(),
                'product_id' => $product2->id,
                'plan_price_id' => $this->monthlyPrice->id,
                'quantity' => 1,
                'start_date' => now()->toDateString(),
                'custom_price' => '1.000', // Attempting to tamper with price
            ]);

        $response->assertSessionHasErrors(['plan_id']);

        // Now subscribe validly with correct product
        $validKey = (string) Str::uuid();
        $responseValid = $this->actingAs($this->admin)
            ->post(route('clients.paid-subscriptions.store', $this->client), [
                '_idempotency_key' => $validKey,
                'product_id' => $this->product->id,
                'plan_price_id' => $this->monthlyPrice->id,
                'quantity' => 1,
                'start_date' => now()->toDateString(),
                'custom_price' => '1.000', // Attempting to override price
            ]);

        $responseValid->assertRedirect();

        // Verify PlanPrice authority: invoice total is 50.000 JOD (monthlyPrice 50000 fils), NOT 1.000
        $subscription = Subscription::where('client_id', $this->client->id)->latest()->firstOrFail();
        $invoice = Invoice::where('subscription_id', $subscription->id)->firstOrFail();
        $this->assertEquals(50000, $invoice->total_minor);

        // Verify same-product subscription conflict prevents duplicate active subscription
        $responseConflict = $this->actingAs($this->admin)
            ->post(route('clients.paid-subscriptions.store', $this->client), [
                '_idempotency_key' => (string) Str::uuid(),
                'product_id' => $this->product->id,
                'plan_price_id' => $this->monthlyPrice->id,
                'quantity' => 1,
                'start_date' => now()->toDateString(),
            ]);

        $responseConflict->assertSessionHasErrors(['plan_price_id']);
    }
}
