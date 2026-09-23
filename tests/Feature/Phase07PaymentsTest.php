<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Contract;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AccountingSetupService;
use App\Services\BillingAccountingService;
use App\Services\FinancialAccountBalanceService;
use App\Services\ReceivableService;
use App\Support\ClientLifecycle;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class Phase07PaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        app(AccountingSetupService::class)->ensureSeeded();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $this->client = $this->createClient();
    }

    public function test_amount_due_comes_from_receivable_service_and_workspace_refreshes_after_payment(): void
    {
        $this->createAccount('cash', FinancialAccount::TYPE_CASH);
        $this->createInvoice($this->client, 45000, '2026-09-01');

        $this->actingAs($this->admin)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee('45.000', false);

        $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client), [
                'amount' => '45.000',
                'payment_method' => 'cash',
            ])
            ->assertRedirect(route('clients.show', $this->client));

        $this->assertSame(0, app(ReceivableService::class)->clientSummary($this->client->fresh())['total_outstanding_minor']);

        $this->actingAs($this->admin)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee('0.000', false);
    }

    public function test_staff_cannot_access_normal_record_payment_route(): void
    {
        $this->createAccount('cash', FinancialAccount::TYPE_CASH);

        $this->actingAs($this->staff)
            ->post(route('clients.payments.normal.store', $this->client), [
                'amount' => '1.000',
                'payment_method' => 'cash',
            ])
            ->assertForbidden();
    }

    public function test_authorized_normal_payment_resolves_canonical_account_and_ignores_browser_account_override(): void
    {
        // V1: cash resolves by code to the bootstrapped CASH-BOX, never by account type [FROZEN D-01/D-02].
        $cash = FinancialAccount::where('code', 'CASH-BOX')->firstOrFail();
        $this->createAccount('cash', FinancialAccount::TYPE_CASH);
        $bank = $this->createAccount('bank', FinancialAccount::TYPE_BANK);
        $invoice = $this->createInvoice($this->client, 10000, '2026-09-01');

        $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client), [
                'amount' => '10.000',
                'payment_method' => 'cash',
                'financial_account_id' => $bank->id,
                'received_at' => '2026-09-18 10:00:00',
            ])
            ->assertSessionHas('success');

        $payment = Payment::firstOrFail();
        $this->assertSame('cash', $payment->payment_method);
        $this->assertSame(10000, (int) $payment->amount_minor);
        $this->assertSame(1, PaymentAllocation::count());
        $this->assertSame(0, app(ReceivableService::class)->invoiceOutstandingMinor($invoice->fresh()));

        $this->assertDatabaseHas('cash_movements', [
            'financial_account_id' => $cash->id,
            'event_type' => CashMovement::EVENT_PAYMENT_RECEIVED,
            'amount_minor' => 10000,
        ]);
        $this->assertDatabaseMissing('cash_movements', [
            'financial_account_id' => $bank->id,
            'event_type' => CashMovement::EVENT_PAYMENT_RECEIVED,
        ]);

        $this->assertSame(10000, app(FinancialAccountBalanceService::class)->currentBalanceMinor($cash->fresh()));
        $this->assertTrue(JournalEntry::where('event_type', 'payment_received')->exists());
        $this->assertTrue(JournalEntry::where('event_type', 'payment_allocation_accounting')->exists());
    }

    public function test_resolver_fails_safely_for_missing_or_inactive_company_account(): void
    {
        // Retired: type-based ambiguity. V1 resolves strictly by code (CASH-BOX), so extra cash-type
        // accounts cannot make resolution ambiguous; missing or inactive CASH-BOX still fails safely.
        $cashBox = FinancialAccount::where('code', 'CASH-BOX')->firstOrFail();
        $cashBox->update(['is_active' => false]);

        $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client), [
                'amount' => '1.000',
                'payment_method' => 'cash',
            ])
            ->assertSessionHasErrors('payment_method');

        $cashBox->update(['is_active' => true, 'code' => 'CASH-BOX-RENAMED']);

        $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client), [
                'amount' => '1.000',
                'payment_method' => 'cash',
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, Payment::count());
    }

    public function test_zero_and_negative_amounts_are_rejected_with_no_payment_created(): void
    {
        $this->createAccount('cash', FinancialAccount::TYPE_CASH);

        $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client), [
                'amount' => '0.000',
                'payment_method' => 'cash',
            ])
            ->assertSessionHasErrors('amount');

        $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client), [
                'amount' => '-1.000',
                'payment_method' => 'cash',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Payment::count());
    }

    public function test_partial_exact_overpayment_and_oldest_first_allocation_behaviour(): void
    {
        $this->createAccount('cash', FinancialAccount::TYPE_CASH);
        $oldest = $this->createInvoice($this->client, 10000, '2026-09-01');
        $newest = $this->createInvoice($this->client, 10000, '2026-09-10');

        $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client), [
                'amount' => '5.000',
                'payment_method' => 'cash',
            ])
            ->assertSessionHas('success');

        $receivables = app(ReceivableService::class);
        $this->assertSame(5000, $receivables->invoiceOutstandingMinor($oldest->fresh()));
        $this->assertSame(10000, $receivables->invoiceOutstandingMinor($newest->fresh()));

        $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client), [
                'amount' => '20.000',
                'payment_method' => 'cash',
            ])
            ->assertSessionHas('success');

        $latestPayment = Payment::latest('id')->firstOrFail();
        $this->assertSame(0, $receivables->invoiceOutstandingMinor($oldest->fresh()));
        $this->assertSame(0, $receivables->invoiceOutstandingMinor($newest->fresh()));
        $this->assertSame(5000, $receivables->paymentUnallocatedMinor($latestPayment->fresh()));
        $this->assertSame($oldest->id, PaymentAllocation::orderBy('id')->firstOrFail()->invoice_id);
    }

    public function test_transaction_rolls_back_payment_cash_and_allocation_when_downstream_accounting_fails(): void
    {
        $this->createAccount('cash', FinancialAccount::TYPE_CASH);
        $this->createInvoice($this->client, 10000, '2026-09-01');

        $mock = Mockery::mock(BillingAccountingService::class);
        $mock->shouldReceive('postPaymentAllocation')->andThrow(new \RuntimeException('posting failed'));
        $this->app->instance(BillingAccountingService::class, $mock);

        $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client), [
                'amount' => '10.000',
                'payment_method' => 'cash',
            ])
            ->assertStatus(500);

        $this->assertSame(0, Payment::count());
        $this->assertSame(0, PaymentAllocation::count());
        $this->assertSame(0, CashMovement::count());
        $this->assertSame(0, JournalEntry::where('event_type', 'payment_received')->count());
    }

    public function test_payment_never_creates_subscription_changes_plan_price_or_regenerates_contract(): void
    {
        $this->createAccount('cash', FinancialAccount::TYPE_CASH);
        $this->createInvoice($this->client, 10000, '2026-09-01');

        $before = [
            'subscriptions' => Subscription::count(),
            'plan_prices' => PlanPrice::count(),
            'contracts' => Contract::count(),
        ];

        $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client), [
                'amount' => '10.000',
                'payment_method' => 'cash',
            ])
            ->assertSessionHas('success');

        $this->assertSame($before['subscriptions'], Subscription::count());
        $this->assertSame($before['plan_prices'], PlanPrice::count());
        $this->assertSame($before['contracts'], Contract::count());
    }

    public function test_existing_manual_allocation_path_remains_available(): void
    {
        $account = $this->createAccount('cash', FinancialAccount::TYPE_CASH);
        $invoice = $this->createInvoice($this->client, 10000, '2026-09-01');

        $this->actingAs($this->admin)
            ->post(route('clients.collections.payments.store', $this->client), [
                'amount' => '10.000',
                'financial_account_id' => $account->id,
                'payment_method' => 'cash',
                'received_at' => '2026-09-18 10:00:00',
                'allocations' => [
                    ['invoice_id' => $invoice->id, 'amount' => '5.000'],
                ],
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, Payment::count());
        $this->assertSame(1, PaymentAllocation::count());
        $this->assertSame(5000, app(ReceivableService::class)->invoiceOutstandingMinor($invoice->fresh()));
    }

    private function createClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'Phase 07 Client',
            'phone' => '0790000000',
            'city_area' => 'Amman',
            'business_category' => 'Restaurant',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
            'stage' => ClientLifecycle::SUBSCRIBER,
        ], $overrides));
    }

    private function createAccount(string $code, string $type): FinancialAccount
    {
        return FinancialAccount::create([
            'code' => $code,
            'name_ar' => $code,
            'name_en' => $code,
            'type' => $type,
            'currency' => 'JOD',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
    }

    private function createInvoice(Client $client, int $totalMinor, string $dueDate): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => 'P07-'.str_pad((string) (Invoice::count() + 1), 6, '0', STR_PAD_LEFT),
            'client_id' => $client->id,
            'currency' => 'JOD',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => '2026-09-01',
            'due_date' => $dueDate,
            'subtotal_minor' => $totalMinor,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => $totalMinor,
            'issued_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        $invoice->lines()->create([
            'line_type' => InvoiceLine::TYPE_CUSTOM,
            'description_snapshot' => 'Phase 07 line',
            'quantity' => 1,
            'unit_price_minor' => $totalMinor,
            'subtotal_minor' => $totalMinor,
            'discount_minor' => 0,
            'tax_rate_bps' => 0,
            'tax_minor' => 0,
            'total_minor' => $totalMinor,
            'sort_order' => 10,
        ]);

        return $invoice;
    }
}
