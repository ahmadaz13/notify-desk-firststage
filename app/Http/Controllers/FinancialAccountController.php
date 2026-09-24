<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\FinancialAccount;
use App\Models\FinancialTransfer;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\CashMovementService;
use App\Services\CompanyAccountBootstrapService;
use App\Services\FinancialAccountBalanceService;
use App\Services\FinancialAccountService;
use App\Support\Permissions;
use App\Support\ReportingPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinancialAccountController extends Controller
{
    public const TRANSFER_DIRECTIONS = [
        'cash_to_cliq' => [CompanyAccountBootstrapService::CASH_BOX_CODE, CompanyAccountBootstrapService::CLIQ_CODE],
        'cliq_to_cash' => [CompanyAccountBootstrapService::CLIQ_CODE, CompanyAccountBootstrapService::CASH_BOX_CODE],
    ];

    /**
     * Company Accounts (§10.4): Cash Box and CliQ with derived balances, a per-account movement list
     * with running balance for the selected period, internal transfers, and historical unassigned
     * cash events only when any exist. No account creation or archiving in the V1 UI.
     */
    public function index(Request $request, FinancialAccountBalanceService $balances): View
    {
        Gate::authorize(Permissions::VIEW_CASH_MANAGEMENT);

        $period = ReportingPeriod::fromRequest($request);
        $v1Accounts = FinancialAccount::query()
            ->whereIn('code', CompanyAccountBootstrapService::V1_CODES)
            ->get()
            ->sortBy(fn (FinancialAccount $account) => array_search($account->code, CompanyAccountBootstrapService::V1_CODES, true))
            ->values();
        $accountCards = $v1Accounts->map(fn (FinancialAccount $account) => [
            'account' => $account,
            'balance_minor' => $balances->currentBalanceMinor($account),
            'last_movement_at' => CashMovement::where('financial_account_id', $account->id)->max('occurred_at'),
        ]);
        $selected = $v1Accounts->firstWhere('code', $request->query('account')) ?? $v1Accounts->first();

        $movements = collect();
        $openingMinor = 0;
        if ($selected) {
            $openingMinor = $balances->balanceAsOfMinor($selected, $period->start->copy()->subSecond());
            $running = $openingMinor;
            $movements = CashMovement::query()
                ->where('financial_account_id', $selected->id)
                ->whereBetween('occurred_at', [$period->start, $period->end])
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->get()
                ->map(function (CashMovement $movement) use (&$running) {
                    $signed = $movement->direction === CashMovement::DIRECTION_INFLOW ? (int) $movement->amount_minor : -(int) $movement->amount_minor;
                    $running += $signed;

                    return ['movement' => $movement, 'signed_minor' => $signed, 'running_minor' => $running];
                })
                ->reverse()
                ->values();
        }
        $page = LengthAwarePaginator::resolveCurrentPage();
        $movementPage = new LengthAwarePaginator(
            $movements->forPage($page, 25)->values(),
            $movements->count(),
            25,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $otherAccounts = FinancialAccount::query()
            ->whereNotIn('code', CompanyAccountBootstrapService::V1_CODES)
            ->orderBy('name_ar')
            ->get()
            ->map(fn (FinancialAccount $account) => ['account' => $account, 'balance_minor' => $balances->currentBalanceMinor($account)])
            ->filter(fn (array $row) => $row['balance_minor'] !== 0 || $row['account']->is_active)
            ->values();
        $transfers = FinancialTransfer::with(['fromAccount', 'toAccount', 'reversal'])
            ->orderByDesc('transferred_at')
            ->orderByDesc('id')
            ->limit(10)
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

        return view('finance.accounts', [
            'period' => $period,
            'accountCards' => $accountCards,
            'selected' => $selected,
            'openingMinor' => $openingMinor,
            'movements' => $movementPage,
            'otherAccounts' => $otherAccounts,
            'transfers' => $transfers,
            'unassignedPayments' => $unassignedPayments,
            'unassignedRefunds' => $unassignedRefunds,
            'unassignedCount' => $unassignedCount,
            'v1Accounts' => $v1Accounts,
            'canTransfer' => Gate::allows(Permissions::MANAGE_CASH_TRANSFERS),
            'canAssignHistorical' => Gate::allows(Permissions::ASSIGN_HISTORICAL_CASH_ACCOUNTS),
        ]);
    }

    public function store(Request $request, FinancialAccountService $accounts): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_FINANCIAL_ACCOUNTS);

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
        Gate::authorize(Permissions::MANAGE_FINANCIAL_ACCOUNTS);

        $accounts->archiveAccount($financialAccount, $request->user()->id);

        return back()->with('success', 'تمت أرشفة الحساب المالي مع بقاء سجله قابلاً للقراءة.');
    }

    public function storeTransfer(Request $request, FinancialAccountService $accounts): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_CASH_TRANSFERS);

        // V1 UI sends a direction (Cash Box ↔ CliQ); explicit account ids remain accepted for the engine.
        $validated = $request->validate([
            'direction' => ['nullable', Rule::in(array_keys(self::TRANSFER_DIRECTIONS))],
            'from_financial_account_id' => 'required_without:direction|nullable|integer|exists:financial_accounts,id',
            'to_financial_account_id' => 'required_without:direction|nullable|integer|exists:financial_accounts,id',
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,3})?$/', 'not_regex:/^0+(\.0{1,3})?$/'],
            'transferred_at' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (! empty($validated['direction'])) {
            [$fromCode, $toCode] = self::TRANSFER_DIRECTIONS[$validated['direction']];
            $validated['from_financial_account_id'] = FinancialAccount::where('code', $fromCode)->valueOrFail('id');
            $validated['to_financial_account_id'] = FinancialAccount::where('code', $toCode)->valueOrFail('id');
        }

        $accounts->createTransfer($validated, $request->user()->id);

        return back()->with('success', 'تم إنشاء التحويل الداخلي.');
    }

    public function reverseTransfer(Request $request, FinancialTransfer $financialTransfer, FinancialAccountService $accounts): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_CASH_TRANSFERS);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $accounts->reverseTransfer($financialTransfer, $validated['reason'], $request->user()->id);

        return back()->with('success', 'تم عكس التحويل الداخلي مع الحفاظ على السجل.');
    }

    public function assignCashEvent(Request $request, CashMovementService $cashMovements): RedirectResponse
    {
        Gate::authorize(Permissions::ASSIGN_HISTORICAL_CASH_ACCOUNTS);

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
