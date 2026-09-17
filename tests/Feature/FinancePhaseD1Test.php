<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\FinancialAccount;
use App\Models\FinancialTransfer;
use App\Models\FinancialTransferReversal;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ReceivableService;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancePhaseD1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    public function test_admin_can_create_financial_account_with_exact_opening_balance_and_partner_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $partnerUser] = $this->createPartnerUser();

        $this->actingAs($admin)->post(route('financial-accounts.store'), [
            'code' => 'cash_box',
            'name_ar' => 'صندوق الشركة',
            'type' => FinancialAccount::TYPE_CASH,
            'opening_balance' => '0.001',
            'opening_date' => '2026-09-14 09:00:00',
        ])->assertSessionHas('success');

        $account = FinancialAccount::firstOrFail();
        $this->assertSame('JOD', $account->currency);
        $this->assertFalse(SchemaRouteInspector::hasDeleteRouteContaining('financial-accounts'));
        $this->assertDatabaseHas('cash_movements', [
            'financial_account_id' => $account->id,
            'event_type' => CashMovement::EVENT_OPENING_BALANCE,
            'direction' => CashMovement::DIRECTION_INFLOW,
            'amount_minor' => 1,
        ]);
        $this->assertDatabaseHas('activity_logs', ['type' => 'financial_account_created']);

        $this->actingAs($partnerUser)->post(route('financial-accounts.store'), [
            'code' => 'blocked',
            'name_ar' => 'Blocked',
            'type' => FinancialAccount::TYPE_CASH,
        ])->assertForbidden();
    }

    public function test_new_v2_payment_requires_active_destination_account_and_posts_one_full_cash_inflow(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $invoice = $this->createInvoice($client, 100000, '2026-09-14');
        $activeAccount = $this->createAccount($admin, 'cash', 'Cash', '50.000');
        $archivedAccount = $this->createAccount($admin, 'old_cash', 'Old Cash');
        $this->actingAs($admin)->post(route('financial-accounts.archive', $archivedAccount))->assertSessionHas('success');

        $this->actingAs($admin)->post(route('clients.collections.payments.store', $client), [
            'amount' => '120.000',
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
        ])->assertSessionHasErrors('financial_account_id');

        $this->actingAs($admin)->post(route('clients.collections.payments.store', $client), [
            'amount' => '120.000',
            'financial_account_id' => $archivedAccount->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
        ])->assertSessionHasErrors('financial_account_id');

        $this->actingAs($admin)->post(route('clients.collections.payments.store', $client), [
            'amount' => '120.000',
            'financial_account_id' => $activeAccount->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
            'allocations' => [
                ['invoice_id' => $invoice->id, 'amount' => '100.000'],
            ],
        ])->assertSessionHas('success');

        $payment = Payment::firstOrFail();
        $this->assertSame(2, CashMovement::count());
        $this->assertDatabaseHas('cash_movements', [
            'financial_account_id' => $activeAccount->id,
            'event_type' => CashMovement::EVENT_PAYMENT_RECEIVED,
            'direction' => CashMovement::DIRECTION_INFLOW,
            'amount_minor' => 120000,
            'source_type' => Payment::class,
            'source_id' => $payment->id,
        ]);
        $this->assertSame(1, PaymentAllocation::count());
        $this->assertSame(20000, app(ReceivableService::class)->paymentUnallocatedMinor($payment));
        $this->assertSame(170000, app(\App\Services\FinancialAccountBalanceService::class)->currentBalanceMinor($activeAccount->fresh()));
    }

    public function test_payment_reversal_posts_equal_outflow_only_when_original_receipt_movement_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $account = $this->createAccount($admin, 'bank', 'Bank');
        $payment = $this->recordPayment($admin, $client, '10.000', $account);

        $this->actingAs($admin)->post(route('payments.reverse', $payment), [
            'reason' => 'Wrong receipt',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('cash_movements', [
            'financial_account_id' => $account->id,
            'event_type' => CashMovement::EVENT_PAYMENT_RECEIVED,
            'direction' => CashMovement::DIRECTION_INFLOW,
            'amount_minor' => 10000,
        ]);
        $this->assertDatabaseHas('cash_movements', [
            'financial_account_id' => $account->id,
            'event_type' => CashMovement::EVENT_PAYMENT_REVERSAL,
            'direction' => CashMovement::DIRECTION_OUTFLOW,
            'amount_minor' => 10000,
        ]);
        $this->assertSame(0, app(\App\Services\FinancialAccountBalanceService::class)->currentBalanceMinor($account->fresh()));

        $historical = Payment::create([
            'client_id' => $client->id,
            'amount' => '5.000',
            'amount_minor' => 5000,
            'currency' => 'JOD',
            'payment_engine_version' => Payment::ENGINE_V2,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 11:00:00',
            'paid_at' => '2026-09-14 11:00:00',
            'recorded_by' => $admin->id,
        ]);
        $this->actingAs($admin)->post(route('payments.reverse', $historical), [
            'reason' => 'Historical correction',
        ])->assertSessionHas('success');

        $this->assertSame(1, CashMovement::where('event_type', CashMovement::EVENT_PAYMENT_REVERSAL)->count());
    }

    public function test_refund_requires_source_account_posts_outflow_and_may_use_different_account_than_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient(['status' => 'subscriber', 'stage' => ClientLifecycle::SUBSCRIBER]);
        $invoice = $this->createInvoice($client, 100000, '2026-09-14');
        $receiptAccount = $this->createAccount($admin, 'receipt_bank', 'Receipt Bank');
        $refundAccount = $this->createAccount($admin, 'refund_wallet', 'Refund Wallet', '25.000');
        $payment = $this->recordPayment($admin, $client, '120.000', $receiptAccount, [
            ['invoice_id' => $invoice->id, 'amount' => '100.000'],
        ]);

        $this->actingAs($admin)->post(route('payments.refunds.store', $payment), [
            'amount' => '15.000',
            'refund_method' => 'cash',
            'refunded_at' => '2026-09-14 13:00:00',
            'reason' => 'Missing account',
        ])->assertSessionHasErrors('financial_account_id');

        $this->actingAs($admin)->post(route('payments.refunds.store', $payment), [
            'amount' => '15.000',
            'financial_account_id' => $refundAccount->id,
            'refund_method' => 'cash',
            'refunded_at' => '2026-09-14 13:00:00',
            'reason' => 'Refund overpayment',
        ])->assertSessionHas('success');

        $refund = Refund::firstOrFail();
        $this->assertDatabaseHas('cash_movements', [
            'financial_account_id' => $refundAccount->id,
            'event_type' => CashMovement::EVENT_REFUND_ISSUED,
            'direction' => CashMovement::DIRECTION_OUTFLOW,
            'amount_minor' => 15000,
            'source_type' => Refund::class,
            'source_id' => $refund->id,
        ]);
        $this->assertSame(120000, app(\App\Services\FinancialAccountBalanceService::class)->currentBalanceMinor($receiptAccount->fresh()));
        $this->assertSame(10000, app(\App\Services\FinancialAccountBalanceService::class)->currentBalanceMinor($refundAccount->fresh()));
    }

    public function test_historical_payments_and_refunds_can_be_assigned_once_and_queue_excludes_legacy_and_unposted_reversed_payments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $account = $this->createAccount($admin, 'assignment_bank', 'Assignment Bank');
        $historicalPayment = Payment::create([
            'client_id' => $client->id,
            'amount' => '12.000',
            'amount_minor' => 12000,
            'currency' => 'JOD',
            'payment_engine_version' => Payment::ENGINE_V2,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
            'paid_at' => '2026-09-14 10:00:00',
            'recorded_by' => $admin->id,
        ]);
        Payment::create([
            'client_id' => $client->id,
            'amount' => '8.000',
            'payment_method' => 'cash',
            'paid_at' => '2026-09-14 10:00:00',
            'recorded_by' => $admin->id,
        ]);
        $reversedHistorical = Payment::create([
            'client_id' => $client->id,
            'amount' => '5.000',
            'amount_minor' => 5000,
            'currency' => 'JOD',
            'payment_engine_version' => Payment::ENGINE_V2,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 11:00:00',
            'paid_at' => '2026-09-14 11:00:00',
            'recorded_by' => $admin->id,
        ]);
        $this->actingAs($admin)->post(route('payments.reverse', $reversedHistorical), [
            'reason' => 'Unposted historical reversal',
        ])->assertSessionHas('success');
        $refund = Refund::create([
            'client_id' => $client->id,
            'currency' => 'JOD',
            'amount_minor' => 3000,
            'refund_method' => 'cash',
            'reason' => 'Historical refund',
            'refunded_at' => '2026-09-14 12:00:00',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('financial-accounts.index'))
            ->assertOk()
            ->assertSee('12.000')
            ->assertSee('3.000')
            ->assertDontSee('8.000')
            ->assertDontSee('5.000');

        $this->actingAs($admin)->post(route('cash-events.assign-account'), [
            'event_type' => 'payment',
            'event_id' => $historicalPayment->id,
            'financial_account_id' => $account->id,
        ])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('cash-events.assign-account'), [
            'event_type' => 'refund',
            'event_id' => $refund->id,
            'financial_account_id' => $account->id,
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post(route('cash-events.assign-account'), [
            'event_type' => 'payment',
            'event_id' => $historicalPayment->id,
            'financial_account_id' => $account->id,
        ])->assertSessionHasErrors('payment_id');
        $this->assertSame(2, CashMovement::count());
        $this->assertDatabaseHas('activity_logs', ['type' => 'cash_event_account_assigned']);
    }

    public function test_financial_transfer_and_reversal_change_account_balances_but_not_company_cash(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $from = $this->createAccount($admin, 'cash_transfer', 'Cash Transfer', '100.000');
        $to = $this->createAccount($admin, 'wallet_transfer', 'Wallet Transfer', '10.000');
        $balances = app(\App\Services\FinancialAccountBalanceService::class);
        $companyBefore = $balances->companyCashMinor();

        $this->actingAs($admin)->post(route('financial-transfers.store'), [
            'from_financial_account_id' => $from->id,
            'to_financial_account_id' => $from->id,
            'amount' => '1.000',
            'transferred_at' => '2026-09-14 12:00:00',
        ])->assertSessionHasErrors('to_financial_account_id');

        $this->actingAs($admin)->post(route('financial-transfers.store'), [
            'from_financial_account_id' => $to->id,
            'to_financial_account_id' => $from->id,
            'amount' => '11.000',
            'transferred_at' => '2026-09-14 12:00:00',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($admin)->post(route('financial-transfers.store'), [
            'from_financial_account_id' => $from->id,
            'to_financial_account_id' => $to->id,
            'amount' => '25.000',
            'transferred_at' => '2026-09-14 12:00:00',
            'reference' => 'TR-REF',
        ])->assertSessionHas('success');

        $transfer = FinancialTransfer::firstOrFail();
        $this->assertStringStartsWith('TR-2026-', $transfer->transfer_number);
        $this->assertSame(75000, $balances->currentBalanceMinor($from->fresh()));
        $this->assertSame(35000, $balances->currentBalanceMinor($to->fresh()));
        $this->assertSame($companyBefore, $balances->companyCashMinor());

        $this->actingAs($admin)->post(route('financial-transfers.reverse', $transfer), [
            'reason' => 'Wrong destination',
        ])->assertSessionHas('success');

        $this->assertSame(1, FinancialTransferReversal::count());
        $this->assertSame(100000, $balances->currentBalanceMinor($from->fresh()));
        $this->assertSame(10000, $balances->currentBalanceMinor($to->fresh()));
        $this->assertSame($companyBefore, $balances->companyCashMinor());
        $this->assertSame(6, CashMovement::count());
        $this->actingAs($admin)->post(route('financial-transfers.reverse', $transfer), [
            'reason' => 'Try again',
        ])->assertSessionHasErrors('financial_transfer_id');
    }

    public function test_allocations_credit_notes_and_credit_applications_do_not_create_cash_movements(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $invoice = $this->createInvoice($client, 100000, '2026-09-14');
        $account = $this->createAccount($admin, 'cash_no_extra', 'Cash No Extra');
        $payment = $this->recordPayment($admin, $client, '40.000', $account);
        $movementCount = CashMovement::count();

        $this->actingAs($admin)->post(route('payments.allocations.store', $payment), [
            'invoice_id' => $invoice->id,
            'amount' => '40.000',
        ])->assertSessionHas('success');
        $allocation = PaymentAllocation::firstOrFail();
        $this->actingAs($admin)->post(route('payment-allocations.reverse', $allocation), [
            'reason' => 'Allocation correction',
        ])->assertSessionHas('success');

        $creditNote = $this->createCreditNote($admin, $client, $invoice);
        $this->actingAs($admin)->post(route('credit-notes.applications.store', $creditNote), [
            'invoice_id' => $invoice->id,
            'amount' => '10.000',
        ])->assertSessionHas('success');

        $this->assertSame($movementCount, CashMovement::count());
    }

    public function test_partner_cannot_access_cash_management_operations(): void
    {
        [, $partnerUser] = $this->createPartnerUser();

        $this->actingAs($partnerUser)->get(route('financial-accounts.index'))->assertForbidden();
        $this->actingAs($partnerUser)->post(route('financial-accounts.store'), [
            'code' => 'blocked',
            'name_ar' => 'Blocked',
            'type' => FinancialAccount::TYPE_CASH,
        ])->assertForbidden();
    }

    private function createAccount(User $admin, string $code, string $name, string $openingBalance = '0.000'): FinancialAccount
    {
        $this->actingAs($admin)->post(route('financial-accounts.store'), [
            'code' => $code,
            'name_ar' => $name,
            'type' => FinancialAccount::TYPE_CASH,
            'opening_balance' => $openingBalance,
            'opening_date' => '2026-09-14 09:00:00',
        ])->assertSessionHas('success');

        return FinancialAccount::where('code', $code)->firstOrFail();
    }

    private function recordPayment(User $admin, Client $client, string $amount, FinancialAccount $account, array $allocations = []): Payment
    {
        $payload = [
            'amount' => $amount,
            'financial_account_id' => $account->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
        ];
        if ($allocations !== []) {
            $payload['allocations'] = $allocations;
        }

        $this->actingAs($admin)->post(route('clients.collections.payments.store', $client), $payload)
            ->assertSessionHas('success');

        return Payment::where('client_id', $client->id)->orderByDesc('id')->firstOrFail();
    }

    private function createCreditNote(User $admin, Client $client, Invoice $invoice): CreditNote
    {
        $this->actingAs($admin)->post(route('clients.credit-notes.store', $client), [
            'issue_date' => '2026-09-14',
            'original_invoice_id' => $invoice->id,
            'reason' => 'Cash-neutral credit',
            'lines' => [
                ['description' => 'Cash-neutral credit', 'subtotal_jod' => '10.000', 'tax_jod' => '0.000'],
            ],
        ])->assertSessionHas('success');

        return CreditNote::where('client_id', $client->id)->orderByDesc('id')->firstOrFail();
    }

    private function createClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'Phase D1 Client',
            'phone' => '0790000000',
            'city_area' => 'Amman',
            'business_category' => 'Restaurant',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ], $overrides));
    }

    private function createInvoice(Client $client, int $totalMinor, string $dueDate): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-D1-'.str_pad((string) (Invoice::count() + 1), 6, '0', STR_PAD_LEFT),
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
        ]);

        $invoice->lines()->create([
            'line_type' => InvoiceLine::TYPE_CUSTOM,
            'description_snapshot' => 'D1 test line',
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
        $partner = Partner::create([
            'company_name' => 'Phase D1 Partner',
            'email' => 'phase-d1-partner@example.com',
        ]);

        $user = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        return [$partner, $user];
    }
}

class SchemaRouteInspector
{
    public static function hasDeleteRouteContaining(string $needle): bool
    {
        return collect(app('router')->getRoutes())
            ->map(fn ($route) => ['methods' => $route->methods(), 'uri' => $route->uri()])
            ->contains(fn (array $route) => in_array('DELETE', $route['methods'], true) && str_contains($route['uri'], $needle));
    }
}
