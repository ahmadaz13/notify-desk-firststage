<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
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

        return view('dashboard', compact(
            'clients', 'prospects', 'subscribers', 'appointments', 'nextAppointment',
            'collected', 'expenses', 'overdue', 'reminders',
            'todayCollections', 'monthIncome', 'monthExpenses', 'netCashResult', 'overdueCollections',
            'unreadNotifications'
        ));
    }

    public function clients(Request $request)
    {
        $query = trim((string) $request->get('q'));
        $status = $request->get('status', 'all');
        $clients = DB::table('clients')->when($query, fn ($q) => $q->where(function ($inner) use ($query) { $inner->where('business_name', 'like', "%{$query}%")->orWhere('phone', 'like', "%{$query}%")->orWhere('city_area', 'like', "%{$query}%"); }))->when($status !== 'all', fn ($q) => $q->where('status', $status))->orderByDesc('updated_at')->paginate(10)->withQueryString();
        return view('clients.index', compact('clients', 'query', 'status'));
    }

    public function showClient(int $client)
    {
        $client = DB::table('clients')->where('id', $client)->first();
        abort_unless($client, 404);
        $timeline = DB::table('activity_logs')->where('client_id', $client->id)->orderByDesc('created_at')->get();
        $appointments = DB::table('appointments')->where('client_id', $client->id)->orderByDesc('appointment_date')->get();
        $payments = DB::table('payments')->where('client_id', $client->id)->orderByDesc('paid_at')->get();
        $offers = DB::table('commercial_offers')->where('client_id', $client->id)->orderByDesc('offer_date')->get();
        $subscriptions = DB::table('subscriptions')->where('client_id', $client->id)->orderByDesc('start_date')->get();
        $followUps = DB::table('follow_ups')->where('client_id', $client->id)->orderByDesc('next_follow_up_date')->get();
        $outcomes = DB::table('meeting_outcomes')->where('client_id', $client->id)->orderByDesc('created_at')->get();
        $schedules = DB::table('payment_schedules')
            ->join('subscriptions', 'subscriptions.id', '=', 'payment_schedules.subscription_id')
            ->where('subscriptions.client_id', $client->id)
            ->select('payment_schedules.*')
            ->orderBy('payment_schedules.due_date')
            ->get();

        return view('clients.show', compact('client', 'timeline', 'appointments', 'payments', 'offers', 'subscriptions', 'followUps', 'outcomes', 'schedules'));
    }

    public function storeClient(Request $request)
    {
        $data = $request->validate(['business_name' => 'required|string|max:255', 'phone' => 'required|string|max:50', 'city_area' => 'required|string|max:120', 'business_category' => 'required|string|max:120', 'lead_source' => 'required|string|max:80', 'contact_person' => 'nullable|string|max:120', 'notes' => 'nullable|string']);
        $data['primary_owner_id'] = auth()->id();
        $data['created_at'] = now(); $data['updated_at'] = now();
        $id = DB::transaction(function () use ($data) { $id = DB::table('clients')->insertGetId($data); $this->log($id, 'client_created', 'تم إنشاء عميل جديد'); return $id; });
        return redirect()->route('clients.show', $id)->with('success', 'تم إنشاء العميل بنجاح.');
    }

    public function storeAppointment(Request $request)
    {
        $data = $request->validate(['client_id' => 'required|exists:clients,id', 'appointment_date' => 'required|date', 'appointment_time' => 'required', 'appointment_type' => 'required|string', 'location' => 'nullable|string|max:255', 'notes' => 'nullable|string']);
        $data['created_at'] = now(); $data['updated_at'] = now();
        $id = DB::transaction(function () use ($data) { $id = DB::table('appointments')->insertGetId($data); $this->log($data['client_id'], 'appointment_created', 'تم جدولة موعد جديد'); return $id; });
        return back()->with('success', 'تم جدولة الموعد.');
    }

    public function updateAppointment(Request $request, int $appointment)
    {
        $data = $request->validate(['status' => 'required|in:scheduled,confirmed,rescheduled,cancelled,no_show,completed']);
        $item = DB::table('appointments')->where('id', $appointment)->first(); abort_unless($item, 404);
        DB::transaction(function () use ($data, $item) { DB::table('appointments')->where('id', $item->id)->update(['status' => $data['status'], 'updated_at' => now()]); $this->log($item->client_id, 'appointment_'.$data['status'], 'تم تحديث حالة الموعد إلى '.$data['status']); });
        return back()->with('success', 'تم تحديث حالة الموعد.');
    }

    public function storePayment(Request $request)
    {
        $data = $request->validate(['client_id' => 'required|exists:clients,id', 'amount' => 'required|numeric|min:0.01', 'payment_method' => 'required|string', 'paid_at' => 'required|date']);
        DB::transaction(function () use ($data) { $subscription = DB::table('subscriptions')->where('client_id', $data['client_id'])->whereIn('status', ['active', 'payment_due'])->latest('id')->first(); if (!$subscription) { $subscriptionId = DB::table('subscriptions')->insertGetId(['client_id' => $data['client_id'], 'user_id' => auth()->id(), 'billing_type' => 'monthly', 'total_price' => $data['amount'], 'start_date' => now()->toDateString(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]); } else { $subscriptionId = $subscription->id; } DB::table('payments')->insert(array_merge($data, ['subscription_id' => $subscriptionId, 'recorded_by' => auth()->id(), 'paid_at' => Carbon::parse($data['paid_at']), 'created_at' => now(), 'updated_at' => now()])); $this->log($data['client_id'], 'payment_received', 'تم تسجيل دفعة بقيمة '.$data['amount'].' د.أ'); DB::table('clients')->where('id', $data['client_id'])->update(['status' => 'subscriber', 'updated_at' => now()]); });
        return back()->with('success', 'تم تسجيل الدفعة.');
    }

    public function storeExpense(Request $request)
    {
        $data = $request->validate(['amount' => 'required|numeric|min:0.01', 'category' => 'required|string|max:120', 'date' => 'required|date', 'notes' => 'nullable|string']);
        DB::table('expenses')->insert(array_merge($data, ['paid_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]));
        return back()->with('success', 'تم تسجيل المصروف.');
    }

    public function convert(Request $request, int $client)
    {
        $data = $request->validate(['billing_type' => 'required|in:monthly,annual,installment', 'total_price' => 'required|numeric|min:0.01', 'start_date' => 'required|date', 'installments_count' => 'nullable|integer|min:2|max:12']);
        DB::transaction(function () use ($data, $client) { $count = $data['billing_type'] === 'annual' ? 1 : ($data['billing_type'] === 'installment' ? (int) ($data['installments_count'] ?: 2) : 12); $subscriptionId = DB::table('subscriptions')->insertGetId(array_merge($data, ['client_id' => $client, 'user_id' => auth()->id(), 'status' => 'active', 'renewal_date' => Carbon::parse($data['start_date'])->addYear()->toDateString(), 'created_at' => now(), 'updated_at' => now()])); $per = round(((float) $data['total_price']) / $count, 2); for ($i = 0; $i < $count; $i++) { DB::table('payment_schedules')->insert(['subscription_id' => $subscriptionId, 'amount_due' => $i === $count - 1 ? ((float) $data['total_price']) - ($per * ($count - 1)) : $per, 'due_date' => Carbon::parse($data['start_date'])->addMonths($data['billing_type'] === 'annual' ? 0 : $i)->toDateString(), 'status' => $i === 0 ? 'due' : 'upcoming', 'created_at' => now(), 'updated_at' => now()]); } DB::table('clients')->where('id', $client)->update(['status' => 'subscriber', 'updated_at' => now()]); $this->log($client, 'converted', 'تم تحويل العميل إلى مشترك وإنشاء جدول الدفعات'); });
        return back()->with('success', 'تم التحويل وإنشاء جدول الدفعات.');
    }

    private function log(int $clientId, string $type, string $description): void
    {
        DB::table('activity_logs')->insert(['client_id' => $clientId, 'user_id' => auth()->id(), 'type' => $type, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
    }
}
