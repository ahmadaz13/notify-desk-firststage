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

    public function test_legacy_financial_endpoints_return_410_and_log_warning(): void
    {
        $routes = [
            ['POST', '/payments'],
            ['POST', '/expenses'],
            ['POST', '/investments'],
            ['POST', '/capital-expenses'],
            ['POST', "/clients/{$this->client->id}/convert"],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->actingAs($this->admin)->call($method, $uri);
            $response->assertStatus(410);
        }
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
}
