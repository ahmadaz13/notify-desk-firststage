<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\DailyNote;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\DailyOperationalService;
use App\Services\FreeInstallationService;
use App\Services\OperationalQueueService;
use App\Services\UnifiedOperationalWorkProjection;
use App\Support\AppointmentTypes;
use App\Support\FinancialPermissions;
use App\Support\PaymentMethods;
use App\ViewModels\TodayViewModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __construct(
        protected DailyOperationalService $dailyOpsService,
        protected OperationalQueueService $operationalQueueService,
        protected UnifiedOperationalWorkProjection $unifiedWorkProjection
    ) {}

    public function index()
    {
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
        $overdueCollections = (float) DB::table('payment_schedules')
            ->whereNull('schedule_engine_version')
            ->whereDate('due_date', '<', $today)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->sum('amount_due');

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
        $operationalQueues = $this->operationalQueueService->queues($user, $today->copy()->endOfDay());
        $recentExpenses = $this->dailyOpsService->getRecentExpenses($user, 5);
        $todayAppointments = $this->dailyOpsService->getTodayAppointments($user);
        $pendingFollowUps = $this->dailyOpsService->getPendingFollowUps($user);
        $dailyNote = DailyNote::where('user_id', $user->id)->whereDate('date', $today)->first();
        $expenseCategories = ExpenseCategory::active()->get();
        $teamUsers = User::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('role')
                    ->orWhereIn('role', User::activeInternalRoles());
            })
            ->orderBy('name')
            ->get();
        $paymentMethodOptions = PaymentMethods::labels();

        $legacyFinancialSummary = $this->legacyFinancialSummary();
        $investments = DB::table('investments')->orderByDesc('entry_date')->get();

        $isPartner = false;
        $partner = null;
        $totalClientPayments = 0.0;
        $netRevenue = 0.0;
        $earnedShare = null;

        $requestedMode = request()->query('mode', 'daily');
        $mode = in_array($requestedMode, ['daily', 'work', 'financial'], true) ? $requestedMode : 'daily';
        $currentMode = $mode;

        $businessNow = Carbon::now('Asia/Amman');
        $todayProjection = $this->unifiedWorkProjection->today($user, $businessNow);
        $requestedFilter = request()->query('filter', UnifiedOperationalWorkProjection::FILTER_ALL);
        $workProjection = $this->unifiedWorkProjection->work($user, $requestedFilter, $businessNow);

        $todayViewModel = TodayViewModel::make(
            $dailySnapshot,
            $operationalQueues,
            $unreadNotifications,
            $recentExpenses,
            $todayProjection,
            $workProjection
        );

        return view('dashboard', compact(
            'clients', 'prospects', 'subscribers', 'appointments', 'nextAppointment',
            'collected', 'expenses', 'overdue', 'reminders',
            'todayCollections', 'monthIncome', 'monthExpenses', 'netCashResult', 'overdueCollections',
            'unreadNotifications', 'investments',
            'isPartner', 'partner', 'totalClientPayments', 'netRevenue', 'earnedShare',
            'dailySnapshot', 'recentExpenses', 'todayAppointments', 'pendingFollowUps', 'dailyNote', 'expenseCategories', 'teamUsers',
            'currentMode', 'mode', 'paymentMethodOptions', 'legacyFinancialSummary', 'todayViewModel',
            'todayProjection', 'workProjection'
        ));
    }

    public function work(Request $request)
    {
        $request->merge(['mode' => 'work']);

        return $this->index();
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
        return app()->call([app(ClientController::class), 'show'], ['id' => $client]);
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
            'attendees.*' => [
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->where(fn ($inner) => $inner->whereNull('role')->orWhereIn('role', User::activeInternalRoles()))),
            ],
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
        Gate::authorize(FinancialPermissions::RECORD_PAYMENT);

        abort(410, 'Legacy payment writes are deprecated. Use the V2 Collections payment workflow.');
    }

    public function storeExpense(Request $request)
    {
        Gate::authorize(FinancialPermissions::MANAGE_EXPENSES);

        abort(410, 'Legacy dashboard expense writes are deprecated. Use the V2 Operating Expenses workflow.');
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

    private function legacyFinancialSummary(): array
    {
        return [
            'status' => 'deprecated',
            'message' => 'Legacy dashboard financial formulas are retired. Use /finance, /executive, /saas-metrics, and /accounting for authoritative V1 values.',
            'authoritative_routes' => [
                'finance' => route('finance.index'),
                'executive' => route('executive.index'),
                'saas' => route('saas-metrics.index'),
                'accounting' => route('accounting.index'),
            ],
        ];
    }
}
