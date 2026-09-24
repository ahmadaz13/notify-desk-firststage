<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Client;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentReceiptConfirmation;
use App\Models\User;
use App\Services\AccountingSetupService;
use App\Services\BillingAccountingService;
use App\Services\FinancialAccountBalanceService;
use App\Services\PaymentReceiptService;
use App\Services\ReceivableService;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

/**
 * P1 — Staff payment receipt confirmation, Owner approval/rejection, owner direct payment [FROZEN D-15, §9].
 */
class V1P1PaymentReceiptApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $founder;
    protected User $admin;
    protected User $staff;
    protected User $otherStaff;
    protected Client $client;
    protected FinancialAccount $cashBox;
    protected FinancialAccount $cliq;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-23 12:00:00');

        $this->seed(SettingsSeeder::class);
        app(AccountingSetupService::class)->ensureSeeded();

        $this->founder = User::factory()->create(['role' => 'founder', 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => 'founder', 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $this->otherStaff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $this->client = Client::create([
            'business_name' => 'P1 Receipt Client',
            'phone' => '0790000000',
            'city_area' => 'Amman',
            'business_category' => 'Restaurant',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
            'stage' => ClientLifecycle::SUBSCRIBER,
        ]);

        $this->cashBox = FinancialAccount::where('code', 'CASH-BOX')->firstOrFail();
        $this->cliq = FinancialAccount::where('code', 'CLIQ')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Staff submission ────────────────────────────────────────────────

    public function test_staff_submission_creates_pending_receipt_with_zero_financial_effect(): void
    {
        $invoice = $this->createInvoice(25000, '2026-09-01');
        $before = $this->financialSnapshot();

        $this->actingAs($this->staff)
            ->post(route('clients.payment-receipts.store', $this->client), [
                'amount' => '25.000',
                'payment_method' => 'cash',
                'reference' => 'R-1',
                'note' => 'Collected at the shop',
            ])
            ->assertRedirect(route('clients.show', $this->client))
            ->assertSessionHas('success');

        $receipt = PaymentReceiptConfirmation::sole();
        $this->assertSame(PaymentReceiptConfirmation::STATUS_PENDING, $receipt->status);
        $this->assertSame(25000, $receipt->amount_minor);
        $this->assertSame('JOD', $receipt->currency);
        $this->assertSame('cash', $receipt->payment_method);
        $this->assertSame('2026-09-23 12:00:00', $receipt->received_at->format('Y-m-d H:i:s'));
        $this->assertSame($this->staff->id, (int) $receipt->submitted_by);
        $this->assertNull($receipt->payment_id);
        $this->assertNotEmpty($receipt->idempotency_key);

        $this->assertSame($before, $this->financialSnapshot());
        $this->assertSame(25000, app(ReceivableService::class)->invoiceOutstandingMinor($invoice->fresh()));
    }

    public function test_pending_receipt_does_not_change_amount_due_and_is_visible_to_staff(): void
    {
        $this->createInvoice(40000, '2026-09-01');
        $this->submitAsStaff('40.000');

        $this->assertSame(40000, app(ReceivableService::class)->clientSummary($this->client->fresh())['total_outstanding_minor']);

        $this->actingAs($this->staff)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee('40.000', false)
            ->assertSee(__('notify.payment_receipts.status.pending'))
            ->assertSee(__('notify.payment_receipts.action_payment_received'))
            ->assertSee(route('clients.payment-receipts.store', $this->client), false)
            ->assertDontSee(route('clients.payments.normal.store', $this->client), false);
    }

    public function test_staff_backdating_more_than_seven_days_is_rejected(): void
    {
        $this->actingAs($this->staff)
            ->post(route('clients.payment-receipts.store', $this->client), [
                'amount' => '5.000',
                'payment_method' => 'cash',
                'received_at' => '2026-09-16 11:59:00',
            ])
            ->assertSessionHasErrors('received_at');

        $this->actingAs($this->staff)
            ->post(route('clients.payment-receipts.store', $this->client), [
                'amount' => '5.000',
                'payment_method' => 'cash',
                'received_at' => '2026-09-16 12:00:00',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, PaymentReceiptConfirmation::count());
    }

    public function test_future_received_at_is_rejected_for_staff(): void
    {
        $this->actingAs($this->staff)
            ->post(route('clients.payment-receipts.store', $this->client), [
                'amount' => '5.000',
                'payment_method' => 'cliq',
                'received_at' => '2026-09-23 12:05:00',
            ])
            ->assertSessionHasErrors('received_at');

        $this->assertSame(0, PaymentReceiptConfirmation::count());
    }

    public function test_staff_submission_rejects_unsupported_method_and_non_positive_amount(): void
    {
        foreach (['bank_transfer', 'zain_cash', 'orange_money', 'e_wallet', 'other'] as $method) {
            $this->actingAs($this->staff)
                ->post(route('clients.payment-receipts.store', $this->client), ['amount' => '5.000', 'payment_method' => $method])
                ->assertSessionHasErrors('payment_method');
        }

        foreach (['0', '0.000', '-1.000', 'abc'] as $amount) {
            $this->actingAs($this->staff)
                ->post(route('clients.payment-receipts.store', $this->client), ['amount' => $amount, 'payment_method' => 'cash'])
                ->assertSessionHasErrors('amount');
        }

        $this->assertSame(0, PaymentReceiptConfirmation::count());
    }

    public function test_repeated_submission_with_same_idempotency_key_creates_one_receipt(): void
    {
        $payload = ['amount' => '5.000', 'payment_method' => 'cash', '_idempotency_key' => 'staff-form-1'];

        $this->actingAs($this->staff)->post(route('clients.payment-receipts.store', $this->client), $payload)->assertSessionHas('success');
        $this->actingAs($this->staff)->post(route('clients.payment-receipts.store', $this->client), $payload)->assertRedirect();

        $service = app(PaymentReceiptService::class);
        $again = $service->submit($this->client, ['amount' => '5.000', 'payment_method' => 'cash'], $this->staff, 'staff-form-1');

        $this->assertSame(1, PaymentReceiptConfirmation::count());
        $this->assertSame(PaymentReceiptConfirmation::sole()->id, $again->id);
    }

    public function test_database_enforces_receipt_invariants(): void
    {
        $base = [
            'client_id' => $this->client->id,
            'amount_minor' => 1000,
            'currency' => 'JOD',
            'payment_method' => 'cash',
            'received_at' => now(),
            'status' => 'pending',
            'submitted_by' => $this->staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ([['amount_minor' => 0], ['amount_minor' => -5], ['currency' => 'USD'], ['payment_method' => 'bank_transfer'], ['status' => 'posted']] as $i => $override) {
            try {
                DB::table('payment_receipt_confirmations')->insert(array_merge($base, $override, ['idempotency_key' => 'db-'.$i]));
                $this->fail('Invalid receipt row must be rejected: '.json_encode($override));
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        DB::table('payment_receipt_confirmations')->insert($base + ['idempotency_key' => 'dup']);
        $this->expectException(QueryException::class);
        DB::table('payment_receipt_confirmations')->insert($base + ['idempotency_key' => 'dup']);
    }

    // ── Owner approval ──────────────────────────────────────────────────

    public function test_owner_approval_creates_exactly_one_payment_with_cash_movement_journals_and_allocation(): void
    {
        $oldest = $this->createInvoice(10000, '2026-09-01');
        $newest = $this->createInvoice(10000, '2026-09-10');
        $receipt = $this->submitAsStaff('15.000', 'cash', ['received_at' => '2026-09-20 09:30:00', 'reference' => 'SHOP-9', 'note' => 'Front desk']);

        $this->actingAs($this->admin)
            ->post(route('payment-receipts.approve', $receipt))
            ->assertSessionHas('success');

        $receipt->refresh();
        $payment = Payment::sole();

        // Receipt review state.
        $this->assertSame(PaymentReceiptConfirmation::STATUS_APPROVED, $receipt->status);
        $this->assertSame($payment->id, (int) $receipt->payment_id);
        $this->assertSame($this->admin->id, (int) $receipt->reviewed_by);
        $this->assertNotNull($receipt->reviewed_at);

        // Payment mirrors the receipt; approver records it.
        $this->assertSame(15000, (int) $payment->amount_minor);
        $this->assertSame('cash', $payment->payment_method);
        $this->assertSame('SHOP-9', $payment->reference);
        $this->assertSame('Front desk', $payment->notes);
        $this->assertSame('2026-09-20 09:30:00', $payment->received_at->format('Y-m-d H:i:s'));
        $this->assertSame($this->admin->id, (int) $payment->recorded_by);
        $this->assertSame(Payment::ENGINE_V2, $payment->payment_engine_version);

        // Cash movement into CASH-BOX only.
        $movement = CashMovement::sole();
        $this->assertSame($this->cashBox->id, (int) $movement->financial_account_id);
        $this->assertSame(CashMovement::EVENT_PAYMENT_RECEIVED, $movement->event_type);
        $this->assertSame(15000, (int) $movement->amount_minor);
        $this->assertSame(15000, app(FinancialAccountBalanceService::class)->currentBalanceMinor($this->cashBox->fresh()));
        $this->assertSame(0, app(FinancialAccountBalanceService::class)->currentBalanceMinor($this->cliq->fresh()));

        // Journal: Dr CASH-BOX ledger / Cr Billing Clearing, then Dr Clearing / Cr AR per allocation.
        $receiptEntry = JournalEntry::where('event_type', 'payment_received')->sole();
        $this->assertSame(15000, (int) $receiptEntry->lines()->where('chart_account_id', $this->cashBox->chart_account_id)->sum('debit_minor'));
        $clearing = app(AccountingSetupService::class)->systemAccount('billing_clearing');
        $receivable = app(AccountingSetupService::class)->systemAccount('accounts_receivable');
        $this->assertSame(15000, (int) $receiptEntry->lines()->where('chart_account_id', $clearing->id)->sum('credit_minor'));
        $this->assertSame(2, JournalEntry::where('event_type', 'payment_allocation_accounting')->count());
        $this->assertSame(15000, (int) JournalLine::where('chart_account_id', $receivable->id)->sum('credit_minor'));
        foreach (JournalEntry::all() as $entry) {
            $this->assertSame((int) $entry->lines()->sum('debit_minor'), (int) $entry->lines()->sum('credit_minor'));
        }

        // Oldest-first auto-allocation and receivable reduction.
        $receivables = app(ReceivableService::class);
        $this->assertSame(0, $receivables->invoiceOutstandingMinor($oldest->fresh()));
        $this->assertSame(5000, $receivables->invoiceOutstandingMinor($newest->fresh()));
        $this->assertSame(5000, $receivables->clientSummary($this->client->fresh())['total_outstanding_minor']);
        $this->assertSame([$oldest->id, $newest->id], PaymentAllocation::orderBy('id')->pluck('invoice_id')->map(fn ($id) => (int) $id)->all());

        // Human-readable activity event.
        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $this->client->id,
            'type' => 'payment_receipt_approved',
            'description' => __('notify.payment_receipts.activity.approved', ['amount' => '15.000', 'method' => __('notify.client_workspace.payment_methods.cash')]),
        ]);
    }

    public function test_cliq_receipt_approval_posts_to_cliq_account(): void
    {
        $this->createInvoice(8000, '2026-09-01');
        $receipt = $this->submitAsStaff('8.000', 'cliq');

        app(PaymentReceiptService::class)->approve($receipt, $this->founder);

        $this->assertSame($this->cliq->id, (int) CashMovement::sole()->financial_account_id);
        $this->assertSame(8000, app(FinancialAccountBalanceService::class)->currentBalanceMinor($this->cliq->fresh()));
        $this->assertSame(0, app(ReceivableService::class)->clientSummary($this->client->fresh())['total_outstanding_minor']);
    }

    public function test_overpayment_remainder_becomes_customer_credit(): void
    {
        $this->createInvoice(5000, '2026-09-01');
        $receipt = $this->submitAsStaff('7.500');

        $payment = app(PaymentReceiptService::class)->approve($receipt, $this->admin);

        $this->assertSame(2500, app(ReceivableService::class)->paymentUnallocatedMinor($payment->fresh()));
    }

    public function test_double_approval_never_double_posts(): void
    {
        $this->createInvoice(10000, '2026-09-01');
        $receipt = $this->submitAsStaff('10.000');
        $staleCopy = PaymentReceiptConfirmation::findOrFail($receipt->id);
        $service = app(PaymentReceiptService::class);

        $first = $service->approve($receipt, $this->admin);
        $second = $service->approve($receipt->fresh(), $this->admin);
        $third = $service->approve($staleCopy, $this->founder); // stale in-memory "pending" copy

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $third->id);

        // HTTP: a different idempotency key on an already approved receipt is a no-op.
        $this->actingAs($this->founder)
            ->post(route('payment-receipts.approve', $receipt), ['_idempotency_key' => 'second-click'])
            ->assertSessionHas('success');

        // HTTP: exact replay of the same key resolves to the stored outcome.
        $this->actingAs($this->admin)->post(route('payment-receipts.approve', $receipt), ['_idempotency_key' => 'replay-1'])->assertRedirect();
        $this->actingAs($this->admin)->post(route('payment-receipts.approve', $receipt), ['_idempotency_key' => 'replay-1'])->assertRedirect();

        $this->assertSame(1, Payment::count());
        $this->assertSame(1, CashMovement::count());
        $this->assertSame(1, PaymentAllocation::count());
        $this->assertSame(1, JournalEntry::where('event_type', 'payment_received')->count());
        $this->assertSame(1, JournalEntry::where('event_type', 'payment_allocation_accounting')->count());
        $this->assertSame(10000, app(FinancialAccountBalanceService::class)->currentBalanceMinor($this->cashBox->fresh()));
        $this->assertSame(1, DB::table('notifications')->where('type', 'payment_receipt_approved')->count());
        $this->assertSame(1, DB::table('activity_logs')->where('type', 'payment_receipt_approved')->count());
    }

    public function test_payment_id_is_unique_at_the_database_boundary(): void
    {
        $first = $this->submitAsStaff('3.000');
        $second = $this->submitAsStaff('3.000');
        $payment = app(PaymentReceiptService::class)->approve($first, $this->admin);

        $this->expectException(QueryException::class);
        DB::table('payment_receipt_confirmations')->where('id', $second->id)->update(['payment_id' => $payment->id]);
    }

    public function test_failed_approval_rolls_back_completely_and_can_be_retried_once(): void
    {
        $this->createInvoice(10000, '2026-09-01');
        $receipt = $this->submitAsStaff('10.000');

        $mock = Mockery::mock(BillingAccountingService::class);
        $mock->shouldReceive('postPaymentAllocation')->andThrow(new \RuntimeException('posting failed'));
        $this->app->instance(BillingAccountingService::class, $mock);

        try {
            app(PaymentReceiptService::class)->approve($receipt, $this->admin);
            $this->fail('Approval must propagate the downstream failure.');
        } catch (\RuntimeException) {
        }

        $this->assertSame(PaymentReceiptConfirmation::STATUS_PENDING, $receipt->fresh()->status);
        $this->assertNull($receipt->fresh()->payment_id);
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, CashMovement::count());
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(0, DB::table('notifications')->where('type', 'payment_receipt_approved')->count());

        $this->app->forgetInstance(BillingAccountingService::class);
        app(PaymentReceiptService::class)->approve($receipt->fresh(), $this->admin);
        app(PaymentReceiptService::class)->approve($receipt->fresh(), $this->admin);

        $this->assertSame(1, Payment::count());
        $this->assertSame(PaymentReceiptConfirmation::STATUS_APPROVED, $receipt->fresh()->status);
    }

    public function test_approval_uses_receipt_values_and_ignores_submitted_overrides(): void
    {
        $receipt = $this->submitAsStaff('4.000', 'cash', ['received_at' => '2026-09-22 10:00:00']);

        $this->actingAs($this->admin)
            ->post(route('payment-receipts.approve', $receipt), [
                'amount' => '400.000',
                'payment_method' => 'cliq',
                'received_at' => '2026-01-01 00:00:00',
                'financial_account_id' => $this->cliq->id,
            ])
            ->assertSessionHas('success');

        $payment = Payment::sole();
        $this->assertSame(4000, (int) $payment->amount_minor);
        $this->assertSame('cash', $payment->payment_method);
        $this->assertSame('2026-09-22 10:00:00', $payment->received_at->format('Y-m-d H:i:s'));
        $this->assertSame($this->cashBox->id, (int) CashMovement::sole()->financial_account_id);
    }

    // ── Owner rejection & Staff cancellation ────────────────────────────

    public function test_rejection_requires_reason_and_has_zero_financial_effect(): void
    {
        $invoice = $this->createInvoice(10000, '2026-09-01');
        $receipt = $this->submitAsStaff('10.000');
        $before = $this->financialSnapshot();

        $this->actingAs($this->admin)
            ->post(route('payment-receipts.reject', $receipt), ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');
        $this->assertTrue($receipt->fresh()->isPending());

        $this->actingAs($this->admin)
            ->post(route('payment-receipts.reject', $receipt), ['rejection_reason' => 'Amount does not match the CliQ statement'])
            ->assertSessionHas('success');

        $receipt->refresh();
        $this->assertSame(PaymentReceiptConfirmation::STATUS_REJECTED, $receipt->status);
        $this->assertSame('Amount does not match the CliQ statement', $receipt->rejection_reason);
        $this->assertSame($this->admin->id, (int) $receipt->reviewed_by);
        $this->assertNotNull($receipt->reviewed_at);
        $this->assertNull($receipt->payment_id);

        $this->assertSame($before, $this->financialSnapshot());
        $this->assertSame(10000, app(ReceivableService::class)->invoiceOutstandingMinor($invoice->fresh()));
        $this->assertDatabaseHas('activity_logs', ['client_id' => $this->client->id, 'type' => 'payment_receipt_rejected']);

        // A reviewed receipt can no longer be approved.
        $this->actingAs($this->admin)
            ->post(route('payment-receipts.approve', $receipt), ['_idempotency_key' => 'after-reject'])
            ->assertSessionHasErrors('receipt');
        $this->assertSame(0, Payment::count());
    }

    public function test_staff_can_cancel_own_pending_receipt_with_zero_financial_effect(): void
    {
        $this->createInvoice(10000, '2026-09-01');
        $receipt = $this->submitAsStaff('10.000');
        $before = $this->financialSnapshot();

        $this->actingAs($this->otherStaff)
            ->post(route('payment-receipts.cancel', $receipt))
            ->assertForbidden();
        $this->assertTrue($receipt->fresh()->isPending());

        $this->actingAs($this->staff)
            ->post(route('payment-receipts.cancel', $receipt))
            ->assertSessionHas('success');

        $this->assertSame(PaymentReceiptConfirmation::STATUS_CANCELLED, $receipt->fresh()->status);
        $this->assertSame($before, $this->financialSnapshot());

        // Cancelled receipts cannot be approved.
        $this->actingAs($this->admin)
            ->post(route('payment-receipts.approve', $receipt), ['_idempotency_key' => 'after-cancel'])
            ->assertSessionHasErrors('receipt');
        $this->assertSame(0, Payment::count());
    }

    public function test_staff_cannot_cancel_a_reviewed_receipt(): void
    {
        $approved = $this->submitAsStaff('2.000');
        app(PaymentReceiptService::class)->approve($approved, $this->admin);
        $rejected = $this->submitAsStaff('3.000');
        app(PaymentReceiptService::class)->reject($rejected, $this->admin, 'Duplicate');

        foreach ([$approved, $rejected] as $receipt) {
            $this->actingAs($this->staff)
                ->post(route('payment-receipts.cancel', $receipt))
                ->assertSessionHasErrors('receipt');
        }

        $this->assertSame(PaymentReceiptConfirmation::STATUS_APPROVED, $approved->fresh()->status);
        $this->assertSame(PaymentReceiptConfirmation::STATUS_REJECTED, $rejected->fresh()->status);
        $this->assertSame(1, Payment::count());
    }

    // ── Authorization ───────────────────────────────────────────────────

    public function test_staff_cannot_approve_reject_or_record_direct_payments(): void
    {
        $receipt = $this->submitAsStaff('5.000');

        $this->actingAs($this->staff)->post(route('payment-receipts.approve', $receipt))->assertForbidden();
        $this->actingAs($this->staff)->post(route('payment-receipts.reject', $receipt), ['rejection_reason' => 'x'])->assertForbidden();
        $this->actingAs($this->staff)
            ->post(route('clients.payments.normal.store', $this->client), ['amount' => '5.000', 'payment_method' => 'cash'])
            ->assertForbidden();
        $this->actingAs($this->staff)->get(route('collections.index'))->assertForbidden();

        $this->assertTrue($receipt->fresh()->isPending());
        $this->assertSame(0, Payment::count());
    }

    public function test_inactive_staff_cannot_submit_receipts(): void
    {
        $this->staff->update(['is_active' => false]);

        $response = $this->actingAs($this->staff)
            ->post(route('clients.payment-receipts.store', $this->client), ['amount' => '5.000', 'payment_method' => 'cash']);

        $this->assertContains($response->status(), [302, 403]);
        $this->assertSame(0, PaymentReceiptConfirmation::count());
    }

    public function test_owner_sees_pending_receipts_with_review_actions_on_collections(): void
    {
        $receipt = $this->submitAsStaff('6.500', 'cliq');

        $this->actingAs($this->admin)
            ->get(route('finance.collections'))
            ->assertOk()
            ->assertSee('data-collections-panel="pending"', false)
            ->assertSee('P1 Receipt Client')
            ->assertSee('6.500')
            ->assertSee(route('payment-receipts.approve', $receipt), false)
            ->assertSee(route('payment-receipts.reject', $receipt), false);
    }

    // ── Owner direct payment ────────────────────────────────────────────

    public function test_owner_direct_cash_payment_posts_immediately_without_receipt(): void
    {
        $this->createInvoice(10000, '2026-09-01');

        $this->actingAs($this->founder)
            ->post(route('clients.payments.normal.store', $this->client), ['amount' => '10.000', 'payment_method' => 'cash'])
            ->assertRedirect(route('clients.show', $this->client));

        $this->assertSame(0, PaymentReceiptConfirmation::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame($this->cashBox->id, (int) CashMovement::sole()->financial_account_id);
        $this->assertSame(0, app(ReceivableService::class)->clientSummary($this->client->fresh())['total_outstanding_minor']);
    }

    public function test_owner_direct_cliq_payment_posts_immediately_to_cliq(): void
    {
        $this->createInvoice(10000, '2026-09-01');

        $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client), ['amount' => '10.000', 'payment_method' => 'cliq'])
            ->assertSessionHas('success');

        $this->assertSame(0, PaymentReceiptConfirmation::count());
        $this->assertSame($this->cliq->id, (int) CashMovement::sole()->financial_account_id);
        $this->assertSame(10000, app(FinancialAccountBalanceService::class)->currentBalanceMinor($this->cliq->fresh()));
    }

    public function test_owner_direct_payment_rejects_future_date_and_unsupported_methods_but_allows_old_dates(): void
    {
        $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client), ['amount' => '1.000', 'payment_method' => 'cash', 'received_at' => '2026-09-24 09:00:00'])
            ->assertSessionHasErrors('received_at');

        $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client), ['amount' => '1.000', 'payment_method' => 'bank_transfer'])
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, Payment::count());

        $this->actingAs($this->admin)
            ->post(route('clients.payments.normal.store', $this->client), ['amount' => '1.000', 'payment_method' => 'cash', 'received_at' => '2026-06-01 09:00:00'])
            ->assertSessionHas('success');

        $this->assertSame('2026-06-01', Payment::sole()->received_at->toDateString());
    }

    // ── Notifications ───────────────────────────────────────────────────

    public function test_submission_notifies_owner_level_users_only(): void
    {
        $inactiveAdmin = User::factory()->create(['role' => 'founder', 'is_active' => false]);
        $receipt = $this->submitAsStaff('9.000');

        $recipients = DB::table('notifications')
            ->where('type', 'payment_receipt_submitted')
            ->where('source_type', 'payment_receipt')
            ->where('source_id', $receipt->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame(collect([$this->founder->id, $this->admin->id])->sort()->values()->all(), $recipients);
        $this->assertNotContains($inactiveAdmin->id, $recipients);
        $this->assertNotContains($this->staff->id, $recipients);
    }

    public function test_submitter_is_notified_on_approval_and_rejection(): void
    {
        $approved = $this->submitAsStaff('1.000');
        $rejected = $this->submitAsStaff('2.000');

        app(PaymentReceiptService::class)->approve($approved, $this->admin);
        app(PaymentReceiptService::class)->reject($rejected, $this->founder, 'Not received in CliQ');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->staff->id,
            'type' => 'payment_receipt_approved',
            'source_type' => 'payment_receipt',
            'source_id' => $approved->id,
        ]);
        $rejection = DB::table('notifications')
            ->where('user_id', $this->staff->id)
            ->where('type', 'payment_receipt_rejected')
            ->where('source_id', $rejected->id)
            ->sole();
        $this->assertStringContainsString('Not received in CliQ', $rejection->message);
        $this->assertSame(0, DB::table('notifications')->whereIn('type', ['payment_receipt_approved', 'payment_receipt_rejected'])->where('user_id', '!=', $this->staff->id)->count());
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function submitAsStaff(string $amount, string $method = 'cash', array $extra = []): PaymentReceiptConfirmation
    {
        return app(PaymentReceiptService::class)->submit(
            $this->client,
            array_merge(['amount' => $amount, 'payment_method' => $method], $extra),
            $this->staff
        );
    }

    private function financialSnapshot(): array
    {
        $balances = app(FinancialAccountBalanceService::class);

        return [
            'payments' => Payment::count(),
            'allocations' => PaymentAllocation::count(),
            'cash_movements' => CashMovement::count(),
            'journal_entries' => JournalEntry::count(),
            'journal_lines' => JournalLine::count(),
            'outstanding_minor' => app(ReceivableService::class)->clientSummary($this->client->fresh())['total_outstanding_minor'],
            'cash_box_minor' => $balances->currentBalanceMinor($this->cashBox->fresh()),
            'cliq_minor' => $balances->currentBalanceMinor($this->cliq->fresh()),
        ];
    }

    private function createInvoice(int $totalMinor, string $dueDate): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => 'P1R-'.str_pad((string) (Invoice::count() + 1), 6, '0', STR_PAD_LEFT),
            'client_id' => $this->client->id,
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
            'description_snapshot' => 'P1 receipt line',
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
