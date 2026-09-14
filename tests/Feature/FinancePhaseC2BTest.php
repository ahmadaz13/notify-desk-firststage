<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CreditNote;
use App\Models\CreditNoteApplication;
use App\Models\CreditNoteApplicationReversal;
use App\Models\CreditNoteLine;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PaymentReversal;
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

class FinancePhaseC2BTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    public function test_credit_note_uses_exact_fils_requires_reason_caps_invoice_credit_and_preserves_invoice_total(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $invoice = $this->createInvoice($client, 100000, '2026-09-14');
        $originalTotal = $invoice->total_minor;

        $this->actingAs($admin)->post(route('clients.credit-notes.store', $client), [
            'issue_date' => '2026-09-14',
            'original_invoice_id' => $invoice->id,
            'lines' => [
                ['description' => 'Exact adjustment', 'subtotal_jod' => '12.375', 'tax_jod' => '0.125'],
            ],
        ])->assertSessionHasErrors('reason');
        $this->assertSame(0, CreditNote::count());
        $this->assertDatabaseMissing('activity_logs', ['type' => 'credit_note_created']);

        $this->createCreditNote($admin, $client, '12.375', '0.125', $invoice, 'Commercial credit');
        $creditNote = CreditNote::firstOrFail();

        $this->assertSame(12375, $creditNote->subtotal_minor);
        $this->assertSame(125, $creditNote->tax_minor);
        $this->assertSame(12500, $creditNote->total_minor);
        $this->assertSame(CreditNote::STATUS_ISSUED, $creditNote->status);
        $this->assertStringStartsWith('CN-2026-', $creditNote->credit_note_number);
        $this->assertSame($originalTotal, $invoice->fresh()->total_minor);
        $this->assertSame(1, CreditNoteLine::count());
        $this->assertDatabaseHas('activity_logs', ['client_id' => $client->id, 'type' => 'credit_note_issued']);

        $this->actingAs($admin)->post(route('clients.credit-notes.store', $client), [
            'issue_date' => '2026-09-14',
            'original_invoice_id' => $invoice->id,
            'reason' => 'Too much',
            'lines' => [
                ['description' => 'Overflow', 'subtotal_jod' => '88.000', 'tax_jod' => '0.000'],
            ],
        ])->assertSessionHasErrors('total');

        $this->assertSame(1, CreditNote::count());
        $this->assertSame($originalTotal, $invoice->fresh()->total_minor);
    }

    public function test_credit_application_reduces_outstanding_and_reversal_restores_invoice_and_credit_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $invoice = $this->createInvoice($client, 100000, '2026-08-20');
        $payment = $this->recordPayment($admin, $client, '40.000', [
            ['invoice_id' => $invoice->id, 'amount' => '40.000'],
        ]);
        $creditNote = $this->createCreditNote($admin, $client, '20.000', '0.000', $invoice);
        $receivables = app(ReceivableService::class);

        $this->actingAs($admin)->post(route('credit-notes.applications.store', $creditNote), [
            'invoice_id' => $invoice->id,
            'amount' => '20.000',
        ])->assertSessionHas('success');

        $application = CreditNoteApplication::firstOrFail();
        $this->assertSame(1, CreditNoteApplication::count());
        $this->assertSame(40000, $receivables->invoiceAllocatedMinor($invoice->fresh()));
        $this->assertSame(20000, $receivables->invoiceCreditAppliedMinor($invoice->fresh()));
        $this->assertSame(40000, $receivables->invoiceOutstandingMinor($invoice->fresh()));
        $this->assertSame('partially_paid', $receivables->invoiceSettlementStatus($invoice->fresh()));
        $this->assertSame(0, $receivables->creditNoteAvailableMinor($creditNote->fresh()));
        $this->assertSame(0, $receivables->paymentUnallocatedMinor($payment->fresh()));

        $this->actingAs($admin)->post(route('credit-note-applications.reverse', $application), [
            'reason' => 'Applied to wrong invoice',
        ])->assertSessionHas('success');

        $this->assertSame(1, CreditNoteApplication::count());
        $this->assertSame(1, CreditNoteApplicationReversal::count());
        $this->assertSame(0, $receivables->invoiceCreditAppliedMinor($invoice->fresh()));
        $this->assertSame(60000, $receivables->invoiceOutstandingMinor($invoice->fresh()));
        $this->assertSame(20000, $receivables->creditNoteAvailableMinor($creditNote->fresh()));

        $this->actingAs($admin)->post(route('credit-note-applications.reverse', $application), [
            'reason' => 'Try twice',
        ])->assertSessionHasErrors('credit_note_application_id');
        $this->assertSame(1, DB::table('activity_logs')->where('type', 'credit_note_application_reversed')->count());
    }

    public function test_credit_application_rejects_cross_client_over_outstanding_and_over_available(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $clientA = $this->createClient(['business_name' => 'A']);
        $clientB = $this->createClient(['business_name' => 'B', 'phone' => '0791111111']);
        $invoiceA = $this->createInvoice($clientA, 10000, '2026-09-14');
        $invoiceB = $this->createInvoice($clientB, 10000, '2026-09-14');
        $creditNote = $this->createCreditNote($admin, $clientA, '5.000', '0.000', $invoiceA);

        $this->actingAs($admin)->post(route('credit-notes.applications.store', $creditNote), [
            'invoice_id' => $invoiceB->id,
            'amount' => '1.000',
        ])->assertSessionHasErrors('invoice_id');

        $this->actingAs($admin)->post(route('credit-notes.applications.store', $creditNote), [
            'invoice_id' => $invoiceA->id,
            'amount' => '6.000',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($admin)->post(route('credit-notes.applications.store', $creditNote), [
            'invoice_id' => $invoiceA->id,
            'amount' => '5.000',
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post(route('credit-notes.applications.store', $creditNote), [
            'invoice_id' => $invoiceA->id,
            'amount' => '0.001',
        ])->assertSessionHasErrors('amount');
    }

    public function test_fully_paid_invoice_can_generate_customer_credit_and_credit_note_refund_consumes_only_available_balance(): void
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
        $invoice = $this->createInvoice($client, 100000, '2026-09-14');
        $this->recordPayment($admin, $client, '100.000', [
            ['invoice_id' => $invoice->id, 'amount' => '100.000'],
        ]);
        $creditNote = $this->createCreditNote($admin, $client, '20.000', '0.000', $invoice, 'Goodwill credit');
        $receivables = app(ReceivableService::class);

        $this->assertSame(0, $receivables->invoiceOutstandingMinor($invoice->fresh()));
        $this->assertSame(20000, $receivables->creditNoteAvailableMinor($creditNote->fresh()));
        $this->assertSame(20000, $receivables->clientSummary($client)['total_customer_credit_minor']);

        $this->actingAs($admin)->post(route('credit-notes.refunds.store', $creditNote), [
            'amount' => '20.000',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'refund_method' => 'cash',
            'refunded_at' => '2026-09-14 13:00:00',
            'reason' => 'Refund goodwill credit',
        ])->assertSessionHas('success');

        $refund = Refund::firstOrFail();
        $this->assertStringStartsWith('RF-2026-', $refund->refund_number);
        $this->assertSame(20000, $refund->amount_minor);
        $this->assertNull($refund->payment_id);
        $this->assertSame($creditNote->id, $refund->credit_note_id);
        $this->assertSame(0, $receivables->creditNoteAvailableMinor($creditNote->fresh()));
        $this->assertDatabaseHas('credit_notes', ['id' => $creditNote->id, 'total_minor' => 20000]);
        $this->assertSame('subscriber', $client->fresh()->status);
        $this->assertSame('active', $subscription->fresh()->status);

        $this->actingAs($admin)->post(route('credit-notes.applications.store', $creditNote), [
            'invoice_id' => $invoice->id,
            'amount' => '0.001',
        ])->assertSessionHasErrors('amount');
    }

    public function test_refund_from_unallocated_payment_credit_works_and_reversed_or_legacy_payments_cannot_fund_refunds(): void
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
        $invoice = $this->createInvoice($client, 100000, '2026-09-14');
        $payment = $this->recordPayment($admin, $client, '120.000', [
            ['invoice_id' => $invoice->id, 'amount' => '100.000'],
        ]);
        $receivables = app(ReceivableService::class);

        $this->assertSame(20000, $receivables->paymentUnallocatedMinor($payment->fresh()));
        $this->actingAs($admin)->post(route('payments.refunds.store', $payment), [
            'amount' => '15.000',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'refund_method' => 'cash',
            'refunded_at' => '2026-09-14 13:00:00',
            'reason' => 'Refund overpayment',
        ])->assertSessionHas('success');

        $this->assertSame(1, Refund::count());
        $this->assertSame(5000, $receivables->paymentUnallocatedMinor($payment->fresh()));
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'amount_minor' => 120000]);
        $this->assertSame('subscriber', $client->fresh()->status);
        $this->assertSame('active', $subscription->fresh()->status);

        $this->actingAs($admin)->post(route('payments.refunds.store', $payment), [
            'amount' => '5.001',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'refund_method' => 'cash',
            'refunded_at' => '2026-09-14 13:05:00',
            'reason' => 'Too much',
        ])->assertSessionHasErrors('amount');

        $reversedPayment = $this->recordPayment($admin, $client, '1.000');
        $this->actingAs($admin)->post(route('payments.reverse', $reversedPayment), [
            'reason' => 'Wrong receipt',
        ])->assertSessionHas('success');
        $this->assertSame(1, PaymentReversal::count());

        $this->actingAs($admin)->post(route('payments.refunds.store', $reversedPayment), [
            'amount' => '1.000',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'refund_method' => 'cash',
            'refunded_at' => '2026-09-14 13:10:00',
            'reason' => 'Blocked',
        ])->assertSessionHasErrors('payment_id');

        $legacy = Payment::create([
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'amount' => '10.000',
            'payment_method' => 'cash',
            'paid_at' => '2026-09-14 10:00:00',
            'recorded_by' => $admin->id,
        ]);
        $this->actingAs($admin)->post(route('payments.refunds.store', $legacy), [
            'amount' => '1.000',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'refund_method' => 'cash',
            'refunded_at' => '2026-09-14 13:15:00',
            'reason' => 'Legacy blocked',
        ])->assertSessionHasErrors('payment_id');
    }

    public function test_credit_note_voiding_requires_reason_blocks_active_applications_and_refunds_and_zeroes_available_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $invoice = $this->createInvoice($client, 100000, '2026-09-14');
        $active = $this->createCreditNote($admin, $client, '10.000', '0.000', $invoice, 'Active credit');
        $refunded = $this->createCreditNote($admin, $client, '10.000', '0.000', $invoice, 'Refunded credit');
        $unused = $this->createCreditNote($admin, $client, '10.000', '0.000', $invoice, 'Unused credit');
        $receivables = app(ReceivableService::class);

        $this->actingAs($admin)->post(route('credit-notes.applications.store', $active), [
            'invoice_id' => $invoice->id,
            'amount' => '10.000',
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post(route('credit-notes.refunds.store', $refunded), [
            'amount' => '10.000',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'refund_method' => 'cash',
            'refunded_at' => '2026-09-14 13:00:00',
            'reason' => 'Refunded',
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post(route('credit-notes.void', $active), [
            'void_reason' => 'Try active',
        ])->assertSessionHasErrors('credit_note_id');

        $this->actingAs($admin)->post(route('credit-notes.void', $refunded), [
            'void_reason' => 'Try refunded',
        ])->assertSessionHasErrors('credit_note_id');

        $this->actingAs($admin)->post(route('credit-notes.void', $unused), [])
            ->assertSessionHasErrors('void_reason');

        $this->actingAs($admin)->post(route('credit-notes.void', $unused), [
            'void_reason' => 'Issued by mistake',
        ])->assertSessionHas('success');

        $this->assertSame(CreditNote::STATUS_VOIDED, $unused->fresh()->status);
        $this->assertSame(0, $receivables->creditNoteAvailableMinor($unused->fresh()));
        $this->assertDatabaseHas('credit_note_lines', ['credit_note_id' => $unused->id]);
        $this->assertDatabaseHas('activity_logs', ['client_id' => $client->id, 'type' => 'credit_note_voided']);
    }

    public function test_customer_credit_projection_and_collections_queue_include_payment_and_credit_note_origins_after_refunds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $invoice = $this->createInvoice($client, 100000, '2026-09-14');
        $payment = $this->recordPayment($admin, $client, '120.000', [
            ['invoice_id' => $invoice->id, 'amount' => '100.000'],
        ]);
        $creditNote = $this->createCreditNote($admin, $client, '30.000', '0.000', $invoice);

        $this->actingAs($admin)->post(route('payments.refunds.store', $payment), [
            'amount' => '5.000',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'refund_method' => 'cash',
            'refunded_at' => '2026-09-14 13:00:00',
            'reason' => 'Partial refund',
        ])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('credit-notes.refunds.store', $creditNote), [
            'amount' => '10.000',
            'financial_account_id' => $this->cashAccount($admin)->id,
            'refund_method' => 'cash',
            'refunded_at' => '2026-09-14 13:05:00',
            'reason' => 'Partial credit refund',
        ])->assertSessionHas('success');

        $receivables = app(ReceivableService::class);
        $summary = $receivables->clientSummary($client);
        $credits = $receivables->availableCustomerCredits(['client_id' => $client->id]);

        $this->assertSame(15000, $summary['unallocated_payment_credit_minor']);
        $this->assertSame(20000, $summary['available_credit_note_minor']);
        $this->assertSame(35000, $summary['total_customer_credit_minor']);
        $this->assertSame(['credit_note', 'payment'], $credits->pluck('source_type')->sort()->values()->all());
        $this->actingAs($admin)->get(route('collections.index', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee('payment credit')
            ->assertSee('credit note');
    }

    public function test_partner_cannot_create_credit_notes_or_issue_refunds(): void
    {
        [$partner, $partnerUser] = $this->createPartnerUser();
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient(['partner_id' => $partner->id]);
        $invoice = $this->createInvoice($client, 10000, '2026-09-14');
        $payment = $this->recordPayment($admin, $client, '1.000');
        $creditNote = $this->createCreditNote($admin, $client, '1.000', '0.000', $invoice);

        $this->actingAs($partnerUser)->post(route('clients.credit-notes.store', $client), [
            'issue_date' => '2026-09-14',
            'reason' => 'Blocked',
            'lines' => [
                ['description' => 'Blocked', 'subtotal_jod' => '1.000', 'tax_jod' => '0.000'],
            ],
        ])->assertForbidden();

        $this->actingAs($partnerUser)->post(route('payments.refunds.store', $payment), [
            'amount' => '1.000',
            'refund_method' => 'cash',
            'refunded_at' => '2026-09-14 13:00:00',
            'reason' => 'Blocked',
        ])->assertForbidden();

        $this->actingAs($partnerUser)->post(route('credit-notes.refunds.store', $creditNote), [
            'amount' => '1.000',
            'refund_method' => 'cash',
            'refunded_at' => '2026-09-14 13:00:00',
            'reason' => 'Blocked',
        ])->assertForbidden();

        $this->assertSame(1, CreditNote::count());
        $this->assertSame(0, Refund::count());
    }

    public function test_no_delete_routes_exist_for_credit_notes_applications_or_refunds(): void
    {
        $routes = collect(app('router')->getRoutes())->map(fn ($route) => [
            'methods' => $route->methods(),
            'uri' => $route->uri(),
        ]);

        $deleteRoutes = $routes->filter(fn (array $route) => in_array('DELETE', $route['methods'], true));

        $this->assertFalse($deleteRoutes->contains(fn (array $route) => str_contains($route['uri'], 'credit-notes')));
        $this->assertFalse($deleteRoutes->contains(fn (array $route) => str_contains($route['uri'], 'credit-note-applications')));
        $this->assertFalse($deleteRoutes->contains(fn (array $route) => str_contains($route['uri'], 'refunds')));
    }

    private function createCreditNote(
        User $admin,
        Client $client,
        string $subtotalJod,
        string $taxJod,
        ?Invoice $invoice = null,
        string $reason = 'Commercial credit'
    ): CreditNote {
        $payload = [
            'issue_date' => '2026-09-14',
            'reason' => $reason,
            'lines' => [
                ['description' => $reason, 'subtotal_jod' => $subtotalJod, 'tax_jod' => $taxJod],
            ],
        ];

        if ($invoice !== null) {
            $payload['original_invoice_id'] = $invoice->id;
        }

        $this->actingAs($admin)->post(route('clients.credit-notes.store', $client), $payload)
            ->assertSessionHas('success');

        return CreditNote::where('client_id', $client->id)->orderByDesc('id')->firstOrFail();
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
            'business_name' => 'Phase C2B Client',
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
            'invoice_number' => 'INV-C2B-'.str_pad((string) (Invoice::count() + 1), 6, '0', STR_PAD_LEFT),
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
            'description_snapshot' => 'C2B test line',
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
            'company_name' => 'Phase C2B Partner',
            'email' => 'phase-c2b-partner@example.com',
        ]);

        $user = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        return [$partner, $user];
    }
}
