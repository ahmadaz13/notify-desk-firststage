<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\AccountingReconciliationService;
use App\Services\AccountingSetupService;
use App\Services\BillingAccountingService;
use App\Services\CreditNoteService;
use App\Services\FinancialAccountService;
use App\Services\InvoiceService;
use App\Services\JournalPostingService;
use App\Services\PaymentAllocationService;
use App\Services\RefundService;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Database\Seeders\AssetCategorySeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePhaseE2ATest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->seed(ExpenseCategorySeeder::class);
        $this->seed(AssetCategorySeeder::class);
        app(AccountingSetupService::class)->ensureSeeded();
    }

    public function test_invoice_issue_posts_ar_deferred_tax_no_revenue_and_void_reverses(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $invoice = $this->createInvoice($admin, $this->createClient(), 100000, 16000);
        $setup = app(AccountingSetupService::class);

        $journal = JournalEntry::where('event_key', 'billing:invoice:'.$invoice->id.':issued')->firstOrFail();
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $journal->id, 'chart_account_id' => $setup->systemAccount('accounts_receivable')->id, 'debit_minor' => 116000]);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $journal->id, 'chart_account_id' => $setup->systemAccount('deferred_revenue')->id, 'credit_minor' => 100000]);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $journal->id, 'chart_account_id' => $setup->systemAccount('sales_tax_payable')->id, 'credit_minor' => 16000]);
        $this->assertDatabaseMissing('journal_lines', ['journal_entry_id' => $journal->id, 'chart_account_id' => $setup->systemAccount('saas_subscription_revenue')->id]);
        $this->assertDatabaseMissing('journal_lines', ['journal_entry_id' => $journal->id, 'chart_account_id' => $setup->systemAccount('one_time_service_revenue')->id]);

        $count = JournalEntry::count();
        app(BillingAccountingService::class)->postInvoiceIssued($invoice);
        $this->assertSame($count, JournalEntry::count());

        app(InvoiceService::class)->void($invoice, 'Cancel invoice', $admin->id);
        $reversal = JournalEntry::where('event_type', 'invoice_void_accounting')->firstOrFail();
        $this->assertSame($journal->id, $reversal->reversal_of_id);
        $this->assertSame(JournalEntry::STATUS_REVERSED, $journal->fresh()->status);
    }

    public function test_payment_allocation_posts_without_cash_and_reversal_is_exact(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->createClient();
        $invoice = $this->createInvoice($admin, $client, 100000, 0);
        $account = $this->createFinancialAccount($admin);

        $beforeCash = CashMovement::count();
        $payment = app(PaymentAllocationService::class)->recordV2Payment($client, [
            'amount' => '60.000',
            'financial_account_id' => $account->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
        ], [['invoice_id' => $invoice->id, 'amount' => '40.000']], false, $admin->id);
        $allocation = $payment->allocations()->firstOrFail();
        $setup = app(AccountingSetupService::class);

        $this->assertSame($beforeCash + 1, CashMovement::count());
        $journal = JournalEntry::where('event_key', 'billing:payment-allocation:'.$allocation->id)->firstOrFail();
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $journal->id, 'chart_account_id' => $setup->systemAccount('billing_clearing')->id, 'debit_minor' => 40000]);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $journal->id, 'chart_account_id' => $setup->systemAccount('accounts_receivable')->id, 'credit_minor' => 40000]);

        $this->actingAs($admin)->post(route('payment-allocations.reverse', $allocation), ['reason' => 'Correction'])->assertSessionHas('success');
        $reversal = JournalEntry::where('event_type', 'payment_allocation_reversal_accounting')->firstOrFail();
        $this->assertSame($journal->id, $reversal->reversal_of_id);
    }

    public function test_credit_note_issue_application_refund_and_void_accounting(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->createClient();
        $invoice = $this->createInvoice($admin, $client, 100000, 16000);
        $credit = app(CreditNoteService::class)->createIssued($client, [
            'issue_date' => '2026-09-14',
            'original_invoice_id' => $invoice->id,
            'reason' => 'Service adjustment',
            'lines' => [['description' => 'Adjustment', 'subtotal_jod' => '10.000', 'tax_jod' => '1.600']],
        ], $admin->id);
        $setup = app(AccountingSetupService::class);

        $creditJournal = JournalEntry::where('event_key', 'billing:credit-note:'.$credit->id.':issued')->firstOrFail();
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $creditJournal->id, 'chart_account_id' => $setup->systemAccount('deferred_revenue')->id, 'debit_minor' => 10000]);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $creditJournal->id, 'chart_account_id' => $setup->systemAccount('sales_tax_payable')->id, 'debit_minor' => 1600]);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $creditJournal->id, 'chart_account_id' => $setup->systemAccount('customer_credits')->id, 'credit_minor' => 11600]);
        $this->assertSame(JournalEntry::STATUS_POSTED, JournalEntry::where('event_key', 'billing:invoice:'.$invoice->id.':issued')->firstOrFail()->status);

        $application = app(CreditNoteService::class)->applyCredit($credit, $invoice, '5.000', $admin->id);
        $applicationJournal = JournalEntry::where('event_key', 'billing:credit-application:'.$application->id)->firstOrFail();
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $applicationJournal->id, 'chart_account_id' => $setup->systemAccount('customer_credits')->id, 'debit_minor' => 5000]);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $applicationJournal->id, 'chart_account_id' => $setup->systemAccount('accounts_receivable')->id, 'credit_minor' => 5000]);
        $cashBefore = CashMovement::count();
        app(CreditNoteService::class)->reverseApplication($application, 'Undo application', $admin->id);
        $this->assertSame($cashBefore, CashMovement::count());
        $this->assertSame($applicationJournal->id, JournalEntry::where('event_type', 'credit_application_reversal_accounting')->firstOrFail()->reversal_of_id);

        $refundAccount = $this->createFinancialAccount($admin, 'refund_credit_cash');
        app(RefundService::class)->refundFromCreditNote($credit, [
            'amount' => '2.000',
            'financial_account_id' => $refundAccount->id,
            'refund_method' => 'cash',
            'refunded_at' => '2026-09-14 11:00:00',
            'reason' => 'Credit cash-out',
        ], $admin->id);
        $refund = Refund::firstOrFail();
        $refundJournal = JournalEntry::where('event_key', 'cash:refund:'.$refund->id.':issued')->firstOrFail();
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $refundJournal->id, 'chart_account_id' => $setup->systemAccount('customer_credits')->id, 'debit_minor' => 2000]);
    }

    public function test_credit_note_void_reverses_and_payment_funded_refund_uses_billing_clearing(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->createClient();
        $invoice = $this->createInvoice($admin, $client, 50000, 8000);
        $credit = app(CreditNoteService::class)->createIssued($client, [
            'issue_date' => '2026-09-14',
            'original_invoice_id' => $invoice->id,
            'reason' => 'Voidable credit',
            'lines' => [['description' => 'Voidable credit', 'subtotal_jod' => '5.000', 'tax_jod' => '0.800']],
        ], $admin->id);

        $creditJournal = JournalEntry::where('event_key', 'billing:credit-note:'.$credit->id.':issued')->firstOrFail();
        app(CreditNoteService::class)->void($credit, 'No longer needed', $admin->id);
        $this->assertSame($creditJournal->id, JournalEntry::where('event_type', 'credit_note_void_accounting')->firstOrFail()->reversal_of_id);

        $account = $this->createFinancialAccount($admin, 'payment_refund_cash');
        $payment = app(PaymentAllocationService::class)->recordV2Payment($client, [
            'amount' => '20.000',
            'financial_account_id' => $account->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
        ], [], false, $admin->id);
        app(RefundService::class)->refundFromPayment($payment, [
            'amount' => '6.000',
            'financial_account_id' => $account->id,
            'refund_method' => 'cash',
            'refunded_at' => '2026-09-14 12:00:00',
            'reason' => 'Refund unallocated payment credit',
        ], $admin->id);

        $refund = Refund::where('payment_id', $payment->id)->firstOrFail();
        $refundJournal = JournalEntry::where('event_key', 'cash:refund:'.$refund->id.':issued')->firstOrFail();
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $refundJournal->id,
            'chart_account_id' => app(AccountingSetupService::class)->systemAccount('billing_clearing')->id,
            'debit_minor' => 6000,
        ]);
    }

    public function test_historical_credit_note_refund_reclassification_preserves_e1_journal(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->createClient();
        $credit = CreditNote::create([
            'credit_note_number' => 'CN-HIST-1',
            'client_id' => $client->id,
            'currency' => 'JOD',
            'status' => CreditNote::STATUS_ISSUED,
            'issue_date' => '2026-09-14',
            'subtotal_minor' => 5000,
            'tax_minor' => 0,
            'total_minor' => 5000,
            'reason' => 'Historical',
            'created_by' => $admin->id,
        ]);
        $refund = Refund::create([
            'refund_number' => 'RF-HIST-1',
            'client_id' => $client->id,
            'credit_note_id' => $credit->id,
            'currency' => 'JOD',
            'amount_minor' => 3000,
            'refund_method' => 'cash',
            'reason' => 'Historical refund',
            'refunded_at' => '2026-09-14 12:00:00',
            'created_by' => $admin->id,
        ]);
        $account = $this->createFinancialAccount($admin, 'historical_refund_cash');
        $setup = app(AccountingSetupService::class);
        $e1 = app(JournalPostingService::class)->post([
            'event_type' => 'refund_issued',
            'event_key' => 'cash:refund:'.$refund->id.':issued',
            'source_type' => Refund::class,
            'source_id' => $refund->id,
            'entry_date' => $refund->refunded_at,
            'description' => 'Historical E1 refund',
            'created_by' => $admin->id,
        ], [
            ['chart_account_id' => $setup->systemAccount('billing_clearing')->id, 'debit_minor' => 3000, 'credit_minor' => 0, 'client_id' => $client->id],
            ['chart_account_id' => $account->chart_account_id, 'debit_minor' => 0, 'credit_minor' => 3000, 'financial_account_id' => $account->id, 'client_id' => $client->id],
        ]);

        $reclass = app(BillingAccountingService::class)->postHistoricalCreditNoteRefundReclassification($refund);
        $this->assertNotNull($reclass);
        $this->assertSame(JournalEntry::STATUS_POSTED, $e1->fresh()->status);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $reclass->id, 'chart_account_id' => $setup->systemAccount('customer_credits')->id, 'debit_minor' => 3000]);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $reclass->id, 'chart_account_id' => $setup->systemAccount('billing_clearing')->id, 'credit_minor' => 3000]);
    }

    public function test_billing_reconciliation_backfill_idempotency_and_partner_access(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        [, $partnerUser] = $this->createPartnerUser();
        $client = $this->createClient();
        $invoice = $this->createInvoice($admin, $client, 100000, 16000);
        $account = $this->createFinancialAccount($admin, 'recon_cash');
        app(PaymentAllocationService::class)->recordV2Payment($client, [
            'amount' => '80.000',
            'financial_account_id' => $account->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
        ], [['invoice_id' => $invoice->id, 'amount' => '60.000']], false, $admin->id);
        $credit = app(CreditNoteService::class)->createIssued($client, [
            'issue_date' => '2026-09-14',
            'original_invoice_id' => $invoice->id,
            'reason' => 'Partial credit',
            'lines' => [['description' => 'Partial credit', 'subtotal_jod' => '10.000', 'tax_jod' => '1.600']],
        ], $admin->id);
        app(CreditNoteService::class)->applyCredit($credit, $invoice, '5.000', $admin->id);
        app(RefundService::class)->refundFromCreditNote($credit, [
            'amount' => '2.000',
            'financial_account_id' => $account->id,
            'refund_method' => 'cash',
            'refunded_at' => '2026-09-14 11:00:00',
            'reason' => 'Credit refund',
        ], $admin->id);

        $result = app(AccountingReconciliationService::class)->run();
        $this->assertTrue($result['ok'], json_encode($result['failures']));
        $this->assertSame(51000, $result['billing']['accounts_receivable']['general_ledger_minor']);
        $this->assertSame(20000, $result['billing']['billing_clearing']['general_ledger_minor']);
        $this->assertSame(4600, $result['billing']['customer_credits']['general_ledger_minor']);
        $this->assertSame(14400, $result['billing']['sales_tax_payable']['general_ledger_minor']);
        $this->assertSame(90000, $result['billing']['deferred_revenue']['general_ledger_minor']);

        $before = JournalEntry::count();
        $this->artisan('finance:backfill-accounting-ledger --dry-run')->assertExitCode(0);
        $this->assertSame($before, JournalEntry::count());
        $this->artisan('finance:backfill-accounting-ledger')->assertExitCode(0);
        $this->assertSame($before, JournalEntry::count());

        $this->actingAs($partnerUser)->get(route('accounting.index'))->assertForbidden();
    }

    private function createInvoice(User $admin, Client $client, int $preTaxMinor, int $taxMinor): Invoice
    {
        return app(InvoiceService::class)->createIssuedOneTime($client, [[
            'line_type' => InvoiceLine::TYPE_ONE_TIME_SERVICE,
            'description_snapshot' => 'E2A service',
            'quantity' => 1,
            'unit_price_minor' => $preTaxMinor,
            'subtotal_minor' => $preTaxMinor,
            'discount_minor' => 0,
            'tax_rate_bps' => 1600,
            'tax_minor' => $taxMinor,
            'total_minor' => $preTaxMinor + $taxMinor,
            'sort_order' => 10,
        ]], Carbon::parse('2026-09-14'), Carbon::parse('2026-09-30'), 'E2A invoice', $admin->id);
    }

    private function createFinancialAccount(User $admin, string $code = 'cash_e2a'): FinancialAccount
    {
        $account = app(FinancialAccountService::class)->createAccount([
            'code' => $code,
            'name_ar' => $code,
            'type' => FinancialAccount::TYPE_CASH,
            'opening_balance' => '0.000',
            'opening_date' => '2026-09-14 09:00:00',
        ], $admin->id);

        app(AccountingSetupService::class)->ensureFinancialAccountMapping($account);

        return $account->fresh();
    }

    private function createClient(): Client
    {
        return Client::create([
            'business_name' => 'Phase E2A Client',
            'phone' => '0790000000',
            'city_area' => 'Amman',
            'business_category' => 'Restaurant',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ]);
    }

    private function createPartnerUser(): array
    {
        return [null, User::factory()->create(['role' => 'external'])];
    }
}
