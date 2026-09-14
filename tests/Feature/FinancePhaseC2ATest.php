<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentAllocationReversal;
use App\Models\PaymentReversal;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ReceivableService;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancePhaseC2ATest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    public function test_allocation_reversal_is_append_only_and_restores_invoice_and_payment_balances(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $invoice = $this->createInvoice($client, 100000, '2026-09-14');
        $payment = $this->recordPayment($admin, $client, '100.000', [
            ['invoice_id' => $invoice->id, 'amount' => '100.000'],
        ]);
        $allocation = PaymentAllocation::firstOrFail();

        $receivables = app(ReceivableService::class);
        $this->assertSame('paid', $receivables->invoiceSettlementStatus($invoice->fresh()));
        $this->assertSame(0, $receivables->paymentUnallocatedMinor($payment->fresh()));

        $this->actingAs($admin)->post(route('payment-allocations.reverse', $allocation), [
            'reason' => 'Wrong invoice selected',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('payment_allocations', ['id' => $allocation->id, 'amount_minor' => 100000]);
        $this->assertSame(1, PaymentAllocation::count());
        $this->assertSame(1, PaymentAllocationReversal::count());
        $this->assertSame(100000, $receivables->invoiceOutstandingMinor($invoice->fresh()));
        $this->assertSame('unpaid', $receivables->invoiceSettlementStatus($invoice->fresh()));
        $this->assertSame(100000, $receivables->paymentUnallocatedMinor($payment->fresh()));
        $this->assertDatabaseHas('activity_logs', ['client_id' => $client->id, 'type' => 'allocation_reversed']);
    }

    public function test_allocation_can_only_be_reversed_once_and_requires_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $invoice = $this->createInvoice($client, 10000, '2026-09-14');
        $this->recordPayment($admin, $client, '10.000', [
            ['invoice_id' => $invoice->id, 'amount' => '10.000'],
        ]);
        $allocation = PaymentAllocation::firstOrFail();

        $this->actingAs($admin)->post(route('payment-allocations.reverse', $allocation), [])
            ->assertSessionHasErrors('reason');
        $this->assertSame(0, PaymentAllocationReversal::count());
        $this->assertDatabaseMissing('activity_logs', ['type' => 'allocation_reversed']);

        $this->actingAs($admin)->post(route('payment-allocations.reverse', $allocation), [
            'reason' => 'Duplicate receipt correction',
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post(route('payment-allocations.reverse', $allocation), [
            'reason' => 'Try twice',
        ])->assertSessionHasErrors('payment_allocation_id');

        $this->assertSame(1, PaymentAllocationReversal::count());
        $this->assertSame(1, DB::table('activity_logs')->where('type', 'allocation_reversed')->count());
    }

    public function test_reversing_one_allocation_reopens_paid_invoice_to_partially_paid(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $invoice = $this->createInvoice($client, 100000, '2026-09-14');
        $this->recordPayment($admin, $client, '40.000', [
            ['invoice_id' => $invoice->id, 'amount' => '40.000'],
        ]);
        $this->recordPayment($admin, $client, '60.000', [
            ['invoice_id' => $invoice->id, 'amount' => '60.000'],
        ]);
        $firstAllocation = PaymentAllocation::orderBy('id')->firstOrFail();

        $this->actingAs($admin)->post(route('payment-allocations.reverse', $firstAllocation), [
            'reason' => 'Partial correction',
        ])->assertSessionHas('success');

        $receivables = app(ReceivableService::class);
        $this->assertSame(60000, $receivables->invoiceAllocatedMinor($invoice->fresh()));
        $this->assertSame(40000, $receivables->invoiceOutstandingMinor($invoice->fresh()));
        $this->assertSame('partially_paid', $receivables->invoiceSettlementStatus($invoice->fresh()));
    }

    public function test_reopened_overdue_invoice_returns_to_aging_queue_and_can_be_voided_after_all_allocations_reversed(): void
    {
        Carbon::setTestNow('2026-09-14 12:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $invoice = $this->createInvoice($client, 100000, '2026-08-01');
        $this->recordPayment($admin, $client, '100.000', [
            ['invoice_id' => $invoice->id, 'amount' => '100.000'],
        ]);
        $allocation = PaymentAllocation::firstOrFail();
        $receivables = app(ReceivableService::class);

        $this->assertSame(0, $receivables->agingBuckets($client)['31_60_days_overdue']);

        $this->actingAs($admin)->post(route('payment-allocations.reverse', $allocation), [
            'reason' => 'Reopen invoice',
        ])->assertSessionHas('success');

        $this->assertSame(100000, $receivables->agingBuckets($client)['31_60_days_overdue']);
        $this->assertCount(1, $receivables->outstandingInvoices(['due_state' => 'overdue']));

        $this->actingAs($admin)->post(route('invoices.void', $invoice), [
            'void_reason' => 'Invoice issued by mistake',
        ])->assertSessionHas('success');

        $this->assertSame(Invoice::STATUS_VOIDED, $invoice->fresh()->status);
    }

    public function test_payment_with_active_allocation_cannot_be_reversed_but_can_after_allocation_reversal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient(['status' => 'subscriber', 'stage' => ClientLifecycle::SUBSCRIBER]);
        $subscription = Subscription::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => '100.000',
            'start_date' => '2026-09-14',
            'status' => 'active',
        ]);
        $invoice = $this->createInvoice($client, 10000, '2026-09-14');
        $payment = $this->recordPayment($admin, $client, '10.000', [
            ['invoice_id' => $invoice->id, 'amount' => '10.000'],
        ]);
        $allocation = PaymentAllocation::firstOrFail();

        $this->actingAs($admin)->post(route('payments.reverse', $payment), [
            'reason' => 'Wrong payment',
        ])->assertSessionHasErrors('payment_id');
        $this->assertSame(0, PaymentReversal::count());

        $this->actingAs($admin)->post(route('payment-allocations.reverse', $allocation), [
            'reason' => 'Remove allocation first',
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post(route('payments.reverse', $payment), [
            'reason' => 'Wrong payment',
        ])->assertSessionHas('success');

        $receivables = app(ReceivableService::class);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertSame(1, PaymentReversal::count());
        $this->assertSame(0, $receivables->paymentUnallocatedMinor($payment->fresh()));
        $this->assertTrue($receivables->unallocatedCredits(['client_id' => $client->id])->isEmpty());

        $client->refresh();
        $subscription->refresh();
        $this->assertSame('subscriber', $client->status);
        $this->assertSame(ClientLifecycle::SUBSCRIBER, $client->stage);
        $this->assertSame('active', $subscription->status);
        $this->assertDatabaseHas('activity_logs', ['client_id' => $client->id, 'type' => 'payment_reversed']);
    }

    public function test_payment_reversal_requires_reason_can_only_happen_once_and_prevents_future_allocation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $invoice = $this->createInvoice($client, 10000, '2026-09-14');
        $payment = $this->recordPayment($admin, $client, '10.000');

        $this->actingAs($admin)->post(route('payments.reverse', $payment), [])
            ->assertSessionHasErrors('reason');
        $this->assertSame(0, PaymentReversal::count());

        $this->actingAs($admin)->post(route('payments.reverse', $payment), [
            'reason' => 'Recorded twice',
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post(route('payments.reverse', $payment), [
            'reason' => 'Try twice',
        ])->assertSessionHasErrors('payment_id');

        $this->actingAs($admin)->post(route('payments.allocations.store', $payment), [
            'invoice_id' => $invoice->id,
            'amount' => '1.000',
        ])->assertSessionHasErrors('payment_id');

        $this->assertSame(1, PaymentReversal::count());
        $this->assertSame(1, DB::table('activity_logs')->where('type', 'payment_reversed')->count());
    }

    public function test_partner_cannot_reverse_allocation_or_payment(): void
    {
        [$partner, $partnerUser] = $this->createPartnerUser();
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient(['partner_id' => $partner->id]);
        $invoice = $this->createInvoice($client, 10000, '2026-09-14');
        $payment = $this->recordPayment($admin, $client, '10.000', [
            ['invoice_id' => $invoice->id, 'amount' => '10.000'],
        ]);
        $allocation = PaymentAllocation::firstOrFail();

        $this->actingAs($partnerUser)->post(route('payment-allocations.reverse', $allocation), [
            'reason' => 'Partner blocked',
        ])->assertForbidden();

        $this->actingAs($partnerUser)->post(route('payments.reverse', $payment), [
            'reason' => 'Partner blocked',
        ])->assertForbidden();

        $this->assertSame(0, PaymentAllocationReversal::count());
        $this->assertSame(0, PaymentReversal::count());
    }

    public function test_legacy_payment_cannot_be_reversed_through_v2_correction_workflow(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient(['status' => 'subscriber', 'stage' => ClientLifecycle::SUBSCRIBER]);
        $subscription = Subscription::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => '10.000',
            'start_date' => '2026-09-14',
            'status' => 'active',
        ]);
        $payment = Payment::create([
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'amount' => '10.000',
            'payment_method' => 'cash',
            'paid_at' => '2026-09-14 10:00:00',
            'recorded_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('payments.reverse', $payment), [
            'reason' => 'Legacy correction attempt',
        ])->assertSessionHasErrors('payment_id');

        $this->assertSame(0, PaymentReversal::count());
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'subscription_id' => $subscription->id]);
    }

    public function test_routes_expose_no_delete_path_for_v2_payments_or_allocations(): void
    {
        $routes = collect(app('router')->getRoutes())->map(fn ($route) => [
            'methods' => $route->methods(),
            'uri' => $route->uri(),
        ]);

        $deleteRoutes = $routes->filter(fn (array $route) => in_array('DELETE', $route['methods'], true));

        $this->assertFalse($deleteRoutes->contains(fn (array $route) => str_contains($route['uri'], 'payments')));
        $this->assertFalse($deleteRoutes->contains(fn (array $route) => str_contains($route['uri'], 'payment-allocations')));
    }

    private function recordPayment(User $admin, Client $client, string $amount, array $allocations = []): Payment
    {
        $payload = [
            'amount' => $amount,
            'financial_account_id' => $this->cashAccount($admin)->id,
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

    private function createClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'Phase C2A Client',
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

    private function createInvoice(Client $client, int $totalMinor, string $dueDate): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-C2A-'.str_pad((string) (Invoice::count() + 1), 6, '0', STR_PAD_LEFT),
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
            'description_snapshot' => 'Correction test line',
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
            'company_name' => 'Phase C2A Partner',
            'email' => 'phase-c2a-partner@example.com',
        ]);

        $user = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        return [$partner, $user];
    }
}
