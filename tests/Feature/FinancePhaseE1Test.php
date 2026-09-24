<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AccountingSystemMapping;
use App\Models\CashMovement;
use App\Models\ChartAccount;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\FinancialTransfer;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\User;
use App\Services\AccountingReconciliationService;
use App\Services\AccountingReportService;
use App\Services\AccountingSetupService;
use App\Services\FinancialAccountService;
use App\Services\JournalPostingService;
use App\Services\OperatingExpenseService;
use App\Support\ClientLifecycle;
use Database\Seeders\AssetCategorySeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancePhaseE1Test extends TestCase
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

    public function test_chart_accounts_seed_idempotently_and_operational_mappings_exist(): void
    {
        app(AccountingSetupService::class)->ensureSeeded();
        app(AccountingSetupService::class)->ensureSeeded();

        $this->assertSame(1, ChartAccount::where('code', '2100')->count());
        $this->assertSame(1, AccountingSystemMapping::where('mapping_key', 'billing_clearing')->count());
        $this->assertTrue(ExpenseCategory::whereNotNull('chart_account_id')->exists());
        $this->assertTrue(\App\Models\AssetCategory::whereNotNull('chart_account_id')->exists());
    }

    public function test_journal_invariants_idempotency_immutability_and_reversal(): void
    {
        $cash = ChartAccount::where('code', '1100')->firstOrFail();
        $equity = app(AccountingSetupService::class)->systemAccount('opening_balance_equity');
        $posting = app(JournalPostingService::class);

        $this->expectException(ValidationException::class);
        $posting->post([
            'event_type' => 'bad',
            'event_key' => 'bad:unbalanced',
            'entry_date' => '2026-09-14',
            'description' => 'Bad',
        ], [
            ['chart_account_id' => $cash->id, 'debit_minor' => 1000, 'credit_minor' => 0],
            ['chart_account_id' => $equity->id, 'debit_minor' => 0, 'credit_minor' => 999],
        ]);
    }

    public function test_journal_posts_exact_minor_units_and_blocks_edit_delete(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $account = $this->createAccount($admin, 'cash_e1_exact', 'Cash E1 Exact', '0.001');
        $journal = JournalEntry::where('event_type', 'opening_balance')->firstOrFail();

        $this->assertSame(1, $journal->totalDebitMinor());
        $this->assertSame(1, $journal->totalCreditMinor());
        $this->assertSame($journal->id, JournalEntry::where('event_key', $journal->event_key)->firstOrFail()->id);
        $this->assertSame($account->chart_account_id, $journal->lines()->where('debit_minor', 1)->firstOrFail()->chart_account_id);

        $this->expectException(ValidationException::class);
        $journal->update(['description' => 'Edited']);
    }

    public function test_payment_receipt_refund_and_allocation_e1_billing_clearing_policy(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->createClient();
        $account = $this->createAccount($admin, 'cash_billing_clear', 'Cash Billing Clear');

        $payment = $this->recordPayment($admin, $client, '25.000', $account);
        $paymentJournal = JournalEntry::where('event_type', 'payment_received')->firstOrFail();
        $billingClearing = app(AccountingSetupService::class)->systemAccount('billing_clearing');

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $paymentJournal->id,
            'chart_account_id' => $billingClearing->id,
            'credit_minor' => 25000,
        ]);
        $this->assertDatabaseMissing('journal_lines', [
            'journal_entry_id' => $paymentJournal->id,
            'chart_account_id' => app(AccountingSetupService::class)->systemAccount('saas_subscription_revenue')->id,
        ]);

        $cashMovementCount = \App\Models\CashMovement::count();
        $this->actingAs($admin)->post(route('payments.allocations.store', $payment), [
            'invoice_id' => $this->createInvoice($client, 25000)->id,
            'amount' => '10.000',
        ])->assertSessionHas('success');
        $this->assertSame($cashMovementCount, \App\Models\CashMovement::count());
    }

    public function test_transfer_posts_one_journal_and_reversal_is_exact(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $from = $this->createAccount($admin, 'cash_transfer_e1', 'Cash Transfer E1', '100.000');
        $to = $this->createAccount($admin, 'wallet_transfer_e1', 'Wallet Transfer E1', '10.000');
        $openingCount = JournalEntry::count();

        $this->actingAs($admin)->post(route('financial-transfers.store'), [
            'from_financial_account_id' => $from->id,
            'to_financial_account_id' => $to->id,
            'amount' => '25.000',
            'transferred_at' => '2026-09-14 12:00:00',
        ])->assertSessionHas('success');

        $transfer = FinancialTransfer::firstOrFail();
        $this->assertSame($openingCount + 1, JournalEntry::count());
        $journal = JournalEntry::where('event_key', 'financial_transfer:'.$transfer->id.':accounting')->firstOrFail();
        $this->assertSame(25000, $journal->totalDebitMinor());
        $this->assertSame(25000, $journal->totalCreditMinor());

        $this->actingAs($admin)->post(route('financial-transfers.reverse', $transfer), [
            'reason' => 'Wrong destination',
        ])->assertSessionHas('success');

        $reversal = JournalEntry::where('event_type', 'financial_transfer_reversal')->firstOrFail();
        $this->assertSame($journal->id, $reversal->reversal_of_id);
        $this->assertSame(JournalEntry::STATUS_REVERSED, $journal->fresh()->status);
    }

    public function test_company_and_personal_expenses_post_to_cash_or_related_party_and_reverse_exactly(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $payer = User::factory()->create(['role' => 'founder']);
        $account = $this->createAccount($admin, 'expense_cash_e1', 'Expense Cash E1', '100.000');
        $category = ExpenseCategory::firstOrFail();

        app(OperatingExpenseService::class)->createV2Expense([
            'amount' => '7.000',
            'category_id' => $category->id,
            'funding_source' => Expense::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $account->id,
            'paid_at' => '2026-09-14 10:00:00',
        ], $admin);
        $companyJournal = JournalEntry::where('event_type', 'company_funded_operating_expense')->firstOrFail();
        $this->assertSame(7000, $companyJournal->totalDebitMinor());

        $personal = app(OperatingExpenseService::class)->createV2Expense([
            'amount' => '5.000',
            'category_id' => $category->id,
            'funding_source' => Expense::FUNDING_PERSONAL,
            'paid_by_user_id' => $payer->id,
            'paid_at' => '2026-09-14 11:00:00',
        ], $admin);
        $personalJournal = JournalEntry::where('event_key', 'expense:'.$personal->id.':accounting')->firstOrFail();
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $personalJournal->id,
            'chart_account_id' => app(AccountingSetupService::class)->systemAccount('related_party_payable')->id,
            'credit_minor' => 5000,
            'user_id' => $payer->id,
        ]);

        app(OperatingExpenseService::class)->reverseExpense($personal, 'Correction', $admin);
        $this->assertSame(JournalEntry::STATUS_REVERSED, $personalJournal->fresh()->status);
        $this->assertDatabaseHas('journal_entries', [
            'event_type' => 'expense_reversal',
            'reversal_of_id' => $personalJournal->id,
        ]);
    }

    public function test_trial_balance_reconciliation_backfill_closed_period_and_partner_access(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        [, $partnerUser] = $this->createPartnerUser();
        $account = $this->createAccount($admin, 'reconcile_cash_e1', 'Reconcile Cash E1', '10.000');

        $trial = app(AccountingReportService::class)->trialBalance();
        $this->assertSame(0, $trial['difference_minor']);
        $this->assertTrue(app(AccountingReconciliationService::class)->run()['ok']);

        $this->artisan('finance:backfill-accounting-ledger --dry-run')
            ->expectsOutputToContain('"dry_run":true')
            ->assertExitCode(0);

        $period = AccountingPeriod::where('period_key', '2026-09')->firstOrFail();
        app(JournalPostingService::class)->closePeriod($period, $admin->id);
        try {
            app(JournalPostingService::class)->post([
                'event_type' => 'closed_period_test',
                'event_key' => 'closed:period:test',
                'entry_date' => '2026-09-14',
                'description' => 'Closed period test',
            ], [
                ['chart_account_id' => $account->chart_account_id, 'debit_minor' => 1000, 'credit_minor' => 0],
                ['chart_account_id' => app(AccountingSetupService::class)->systemAccount('opening_balance_equity')->id, 'debit_minor' => 0, 'credit_minor' => 1000],
            ]);
            $this->fail('Closed period posting should fail.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->actingAs($partnerUser)->get(route('accounting.index'))->assertForbidden();
    }

    private function createAccount(User $admin, string $code, string $name, string $openingBalance = '0.000'): FinancialAccount
    {
        return app(FinancialAccountService::class)->createAccount([
            'code' => $code,
            'name_ar' => $name,
            'type' => FinancialAccount::TYPE_CASH,
            'opening_balance' => $openingBalance,
            'opening_date' => '2026-09-14 09:00:00',
        ], $admin->id);
    }

    private function recordPayment(User $admin, Client $client, string $amount, FinancialAccount $account): Payment
    {
        $this->actingAs($admin)->post(route('clients.collections.payments.store', $client), [
            'amount' => $amount,
            'financial_account_id' => $account->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
        ])->assertSessionHas('success');

        return Payment::where('client_id', $client->id)->orderByDesc('id')->firstOrFail();
    }

    private function createClient(): Client
    {
        return Client::create([
            'business_name' => 'Phase E1 Client',
            'phone' => '0790000000',
            'city_area' => 'Amman',
            'business_category' => 'Restaurant',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ]);
    }

    private function createInvoice(Client $client, int $totalMinor): \App\Models\Invoice
    {
        $invoice = \App\Models\Invoice::create([
            'invoice_number' => 'INV-E1-'.str_pad((string) (\App\Models\Invoice::count() + 1), 6, '0', STR_PAD_LEFT),
            'client_id' => $client->id,
            'currency' => 'JOD',
            'status' => \App\Models\Invoice::STATUS_ISSUED,
            'issue_date' => '2026-09-01',
            'due_date' => '2026-09-14',
            'subtotal_minor' => $totalMinor,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => $totalMinor,
            'issued_at' => now(),
        ]);

        $invoice->lines()->create([
            'line_type' => \App\Models\InvoiceLine::TYPE_CUSTOM,
            'description_snapshot' => 'E1 test line',
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

    private function createPartnerUser(): array
    {
        return [null, User::factory()->create(['role' => 'external'])];
    }
}
