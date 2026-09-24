<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CreditNote;
use App\Models\CreditNoteApplication;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentReceiptConfirmation;
use App\Services\CollectionCorrectionService;
use App\Services\CreditNoteService;
use App\Services\PaymentAllocationService;
use App\Services\PaymentFinancialAccountResolver;
use App\Services\ReceivableService;
use App\Services\RefundService;
use App\Support\Permissions;
use App\Support\PaymentMethods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CollectionsController extends Controller
{
    public const TABS = ['pending', 'due', 'partial', 'credits', 'payments'];

    /**
     * Collections & Receivables (§9.6): Owner tabs. Only the active tab's list is loaded; badge counts
     * come from the pending query and one receivables projection.
     */
    public function index(Request $request, ReceivableService $receivables)
    {
        Gate::authorize(Permissions::VIEW_FINANCIAL_REPORTS);

        $filters = $request->validate([
            'client_id' => 'nullable|integer|exists:clients,id',
            'tab' => ['nullable', Rule::in(self::TABS)],
        ]);
        $clientId = $filters['client_id'] ?? null;

        $pendingQuery = PaymentReceiptConfirmation::query()
            ->pending()
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId));
        $pendingCount = (clone $pendingQuery)->count();

        $outstanding = $receivables->outstandingInvoices(array_filter(['client_id' => $clientId]));
        $partial = $outstanding->filter(fn (array $item) => $item['projection']['settlement_status'] === 'partially_paid')->values();

        $tab = $filters['tab'] ?? ($pendingCount > 0 ? 'pending' : 'due');

        $pendingReceipts = $tab === 'pending'
            ? (clone $pendingQuery)->with(['client:id,business_name', 'submitter:id,name'])->orderBy('created_at')->orderBy('id')->get()
            : collect();
        $credits = $tab === 'credits' ? $receivables->availableCustomerCredits(array_filter(['client_id' => $clientId])) : collect();
        $payments = null;
        $paymentProjections = collect();
        if ($tab === 'payments') {
            $payments = Payment::with(['client:id,business_name', 'reversal'])
                ->where('payment_engine_version', Payment::ENGINE_V2)
                ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
                ->orderByDesc('received_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();
            $paymentProjections = $receivables->paymentProjections($payments->getCollection());
        }

        return view('finance.collections', [
            'tab' => $tab,
            'clientId' => $clientId,
            'client' => $clientId ? Client::find($clientId, ['id', 'business_name']) : null,
            'counts' => [
                'pending' => $pendingCount,
                'due' => $outstanding->count(),
                'partial' => $partial->count(),
            ],
            'pendingReceipts' => $pendingReceipts,
            'dueItems' => $tab === 'due'
                ? $outstanding->sortBy([
                    fn (array $a, array $b) => $b['projection']['is_overdue'] <=> $a['projection']['is_overdue'],
                    fn (array $a, array $b) => $a['invoice']->due_date <=> $b['invoice']->due_date,
                ])->values()
                : collect(),
            'partialItems' => $tab === 'partial' ? $partial : collect(),
            'credits' => $credits,
            'payments' => $payments,
            'paymentProjections' => $paymentProjections,
            'canApproveReceipts' => Gate::allows(Permissions::APPROVE_PAYMENT_RECEIPTS),
            'canRecordPayment' => Gate::allows(Permissions::RECORD_PAYMENT),
        ]);
    }

    public function storePayment(
        Request $request,
        Client $client,
        PaymentAllocationService $paymentAllocationService
    ): RedirectResponse {
        Gate::authorize(Permissions::RECORD_PAYMENT);
        Gate::authorize('view', $client);

        $validated = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,3})?$/', 'not_regex:/^0+(\.0{1,3})?$/'],
            'financial_account_id' => 'required|integer|exists:financial_accounts,id',
            'payment_method' => ['required', 'string', Rule::in(PaymentMethods::v1())],
            'reference' => 'nullable|string|max:255',
            'received_at' => 'required|date|before_or_equal:now',
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

    public function storeNormalPayment(
        Request $request,
        Client $client,
        PaymentAllocationService $paymentAllocationService,
        PaymentFinancialAccountResolver $accountResolver
    ): RedirectResponse {
        Gate::authorize(Permissions::RECORD_PAYMENT);
        Gate::authorize('view', $client);

        $validated = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,3})?$/', 'not_regex:/^0+(\.0{1,3})?$/'],
            'payment_method' => ['required', 'string', Rule::in(PaymentMethods::v1())],
            'received_at' => 'nullable|date|before_or_equal:now',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ], [
            'amount.required' => 'أدخل المبلغ المستلم.',
            'amount.regex' => 'المبلغ المستلم يجب أن يكون أكبر من صفر.',
            'amount.not_regex' => 'المبلغ المستلم يجب أن يكون أكبر من صفر.',
            'payment_method.required' => 'اختر طريقة الدفع.',
            'payment_method.in' => 'اختر طريقة دفع معتمدة.',
            'received_at.before_or_equal' => __('notify.payment_receipts.errors.future_date'),
        ]);

        $financialAccount = $accountResolver->resolve($validated['payment_method']);

        $paymentAllocationService->recordV2Payment(
            $client,
            [
                'amount' => $validated['amount'],
                'financial_account_id' => $financialAccount->id,
                'payment_method' => $validated['payment_method'],
                'received_at' => $validated['received_at'] ?? now()->format('Y-m-d H:i:s'),
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ],
            [],
            true,
            $request->user()->id
        );

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'تم تسجيل الدفعة وتحديث المبلغ المستحق.');
    }

    public function allocatePayment(
        Request $request,
        Payment $payment,
        PaymentAllocationService $paymentAllocationService
    ): RedirectResponse {
        Gate::authorize(Permissions::RECORD_PAYMENT);

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
        Gate::authorize(Permissions::RECORD_PAYMENT);

        $paymentAllocationService->autoAllocateOldest($payment, $request->user()->id);

        return back()->with('success', 'تم تخصيص الرصيد على أقدم الفواتير المستحقة.');
    }

    public function reverseAllocation(
        Request $request,
        PaymentAllocation $paymentAllocation,
        CollectionCorrectionService $correctionService
    ): RedirectResponse {
        Gate::authorize(Permissions::MANAGE_COLLECTION_CORRECTIONS);

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
        Gate::authorize(Permissions::MANAGE_COLLECTION_CORRECTIONS);

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
        Gate::authorize(Permissions::MANAGE_CREDIT_NOTES);
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
        Gate::authorize(Permissions::MANAGE_CREDIT_NOTES);

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
        Gate::authorize(Permissions::MANAGE_CREDIT_NOTES);

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
        Gate::authorize(Permissions::MANAGE_CREDIT_NOTES);

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
        Gate::authorize(Permissions::ISSUE_REFUNDS);

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
        Gate::authorize(Permissions::ISSUE_REFUNDS);

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
