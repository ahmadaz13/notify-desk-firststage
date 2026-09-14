<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\DailyNote;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\DailyOperationalService;
use App\Services\FreeInstallationService;
use App\Support\AppointmentTypes;
use App\Support\FinancialPermissions;
use App\Support\PaymentMethods;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function __construct(
        protected DailyOperationalService $dailyOpsService
    ) {}

    public function index()
    {
        if (auth()->check() && auth()->user()->isPartner()) {
            return app(PartnerDashboardController::class)->index(request());
        }

        $user = auth()->user();
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $clients = DB::table('clients')->where('status', '!=', 'archived')->count();
        $prospects = DB::table('clients')->where('status', 'prospect')->count();
        $subscribers = DB::table('clients')->where('status', 'subscriber')->count();
        $appointments = DB::table('appointments')
            ->join('clients', 'clients.id', '=', 'appointments.client_id')
            ->whereDate('appointment_date', $today)
            ->orderBy('appointment_time')
            ->get(['appointments.*', 'clients.business_name', 'clients.phone']);

        $nextAppointment = DB::table('appointments')
            ->join('clients', 'clients.id', '=', 'appointments.client_id')
            ->where('appointment_date', '>=', $today)
            ->whereIn('appointments.status', ['scheduled', 'confirmed'])
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->first(['appointments.*', 'clients.business_name', 'clients.phone']);

        // Financial KPIs
        $todayCollections = (float) DB::table('payments')->whereDate('paid_at', $today)->sum('amount');
        $monthIncome = (float) DB::table('payments')->whereBetween('paid_at', [$monthStart->startOfDay(), $today->copy()->endOfDay()])->sum('amount');
        $monthExpenses = (float) DB::table('expenses')->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()])->sum('amount');
        $netCashResult = $monthIncome - $monthExpenses;
        $overdueCollections = (float) DB::table('payment_schedules')->whereDate('due_date', '<', $today)->whereNotIn('status', ['paid', 'cancelled'])->sum('amount_due');

        // Backward compatibility mappings
        $collected = $monthIncome;
        $expenses = $monthExpenses;
        $overdue = $overdueCollections;

        // Unread notifications for current user
        $unreadNotifications = DB::table('notifications')
            ->where('user_id', auth()->id())
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $reminders = DB::table('clients')->whereNotNull('status')->where('status', '!=', 'archived')->orderBy('updated_at', 'desc')->limit(4)->get();

        // Operational Cockpit Metrics
        $dailySnapshot = $this->dailyOpsService->getTodaySnapshot($user);
        $recentExpenses = $this->dailyOpsService->getRecentExpenses($user, 5);
        $todayAppointments = $this->dailyOpsService->getTodayAppointments($user);
        $pendingFollowUps = $this->dailyOpsService->getPendingFollowUps($user);
        $dailyNote = DailyNote::where('user_id', $user->id)->whereDate('date', $today)->first();
        $expenseCategories = ExpenseCategory::active()->get();
        $teamUsers = User::where('role', 'admin')->get();
        $paymentMethodOptions = PaymentMethods::labels();

        $financialMetrics = $this->calculateFinancialMetrics();
        $investments = DB::table('investments')->orderByDesc('entry_date')->get();

        $isPartner = auth()->user()->isPartner();
        $partner = null;
        $totalClientPayments = 0.0;
        $netRevenue = 0.0;
        $earnedShare = null;

        if ($isPartner) {
            $partner = auth()->user()->partner;
            if ($partner) {
                $totalClientPayments = (float) $partner->total_client_payments;
                $deduction = $partner->deduction_percentage !== null ? (float) $partner->deduction_percentage : 20.0;
                $netRevenue = (float) ($totalClientPayments * (1 - ($deduction / 100)));
                $earnedShare = $partner->earned_share;
            }
        }

        $requestedMode = request()->query('mode', 'daily');
        $mode = in_array($requestedMode, ['daily', 'financial'], true) ? $requestedMode : 'daily';
        $currentMode = $mode;

        return view('dashboard', array_merge(compact(
            'clients', 'prospects', 'subscribers', 'appointments', 'nextAppointment',
            'collected', 'expenses', 'overdue', 'reminders',
            'todayCollections', 'monthIncome', 'monthExpenses', 'netCashResult', 'overdueCollections',
            'unreadNotifications', 'investments',
            'isPartner', 'partner', 'totalClientPayments', 'netRevenue', 'earnedShare',
            'dailySnapshot', 'recentExpenses', 'todayAppointments', 'pendingFollowUps', 'dailyNote', 'expenseCategories', 'teamUsers',
            'currentMode', 'mode', 'paymentMethodOptions'
        ), $financialMetrics));
    }

    /**
     * @deprecated Use ClientController::index() instead.
     */
    public function clients(Request $request)
    {
        return app(ClientController::class)->index($request);
    }

    /**
     * @deprecated Use ClientController::show() instead.
     */
    public function showClient(int $client)
    {
        return app(ClientController::class)->show($client);
    }

    /**
     * @deprecated Use ClientController::store() instead.
     */
    public function storeClient(Request $request)
    {
        return app(ClientController::class)->store($request);
    }

    public function storeAppointment(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'appointment_type' => 'required|string',
            'location' => 'nullable|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'attendees' => 'nullable|array',
            'attendees.*' => 'exists:users,id',
        ]);
        $clientModel = \App\Models\Client::findOrFail($data['client_id']);
        \Illuminate\Support\Facades\Gate::authorize('update', $clientModel);

        DB::transaction(function () use ($data) {
            $appointment = Appointment::create([
                'client_id' => $data['client_id'],
                'appointment_date' => $data['appointment_date'],
                'appointment_time' => $data['appointment_time'],
                'appointment_type' => $data['appointment_type'],
                'location' => $data['location'] ?? null,
                'branch_name' => $data['branch_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'scheduled',
            ]);

            $attendees = !empty($data['attendees']) ? $data['attendees'] : [auth()->id()];
            $appointment->users()->sync($attendees);

            $this->log($data['client_id'], 'appointment_created', 'تم جدولة موعد جديد');
        });

        return back()->with('success', 'تم جدولة الموعد.');
    }

    public function updateAppointment(Request $request, int $appointment, FreeInstallationService $freeInstallationService)
    {
        $data = $request->validate([
            'status' => 'required|in:scheduled,confirmed,rescheduled,cancelled,no_show,completed',
            'next_stage' => 'nullable|string',
        ]);
        $item = Appointment::with('client')->findOrFail($appointment);
        $clientModel = $item->client;
        \Illuminate\Support\Facades\Gate::authorize('update', $clientModel);

        if ($item->appointment_type === AppointmentTypes::INSTALLATION && $data['status'] === 'cancelled') {
            $freeInstallationService->cancelInstallationAppointment($item, $request->user(), $data['next_stage'] ?? null);

            return back()->with('success', 'تم تحديث حالة الموعد.');
        }

        DB::transaction(function () use ($data, $item) { DB::table('appointments')->where('id', $item->id)->update(['status' => $data['status'], 'updated_at' => now()]); $this->log($item->client_id, 'appointment_'.$data['status'], 'تم تحديث حالة الموعد إلى '.$data['status']); });
        return back()->with('success', 'تم تحديث حالة الموعد.');
    }

    public function storePayment(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => ['required', 'string', Rule::in(PaymentMethods::values())],
            'paid_at' => 'required|date',
        ]);
        Gate::authorize(FinancialPermissions::RECORD_PAYMENT);

        $clientModel = \App\Models\Client::findOrFail($data['client_id']);
        $subscription = DB::table('subscriptions')
            ->where('client_id', $clientModel->id)
            ->whereIn('status', ['active', 'payment_due'])
            ->latest('id')
            ->first();

        if (! $subscription) {
            throw ValidationException::withMessages([
                'client_id' => 'لا يمكن تسجيل دفعة قبل وجود اشتراك صالح للعميل.',
            ]);
        }

        DB::transaction(function () use ($data, $subscription) {
            DB::table('payments')->insert(array_merge($data, [
                'subscription_id' => $subscription->id,
                'recorded_by' => auth()->id(),
                'paid_at' => Carbon::parse($data['paid_at']),
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $this->log($data['client_id'], 'payment_received', 'تم تسجيل دفعة بقيمة '.$data['amount'].' د.أ');
        });
        return back()->with('success', 'تم تسجيل الدفعة.');
    }

    public function storeExpense(Request $request)
    {
        Gate::authorize(FinancialPermissions::MANAGE_EXPENSES);

        $data = $request->validate(['amount' => 'required|numeric|min:0.01', 'category' => 'required|string|max:120', 'date' => 'required|date', 'notes' => 'nullable|string']);
        DB::table('expenses')->insert(array_merge($data, ['paid_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]));
        return back()->with('success', 'تم تسجيل المصروف.');
    }

    /**
     * @deprecated Use ClientController::convert() instead.
     */
    public function convert(Request $request, int $client)
    {
        return app(ClientController::class)->convert($request, $client);
    }

    private function log(int $clientId, string $type, string $description): void
    {
        DB::table('activity_logs')->insert(['client_id' => $clientId, 'user_id' => auth()->id(), 'type' => $type, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function calculateFinancialMetrics(): array
    {
        $totalGrossRevenue = (float) DB::table('payments')->sum('amount');
        $allTimeCollections = $totalGrossRevenue;

        $opCostSetting = DB::table('settings')->where('key', 'operational_cost_percentage')->value('value');
        $operationalCostPercentage = (is_numeric($opCostSetting) && (float) $opCostSetting >= 0)
            ? (float) $opCostSetting
            : 20.0;

        $operationalCost = $totalGrossRevenue * ($operationalCostPercentage / 100);
        $netOperatingRevenue = $totalGrossRevenue - $operationalCost;

        // Actual operational expenses recorded
        $allTimeOperationalExpenses = (float) DB::table('expenses')->sum('amount');
        $netProfit = $netOperatingRevenue - $allTimeOperationalExpenses;

        // Corrected ARR: (Active monthly subscriptions * 12) + (Active annual subscriptions) + (Active installment subscriptions)
        $activeSubscriptions = DB::table('subscriptions')
            ->where('status', 'active')
            ->get();

        $monthlyTotal = 0.0;
        $annualTotal = 0.0;
        $installmentTotal = 0.0;

        foreach ($activeSubscriptions as $sub) {
            $billing = strtolower($sub->billing_type ?? $sub->billing_cycle ?? '');
            $price = (float) $sub->total_price;
            if ($billing === 'monthly') {
                $monthlyTotal += $price;
            } elseif ($billing === 'annual') {
                $annualTotal += $price;
            } elseif ($billing === 'installment') {
                $installmentTotal += $price;
            }
        }

        $annualRecurringRevenue = ($monthlyTotal * 12) + $annualTotal + $installmentTotal;

        $multiplierSetting = DB::table('settings')->where('key', 'market_valuation_multiplier')->value('value');
        $marketMultiplier = (is_numeric($multiplierSetting) && (float) $multiplierSetting >= 0)
            ? (float) $multiplierSetting
            : 5.0;

        // Market Valuation = ARR * marketMultiplier (no longer explodes with cumulative historical revenue)
        $estimatedMarketValue = $annualRecurringRevenue * $marketMultiplier;

        $totalInvestments = (float) DB::table('investments')->sum('amount');
        $totalCapitalExpenses = (float) DB::table('capital_expenses')->sum('amount');

        // Corrected Liquidity: (Investments + All Collections) - (Capital Expenses + Operational Expenses)
        $liquidityBalance = ($totalInvestments + $allTimeCollections) - ($totalCapitalExpenses + $allTimeOperationalExpenses);

        return [
            'total_gross_revenue' => $totalGrossRevenue,
            'operational_cost_percentage' => $operationalCostPercentage,
            'operational_cost' => $operationalCost,
            'net_operating_revenue' => $netOperatingRevenue,
            'all_time_operational_expenses' => $allTimeOperationalExpenses,
            'net_profit' => $netProfit,
            'annual_recurring_revenue' => $annualRecurringRevenue,
            'market_multiplier' => $marketMultiplier,
            'estimated_market_value' => $estimatedMarketValue,
            'total_investments' => $totalInvestments,
            'total_capital_expenses' => $totalCapitalExpenses,
            'all_time_collections' => $allTimeCollections,
            'liquidity_balance' => $liquidityBalance,
        ];
    }
}
