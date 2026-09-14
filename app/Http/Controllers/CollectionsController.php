<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CreditNote;
use App\Models\CreditNoteApplication;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\CollectionCorrectionService;
use App\Services\CreditNoteService;
use App\Services\PaymentAllocationService;
use App\Services\ReceivableService;
use App\Services\RefundService;
use App\Support\FinancialPermissions;
use App\Support\PaymentMethods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CollectionsController extends Controller
{
    public function index(Request $request, ReceivableService $receivables)
    {
        Gate::authorize(FinancialPermissions::VIEW_FINANCIAL_REPORTS);

        $filters = $request->validate([
            'client_id' => 'nullable|integer|exists:clients,id',
            'due_state' => ['nullable', Rule::in(['all', 'overdue', 'not_due'])],
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'settlement_state' => ['nullable', Rule::in(['unpaid', 'partially_paid', 'paid'])],
        ]);
        $filters['due_state'] = ($filters['due_state'] ?? 'all') === 'all' ? null : $filters['due_state'];

        $outstandingInvoices = $receivables->outstandingInvoices($filters);
        $overdueInvoices = $receivables->outstandingInvoices(array_merge($filters, ['due_state' => 'overdue']));
        $partiallyPaidInvoices = $receivables->outstandingInvoices(array_merge($filters, ['settlement_state' => 'partially_paid']));
        $unallocatedCredits = $receivables->unallocatedCredits($filters);
        $availableCustomerCredits = $receivables->availableCustomerCredits($filters);
        $clients = Client::orderBy('business_name')->get(['id', 'business_name']);

        return view('collections.index', compact(
            'filters',
            'outstandingInvoices',
            'overdueInvoices',
            'partiallyPaidInvoices',
            'unallocatedCredits',
            'availableCustomerCredits',
            'clients'
        ));
    }

    public function storePayment(
        Request $request,
        Client $client,
        PaymentAllocationService $paymentAllocationService
    ): RedirectResponse {
        Gate::authorize(FinancialPermissions::RECORD_PAYMENT);
        Gate::authorize('view', $client);

        $validated = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,3})?$/', 'not_regex:/^0+(\.0{1,3})?$/'],
            'financial_account_id' => 'required|integer|exists:financial_accounts,id',
            'payment_method' => ['required', 'string', Rule::in(PaymentMethods::values())],
            'reference' => 'nullable|string|max:255',
            'received_at' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'auto_allocate_oldest' => 'nullable|boolean',
            'allocations' => 'nullable|array',
            'allocations.*.invoice_id' => 'nullable|integer|exists:invoices,id',
            'allocations.*.amount' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,3})?$/', 'not_regex:/^0+(\.0{1,3})?$/'],
        ]);

        $allocations = collect($validated['allocations'] ?? [])
            ->filter(fn (array $allocation) => filled($allocation['invoice_id'] ?? null) && filled($allocation['amount'] ?? null))
            ->values()
            ->all();

        $paymentAllocationService->recordV2Payment(
            $client,
            $validated,
            $allocations,
            (bool) ($validated['auto_allocate_oldest'] ?? false),
            $request->user()->id
        );

        return back()->with('success', 'تم تسجيل دفعة V2 وتحديث أرصدة الذمم.');
    }

    public function allocatePayment(
        Request $request,
        Payment $payment,
        PaymentAllocationService $paymentAllocationService
    ): RedirectResponse {
        Gate::authorize(FinancialPermissions::RECORD_PAYMENT);

        $validated = $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,3})?$/', 'not_regex:/^0+(\.0{1,3})?$/'],
        ]);

        $invoice = Invoice::findOrFail((int) $validated['invoice_id']);
        $paymentAllocationService->allocate($payment, $invoice, $validated['amount'], $request->user()->id);

        return back()->with('success', 'تم تخصيص الرصيد غير المستخدم على الفاتورة.');
    }

    public function autoAllocatePayment(
        Request $request,
        Payment $payment,
        PaymentAllocationService $paymentAllocationService
    ): RedirectResponse {
        Gate::authorize(FinancialPermissions::RECORD_PAYMENT);

        $paymentAllocationService->autoAllocateOldest($payment, $request->user()->id);

        return back()->with('success', 'تم تخصيص الرصيد على أقدم الفواتير المستحقة.');
    }

    public function reverseAllocation(
        Request $request,
        PaymentAllocation $paymentAllocation,
        CollectionCorrectionService $correctionService
    ): RedirectResponse {
        Gate::authorize(FinancialPermissions::MANAGE_COLLECTION_CORRECTIONS);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $correctionService->reverseAllocation($paymentAllocation, $validated['reason'], $request->user()->id);

        return back()->with('success', 'تم عكس تخصيص الدفعة مع الحفاظ على السجل.');
    }

    public function reversePayment(
        Request $request,
        Payment $payment,
        CollectionCorrectionService $correctionService
    ): RedirectResponse {
        Gate::authorize(FinancialPermissions::MANAGE_COLLECTION_CORRECTIONS);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $correctionService->reversePayment($payment, $validated['reason'], $request->user()->id);

        return back()->with('success', 'تم عكس الدفعة كتصحيح سجل وليس كاسترداد.');
    }

    public function storeCreditNote(
        Request $request,
        Client $client,
        CreditNoteService $creditNoteService
    ): RedirectResponse {
        Gate::authorize(FinancialPermissions::MANAGE_CREDIT_NOTES);
        Gate::authorize('view', $client);

        $validated = $request->validate([
            'original_invoice_id' => 'nullable|integer|exists:invoices,id',
            'issue_date' => 'required|date',
            'reason' => 'required|string|max:1000',
            'lines' => 'required|array|min:1',
            'lines.*.invoice_line_id' => 'nullable|integer|exists:invoice_lines,id',
            'lines.*.description' => 'required|string|max:255',
            'lines.*.quantity' => 'nullable|integer|min:1|max:999',
            'lines.*.subtotal_jod' => ['required', 'string', 'regex:/^\d+(\.\d{1,3})?$/', 'not_regex:/^0+(\.0{1,3})?$/'],
            'lines.*.tax_jod' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,3})?$/'],
        ]);

        $creditNoteService->createIssued($client, $validated, $request->user()->id);

        return back()->with('success', 'تم إنشاء وإصدار إشعار الدائن مع حفظ السجل.');
    }

    public function applyCreditNote(
        Request $request,
        CreditNote $creditNote,
        CreditNoteService $creditNoteService
    ): RedirectResponse {
        Gate::authorize(FinancialPermissions::MANAGE_CREDIT_NOTES);

        $validated = $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,3})?$/', 'not_regex:/^0+(\.0{1,3})?$/'],
        ]);

        $invoice = Invoice::findOrFail((int) $validated['invoice_id']);
        $creditNoteService->applyCredit($creditNote, $invoice, $validated['amount'], $request->user()->id);

        return back()->with('success', 'تم تطبيق رصيد إشعار الدائن على الفاتورة.');
    }

    public function reverseCreditApplication(
        Request $request,
        CreditNoteApplication $creditNoteApplication,
        CreditNoteService $creditNoteService
    ): RedirectResponse {
        Gate::authorize(FinancialPermissions::MANAGE_CREDIT_NOTES);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $creditNoteService->reverseApplication($creditNoteApplication, $validated['reason'], $request->user()->id);

        return back()->with('success', 'تم عكس تطبيق رصيد إشعار الدائن مع الحفاظ على السجل.');
    }

    public function voidCreditNote(
        Request $request,
        CreditNote $creditNote,
        CreditNoteService $creditNoteService
    ): RedirectResponse {
        Gate::authorize(FinancialPermissions::MANAGE_CREDIT_NOTES);

        $validated = $request->validate([
            'void_reason' => 'required|string|max:1000',
        ]);

        $creditNoteService->void($creditNote, $validated['void_reason'], $request->user()->id);

        return back()->with('success', 'تم إلغاء إشعار الدائن المؤهل مع حفظ السجل.');
    }

    public function refundPayment(
        Request $request,
        Payment $payment,
        RefundService $refundService
    ): RedirectResponse {
        Gate::authorize(FinancialPermissions::ISSUE_REFUNDS);

        $validated = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,3})?$/', 'not_regex:/^0+(\.0{1,3})?$/'],
            'financial_account_id' => 'required|integer|exists:financial_accounts,id',
            'refund_method' => ['required', 'string', Rule::in(PaymentMethods::values())],
            'reference' => 'nullable|string|max:255',
            'reason' => 'required|string|max:1000',
            'refunded_at' => 'required|date',
        ]);

        $refundService->refundFromPayment($payment, $validated, $request->user()->id);

        return back()->with('success', 'تم تسجيل استرداد من رصيد دفعة غير مخصص.');
    }

    public function refundCreditNote(
        Request $request,
        CreditNote $creditNote,
        RefundService $refundService
    ): RedirectResponse {
        Gate::authorize(FinancialPermissions::ISSUE_REFUNDS);

        $validated = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,3})?$/', 'not_regex:/^0+(\.0{1,3})?$/'],
            'financial_account_id' => 'required|integer|exists:financial_accounts,id',
            'refund_method' => ['required', 'string', Rule::in(PaymentMethods::values())],
            'reference' => 'nullable|string|max:255',
            'reason' => 'required|string|max:1000',
            'refunded_at' => 'required|date',
        ]);

        $refundService->refundFromCreditNote($creditNote, $validated, $request->user()->id);

        return back()->with('success', 'تم تسجيل استرداد من رصيد إشعار دائن.');
    }
}
