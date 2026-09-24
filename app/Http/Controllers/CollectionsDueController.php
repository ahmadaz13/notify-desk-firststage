<?php

namespace App\Http\Controllers;

use App\Models\PaymentReceiptConfirmation;
use App\Services\ReceivableService;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Staff "Collections due" (§9.7, D-07): an operational list, not a finance dashboard.
 * Per-client amounts due and next due date plus the user's own pending receipts. No company totals,
 * balances, P&L, accounting, reports or capital are computed or rendered here.
 */
class CollectionsDueController extends Controller
{
    public function index(Request $request, ReceivableService $receivables): View
    {
        Gate::authorize(Permissions::VIEW_COLLECTIONS_DUE);

        $rows = $receivables->outstandingInvoices()
            ->groupBy(fn (array $item) => $item['invoice']->client_id)
            ->map(function ($items) {
                $invoices = $items->pluck('invoice');
                $overdue = $items->filter(fn (array $item) => $item['projection']['is_overdue']);

                return [
                    'client' => $invoices->first()->client,
                    'due_minor' => (int) $items->sum(fn (array $item) => $item['projection']['outstanding_minor']),
                    'overdue_minor' => (int) $overdue->sum(fn (array $item) => $item['projection']['outstanding_minor']),
                    'is_overdue' => $overdue->isNotEmpty(),
                    'next_due_date' => $invoices->pluck('due_date')->filter()->sort()->first(),
                ];
            })
            ->sortBy([
                fn (array $a, array $b) => $b['is_overdue'] <=> $a['is_overdue'],
                fn (array $a, array $b) => $a['next_due_date'] <=> $b['next_due_date'],
            ])
            ->values();

        $myPendingReceipts = PaymentReceiptConfirmation::with('client:id,business_name')
            ->pending()
            ->where('submitted_by', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return view('collections-due.index', [
            'rows' => $rows,
            'myPendingReceipts' => $myPendingReceipts,
            'pendingByClient' => $myPendingReceipts->groupBy('client_id'),
        ]);
    }
}
