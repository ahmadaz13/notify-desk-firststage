<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PartnerDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        abort_unless($user && ($user->isPartner() || $user->isAdmin()), 403, 'غير مصرح لك بالوصول إلى لوحة الشريك.');

        $partner = $user->partner;

        // If an administrator accesses the partner dashboard, support viewing a partner
        if (!$partner && $user->isAdmin()) {
            $partnerId = $request->get('partner_id');
            $partner = $partnerId ? Partner::find($partnerId) : Partner::first();
        }

        abort_unless($partner, 404, 'لم يتم العثور على بيانات الشريك.');

        $today = Carbon::today();
        $delegateUrl = url('/p/' . $partner->public_uuid . '/client/create');

        // Scoped Partner Financial Metrics
        $totalClientPayments = (float) $partner->total_client_payments;
        $deductionPercentage = $partner->deduction_percentage !== null ? (float) $partner->deduction_percentage : 20.0;
        $netOperatingRevenue = (float) ($totalClientPayments * (1 - ($deductionPercentage / 100)));
        $earnedShare = $partner->earned_share;

        // Scoped Appointments for this partner's clients today
        $appointments = DB::table('appointments')
            ->join('clients', 'clients.id', '=', 'appointments.client_id')
            ->where('clients.partner_id', $partner->id)
            ->whereDate('appointments.appointment_date', $today)
            ->orderBy('appointments.appointment_time')
            ->select('appointments.*', 'clients.business_name', 'clients.phone')
            ->get();

        // Scoped Follow-ups for this partner's clients
        $followUps = DB::table('follow_ups')
            ->join('clients', 'clients.id', '=', 'follow_ups.client_id')
            ->where('clients.partner_id', $partner->id)
            ->whereDate('follow_ups.next_follow_up_date', '>=', $today)
            ->orderBy('follow_ups.next_follow_up_date')
            ->select('follow_ups.*', 'clients.business_name', 'clients.phone')
            ->limit(5)
            ->get();

        // Scoped Collections today for this partner's clients
        $todayCollections = (float) DB::table('payments')
            ->join('clients', 'clients.id', '=', 'payments.client_id')
            ->where('clients.partner_id', $partner->id)
            ->whereDate('payments.paid_at', $today)
            ->sum('payments.amount');

        // Scoped Clients list with search and pagination
        $query = trim((string) $request->get('q'));
        $status = $request->get('status', 'all');

        $clients = Client::where('partner_id', $partner->id)
            ->when($query, function ($q) use ($query) {
                $q->where(function ($inner) use ($query) {
                    $inner->where('business_name', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('city_area', 'like', "%{$query}%");
                });
            })
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('updated_at')
            ->paginate(10)
            ->withQueryString();

        // Unread Notifications count for current user
        $unreadNotificationsCount = DB::table('notifications')
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return view('partners.dashboard', compact(
            'partner',
            'delegateUrl',
            'totalClientPayments',
            'deductionPercentage',
            'netOperatingRevenue',
            'earnedShare',
            'appointments',
            'followUps',
            'todayCollections',
            'clients',
            'query',
            'status',
            'unreadNotificationsCount'
        ));
    }
}
