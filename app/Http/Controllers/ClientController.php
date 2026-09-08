<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Client::class);

        $query = Client::query();

        if (auth()->user()->isPartner()) {
            $query->where('partner_id', auth()->user()->partner_id);
        }

        $search = trim((string) $request->get('q'));
        $status = $request->get('status', 'all');

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('business_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('city_area', 'like', "%{$search}%");
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $clients = $query->orderByDesc('updated_at')->paginate(10)->withQueryString();

        return view('clients.index', compact('clients', 'search', 'status'));
    }

    public function create(): View
    {
        Gate::authorize('create', Client::class);

        $partners = Partner::orderBy('company_name')->get();

        return view('clients.create', compact('partners'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Client::class);

        $data = $request->validate([
            'business_name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'city_area' => 'required|string|max:120',
            'business_category' => 'required|string|max:120',
            'lead_source' => 'required|string|max:80',
            'contact_person' => 'nullable|string|max:120',
            'notes' => 'nullable|string',
            'partner_id' => 'nullable|exists:partners,id',
        ]);

        if (auth()->user()->isPartner()) {
            $data['partner_id'] = auth()->user()->partner_id;
        } elseif (auth()->user()->isAdmin()) {
            $data['partner_id'] = $request->filled('partner_id') ? (int) $request->partner_id : null;
        }

        $data['primary_owner_id'] = auth()->id();

        $client = Client::create($data);

        DB::table('activity_logs')->insert([
            'client_id' => $client->id,
            'user_id' => auth()->id(),
            'type' => 'client_created',
            'description' => 'تم إنشاء عميل جديد',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('clients.show', $client->id)->with('success', 'تم إنشاء العميل بنجاح.');
    }

    public function show(int $id): View
    {
        $clientModel = Client::findOrFail($id);
        Gate::authorize('view', $clientModel);

        $client = $clientModel;
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

    public function edit(int $id): View
    {
        $client = Client::findOrFail($id);
        Gate::authorize('update', $client);

        $partners = Partner::orderBy('company_name')->get();

        return view('clients.edit', compact('client', 'partners'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $client = Client::findOrFail($id);
        Gate::authorize('update', $client);

        $data = $request->validate([
            'business_name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'city_area' => 'required|string|max:120',
            'business_category' => 'required|string|max:120',
            'lead_source' => 'required|string|max:80',
            'contact_person' => 'nullable|string|max:120',
            'notes' => 'nullable|string',
            'partner_id' => 'nullable|exists:partners,id',
        ]);

        if (auth()->user()->isPartner()) {
            $data['partner_id'] = auth()->user()->partner_id;
        } elseif (auth()->user()->isAdmin()) {
            $data['partner_id'] = $request->filled('partner_id') ? (int) $request->partner_id : null;
        }

        $client->update($data);

        DB::table('activity_logs')->insert([
            'client_id' => $client->id,
            'user_id' => auth()->id(),
            'type' => 'client_updated',
            'description' => 'تم تحديث بيانات العميل',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('clients.show', $client->id)->with('success', 'تم تحديث بيانات العميل بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $client = Client::findOrFail($id);
        Gate::authorize('delete', $client);

        $client->delete();

        return redirect()->route('clients.index')->with('success', 'تم حذف العميل بنجاح.');
    }
}
