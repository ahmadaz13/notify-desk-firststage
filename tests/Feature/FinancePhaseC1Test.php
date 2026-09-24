<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\ReceivableService;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancePhaseC1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    public function test_v2_payment_stores_exact_fils_and_does_not_create_subscription_or_change_client(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->createClient(['status' => 'prospect', 'stage' => ClientLifecycle::INSTALLED_FREE]);

        $this->actingAs($admin)->post(route('clients.collections.payments.store', $client), [
            'amount' => '0.001',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
            'reference' => 'R-001',
        ])->assertSessionHas('success');

        $payment = Payment::where('client_id', $client->id)->firstOrFail();
        $this->assertSame(1, $payment->amount_minor);
        $this->assertSame('0.001', $payment->amount);
        $this->assertSame('JOD', $payment->currency);
        $this->assertSame(Payment::ENGINE_V2, $payment->payment_engine_version);
        $this->assertNull($payment->subscription_id);
        $this->assertDatabaseMissing('subscriptions', ['client_id' => $client->id]);

        $client->refresh();
        $this->assertSame('prospect', $client->status);
        $this->assertSame(ClientLifecycle::INSTALLED_FREE, $client->stage);
        $this->assertDatabaseHas('activity_logs', ['type' => 'payment_received_v2', 'client_id' => $client->id]);
    }

    public function test_v2_payment_stores_exact_twelve_point_three_seven_five_jod(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->createClient();

        $this->actingAs($admin)->post(route('clients.collections.payments.store', $client), [
            'amount' => '12.375',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'payment_method' => 'cliq',
            'received_at' => '2026-09-14 10:00:00',
        ])->assertSessionHas('success');

        $this->assertSame(12375, Payment::firstOrFail()->amount_minor);
    }

    public function test_payment_can_be_allocated_partially_fully_and_across_invoices(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->createClient();
        $invoiceA = $this->createInvoice($client, 100000, '2026-09-01');
        $invoiceB = $this->createInvoice($client, 50000, '2026-09-02');

        $this->actingAs($admin)->post(route('clients.collections.payments.store', $client), [
            'amount' => '125.000',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
            'allocations' => [
                ['invoice_id' => $invoiceA->id, 'amount' => '100.000'],
                ['invoice_id' => $invoiceB->id, 'amount' => '25.000'],
            ],
        ])->assertSessionHas('success');

        $payment = Payment::firstOrFail();
        $receivables = app(ReceivableService::class);

        $this->assertSame(2, PaymentAllocation::count());
        $this->assertSame('paid', $receivables->invoiceSettlementStatus($invoiceA->fresh()));
        $this->assertSame('partially_paid', $receivables->invoiceSettlementStatus($invoiceB->fresh()));
        $this->assertSame(25000, $receivables->invoiceOutstandingMinor($invoiceB->fresh()));
        $this->assertSame(0, $receivables->paymentUnallocatedMinor($payment->fresh()));
        $this->assertDatabaseHas('activity_logs', ['type' => 'payment_allocated', 'client_id' => $client->id]);
    }

    public function test_allocation_invariants_reject_over_allocation_cross_client_draft_and_voided_invoices(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $clientA = $this->createClient(['business_name' => 'A']);
        $clientB = $this->createClient(['business_name' => 'B', 'phone' => '0791111111']);
        $invoiceA = $this->createInvoice($clientA, 10000, '2026-09-14');
        $invoiceB = $this->createInvoice($clientB, 10000, '2026-09-14');
        $draft = $this->createInvoice($clientA, 10000, '2026-09-14', Invoice::STATUS_DRAFT);
        $voided = $this->createInvoice($clientA, 10000, '2026-09-14', Invoice::STATUS_VOIDED);

        $this->actingAs($admin)->post(route('clients.collections.payments.store', $clientA), [
            'amount' => '10.000',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
        ])->assertSessionHas('success');
        $payment = Payment::firstOrFail();

        $this->actingAs($admin)->post(route('payments.allocations.store', $payment), [
            'invoice_id' => $invoiceA->id,
            'amount' => '10.001',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($admin)->post(route('payments.allocations.store', $payment), [
            'invoice_id' => $invoiceB->id,
            'amount' => '1.000',
        ])->assertSessionHasErrors('invoice_id');

        $this->actingAs($admin)->post(route('payments.allocations.store', $payment), [
            'invoice_id' => $draft->id,
            'amount' => '1.000',
        ])->assertSessionHasErrors('invoice_id');

        $this->actingAs($admin)->post(route('payments.allocations.store', $payment), [
            'invoice_id' => $voided->id,
            'amount' => '1.000',
        ])->assertSessionHasErrors('invoice_id');

        $this->actingAs($admin)->post(route('payments.allocations.store', $payment), [
            'invoice_id' => $invoiceA->id,
            'amount' => '10.000',
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post(route('payments.allocations.store', $payment), [
            'invoice_id' => $invoiceA->id,
            'amount' => '0.001',
        ])->assertSessionHasErrors('amount');
    }

    public function test_overpayment_remains_unallocated_and_can_later_settle_new_invoice(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->createClient();
        $invoiceA = $this->createInvoice($client, 10000, '2026-09-14');

        $this->actingAs($admin)->post(route('clients.collections.payments.store', $client), [
            'amount' => '15.000',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
            'allocations' => [
                ['invoice_id' => $invoiceA->id, 'amount' => '10.000'],
            ],
        ])->assertSessionHas('success');

        $payment = Payment::firstOrFail();
        $receivables = app(ReceivableService::class);
        $this->assertSame(5000, $receivables->paymentUnallocatedMinor($payment));

        $invoiceB = $this->createInvoice($client, 5000, '2026-09-15');
        $this->actingAs($admin)->post(route('payments.allocations.store', $payment), [
            'invoice_id' => $invoiceB->id,
            'amount' => '5.000',
        ])->assertSessionHas('success');

        $this->assertSame('paid', $receivables->invoiceSettlementStatus($invoiceB->fresh()));
        $this->assertSame(0, $receivables->paymentUnallocatedMinor($payment->fresh()));
        $this->assertSame(1, Payment::count());
    }

    public function test_auto_allocation_uses_oldest_due_invoice_and_leaves_remainder_unallocated(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->createClient();
        $newer = $this->createInvoice($client, 10000, '2026-09-20');
        $older = $this->createInvoice($client, 10000, '2026-09-01');

        $this->actingAs($admin)->post(route('clients.collections.payments.store', $client), [
            'amount' => '25.000',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
            'auto_allocate_oldest' => '1',
        ])->assertSessionHas('success');

        $payment = Payment::firstOrFail();
        $receivables = app(ReceivableService::class);
        $this->assertSame(10000, $receivables->invoiceAllocatedMinor($older->fresh()));
        $this->assertSame(10000, $receivables->invoiceAllocatedMinor($newer->fresh()));
        $this->assertSame(5000, $receivables->paymentUnallocatedMinor($payment));
    }

    public function test_aging_excludes_paid_and_voided_and_ages_remaining_partial_balance(): void
    {
        Carbon::setTestNow('2026-09-14 12:00:00');
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->createClient();
        $partial = $this->createInvoice($client, 10000, '2026-08-20');
        $paid = $this->createInvoice($client, 10000, '2026-08-01');
        $voided = $this->createInvoice($client, 10000, '2026-07-01', Invoice::STATUS_VOIDED);

        $this->actingAs($admin)->post(route('clients.collections.payments.store', $client), [
            'amount' => '15.000',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
            'allocations' => [
                ['invoice_id' => $partial->id, 'amount' => '5.000'],
                ['invoice_id' => $paid->id, 'amount' => '10.000'],
            ],
        ])->assertSessionHas('success');

        $aging = app(ReceivableService::class)->agingBuckets($client);
        $this->assertSame(5000, $aging['1_30_days_overdue']);
        $this->assertSame(0, $aging['31_60_days_overdue']);
        $this->assertSame(0, app(ReceivableService::class)->invoiceOutstandingMinor($voided));
    }

    public function test_invoice_with_allocation_cannot_be_voided_in_c1(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->createClient();
        $invoice = $this->createInvoice($client, 10000, '2026-09-14');

        $this->actingAs($admin)->post(route('clients.collections.payments.store', $client), [
            'amount' => '1.000',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
            'allocations' => [
                ['invoice_id' => $invoice->id, 'amount' => '1.000'],
            ],
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post(route('invoices.void', $invoice), [
            'void_reason' => 'Correction',
        ])->assertSessionHasErrors('void_reason');

        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->fresh()->status);
    }

    public function test_partner_cannot_record_allocate_or_view_collections_queue(): void
    {
        [, $partnerUser] = $this->createPartnerUser();
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->createClient();
        $invoice = $this->createInvoice($client, 10000, '2026-09-14');

        $this->actingAs($partnerUser)->post(route('clients.collections.payments.store', $client), [
            'amount' => '1.000',
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('clients.collections.payments.store', $client), [
            'amount' => '1.000',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-14 10:00:00',
        ])->assertSessionHas('success');

        $payment = Payment::firstOrFail();
        $this->actingAs($partnerUser)->post(route('payments.allocations.store', $payment), [
            'invoice_id' => $invoice->id,
            'amount' => '1.000',
        ])->assertForbidden();

        $this->actingAs($partnerUser)->get(route('collections.index'))->assertForbidden();
    }

    public function test_legacy_payments_remain_readable(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);
        $client = $this->createClient(['status' => 'subscriber', 'stage' => ClientLifecycle::SUBSCRIBER]);
        $subscriptionId = DB::table('subscriptions')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => '10.000',
            'start_date' => '2026-09-14',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('payments')->insert([
            'client_id' => $client->id,
            'subscription_id' => $subscriptionId,
            'amount' => '10.000',
            'payment_method' => 'cash',
            'paid_at' => '2026-09-14 10:00:00',
            'recorded_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('clients.show', $client))->assertOk()->assertSee('10.000');
    }

    private function createClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'Phase C1 Client',
            'phone' => '0790000000',
            'city_area' => 'Amman',
            'business_category' => 'Restaurant',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ], $overrides));
    }

    private function cashAccount(User $admin): FinancialAccount
    {
        return FinancialAccount::firstOrCreate(
            ['code' => 'test_cash'],
            [
                'name_ar' => 'Test Cash',
                'type' => FinancialAccount::TYPE_CASH,
                'currency' => 'JOD',
                'is_active' => true,
                'created_by' => $admin->id,
            ]
        );
    }

    private function createInvoice(Client $client, int $totalMinor, string $dueDate, string $status = Invoice::STATUS_ISSUED): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-'.str_pad((string) (Invoice::count() + 1), 6, '0', STR_PAD_LEFT),
            'client_id' => $client->id,
            'currency' => 'JOD',
            'status' => $status,
            'issue_date' => '2026-09-01',
            'due_date' => $dueDate,
            'subtotal_minor' => $totalMinor,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => $totalMinor,
            'issued_at' => $status === Invoice::STATUS_ISSUED ? now() : null,
            'voided_at' => $status === Invoice::STATUS_VOIDED ? now() : null,
            'void_reason' => $status === Invoice::STATUS_VOIDED ? 'Test void' : null,
        ]);

        $invoice->lines()->create([
            'line_type' => InvoiceLine::TYPE_CUSTOM,
            'description_snapshot' => 'Test line',
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
