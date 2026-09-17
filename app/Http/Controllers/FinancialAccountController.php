<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\FinancialAccount;
use App\Models\FinancialTransfer;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\CashMovementService;
use App\Services\FinancialAccountBalanceService;
use App\Services\FinancialAccountService;
use App\Support\FinancialPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinancialAccountController extends Controller
{
    public function index(FinancialAccountBalanceService $balances): View
    {
        Gate::authorize(FinancialPermissions::VIEW_CASH_MANAGEMENT);

        $accountCards = $balances->accountCards();
        $activeAccounts = FinancialAccount::where('is_active', true)->whereNull('archived_at')->orderBy('name_ar')->get();
        $recentMovements = $balances->recentMovements();
        $transfers = FinancialTransfer::with(['fromAccount', 'toAccount', 'reversal'])
            ->orderByDesc('transferred_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
        $unassignedPayments = Payment::with('client')
            ->where('payment_engine_version', Payment::ENGINE_V2)
            ->whereNotNull('amount_minor')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('cash_movements')
                    ->where('cash_movements.event_type', CashMovement::EVENT_PAYMENT_RECEIVED)
                    ->whereColumn('cash_movements.source_id', 'payments.id')
                    ->where('cash_movements.source_type', Payment::class);
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('payment_reversals')
                    ->whereColumn('payment_reversals.payment_id', 'payments.id');
            })
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
        $unassignedRefunds = Refund::with(['client', 'payment', 'creditNote'])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('cash_movements')
                    ->where('cash_movements.event_type', CashMovement::EVENT_REFUND_ISSUED)
                    ->whereColumn('cash_movements.source_id', 'refunds.id')
                    ->where('cash_movements.source_type', Refund::class);
            })
            ->orderBy('refunded_at')
            ->orderBy('id')
            ->get();
        $unassignedCount = $unassignedPayments->count() + $unassignedRefunds->count();
        $totalOperationalCashMinor = $balances->companyCashMinor();
        $todayInflowsMinor = $accountCards->sum('today_inflows_minor');
        $todayOutflowsMinor = $accountCards->sum('today_outflows_minor');

        return view('financial-accounts.index', compact(
            'accountCards',
            'activeAccounts',
            'recentMovements',
            'transfers',
            'unassignedPayments',
            'unassignedRefunds',
            'unassignedCount',
            'totalOperationalCashMinor',
            'todayInflowsMinor',
            'todayOutflowsMinor'
        ));
    }

    public function store(Request $request, FinancialAccountService $accounts): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_FINANCIAL_ACCOUNTS);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:financial_accounts,code',
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'type' => ['required', 'string', Rule::in(FinancialAccount::TYPES)],
            'notes' => 'nullable|string|max:1000',
            'opening_balance' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,3})?$/'],
            'opening_date' => 'nullable|date',
        ]);

        $validated['opening_balance'] = $validated['opening_balance'] ?? '0';
        $accounts->createAccount($validated, $request->user()->id);

        return back()->with('success', 'تم إنشاء الحساب المالي.');
    }

    public function archive(FinancialAccount $financialAccount, FinancialAccountService $accounts, Request $request): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_FINANCIAL_ACCOUNTS);

        $accounts->archiveAccount($financialAccount, $request->user()->id);

        return back()->with('success', 'تمت أرشفة الحساب المالي مع بقاء سجله قابلاً للقراءة.');
    }

    public function storeTransfer(Request $request, FinancialAccountService $accounts): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_CASH_TRANSFERS);

        $validated = $request->validate([
            'from_financial_account_id' => 'required|integer|exists:financial_accounts,id',
            'to_financial_account_id' => 'required|integer|exists:financial_accounts,id',
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,3})?$/', 'not_regex:/^0+(\.0{1,3})?$/'],
            'transferred_at' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $accounts->createTransfer($validated, $request->user()->id);

        return back()->with('success', 'تم إنشاء التحويل الداخلي.');
    }

    public function reverseTransfer(Request $request, FinancialTransfer $financialTransfer, FinancialAccountService $accounts): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_CASH_TRANSFERS);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $accounts->reverseTransfer($financialTransfer, $validated['reason'], $request->user()->id);

        return back()->with('success', 'تم عكس التحويل الداخلي مع الحفاظ على السجل.');
    }

    public function assignCashEvent(Request $request, CashMovementService $cashMovements): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::ASSIGN_HISTORICAL_CASH_ACCOUNTS);

        $validated = $request->validate([
            'event_type' => ['required', 'string', Rule::in(['payment', 'refund'])],
            'event_id' => 'required|integer',
            'financial_account_id' => 'required|integer|exists:financial_accounts,id',
        ]);
        $account = FinancialAccount::findOrFail((int) $validated['financial_account_id']);

        if ($validated['event_type'] === 'payment') {
            $cashMovements->assignHistoricalPayment(Payment::findOrFail((int) $validated['event_id']), $account, $request->user()->id);
        } else {
            $cashMovements->assignHistoricalRefund(Refund::findOrFail((int) $validated['event_id']), $account, $request->user()->id);
        }

        return back()->with('success', 'تم تعيين الحساب المالي للحدث النقدي التاريخي.');
    }
}
