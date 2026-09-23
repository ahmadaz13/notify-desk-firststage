<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\PaymentReceiptConfirmation;
use App\Services\PaymentReceiptService;
use App\Support\FinancialPermissions;
use App\Support\PaymentMethods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PaymentReceiptController extends Controller
{
    public function store(Request $request, Client $client, PaymentReceiptService $receipts): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::SUBMIT_PAYMENT_RECEIPT);
        Gate::authorize('view', $client);

        $validated = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,3})?$/', 'not_regex:/^0+(\.0{1,3})?$/'],
            'payment_method' => ['required', 'string', Rule::in(PaymentMethods::v1())],
            'received_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'amount.required' => __('notify.payment_receipts.errors.amount_positive'),
            'amount.regex' => __('notify.payment_receipts.errors.amount_positive'),
            'amount.not_regex' => __('notify.payment_receipts.errors.amount_positive'),
            'payment_method.required' => __('notify.payment_receipts.errors.unsupported_method'),
            'payment_method.in' => __('notify.payment_receipts.errors.unsupported_method'),
        ]);

        $receipts->submit(
            $client,
            $validated,
            $request->user(),
            $request->input('_idempotency_key') ?: $request->header('X-Idempotency-Key')
        );

        return redirect()
            ->route('clients.show', $client)
            ->with('success', __('notify.payment_receipts.flash.submitted'));
    }

    public function approve(Request $request, PaymentReceiptConfirmation $paymentReceipt, PaymentReceiptService $receipts): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::APPROVE_PAYMENT_RECEIPTS);

        $receipts->approve($paymentReceipt, $request->user());

        return back()->with('success', __('notify.payment_receipts.flash.approved'));
    }

    public function reject(Request $request, PaymentReceiptConfirmation $paymentReceipt, PaymentReceiptService $receipts): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::APPROVE_PAYMENT_RECEIPTS);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ], [
            'rejection_reason.required' => __('notify.payment_receipts.errors.reason_required'),
        ]);

        $receipts->reject($paymentReceipt, $request->user(), $validated['rejection_reason']);

        return back()->with('success', __('notify.payment_receipts.flash.rejected'));
    }

    public function cancel(Request $request, PaymentReceiptConfirmation $paymentReceipt, PaymentReceiptService $receipts): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::SUBMIT_PAYMENT_RECEIPT);

        $receipts->cancel($paymentReceipt, $request->user());

        return back()->with('success', __('notify.payment_receipts.flash.cancelled'));
    }
}
