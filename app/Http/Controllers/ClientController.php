<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Installation;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Partner;
use App\Models\User;
use App\Services\ReceivableService;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use App\Support\FinancialPermissions;
use App\Support\PaymentMethods;
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

        $queryBuilder = Client::query()->with('primaryContact');

        if (auth()->user()->isPartner()) {
            $queryBuilder->where('partner_id', auth()->user()->partner_id);
        }

        $search = trim((string) $request->get('q'));
        $query = $search;
        $status = $request->get('status', 'all');

        if ($search !== '') {
            $queryBuilder->where(function ($inner) use ($search) {
                $inner->where('business_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('business_phone', 'like', "%{$search}%")
                    ->orWhere('city_area', 'like', "%{$search}%");
            });
        }

        if ($status !== 'all') {
            $queryBuilder->where(function ($inner) use ($status) {
                if ($status === 'archived') {
                    $inner->where('stage', ClientLifecycle::CLOSED)->orWhere('status', 'archived');
                    return;
                }

                $inner->where('stage', $status);
                if (in_array($status, ['prospect', 'subscriber'], true)) {
                    $inner->orWhere('status', $status);
                }
            });
        }

        $clients = $queryBuilder->orderByDesc('updated_at')->paginate(10)->withQueryString();

        $lifecycleStages = ClientLifecycle::STAGES;
        $lifecycleLabels = ClientLifecycle::labels();

        return view('clients.index', compact('clients', 'search', 'query', 'status', 'lifecycleStages', 'lifecycleLabels'));
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
            'business_phone' => 'nullable|string|max:50',
            'city_area' => 'required|string|max:120',
            'city' => 'nullable|string|max:120',
            'area' => 'nullable|string|max:120',
            'business_category' => 'required|string|max:120',
            'business_type' => 'nullable|string|max:120',
            'lead_source' => 'required|string|max:80',
            'source_reference' => 'nullable|string|max:255',
            'number_of_branches' => 'nullable|integer|min:1|max:999',
            'instagram' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'maps_url' => 'nullable|string|max:500',
            'location_text' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:120',
            'notes' => 'nullable|string',
            'partner_id' => 'nullable|exists:partners,id',
        ]);

        $data['business_phone'] = $data['business_phone'] ?? $data['phone'];
        $data['business_type'] = $data['business_type'] ?? $data['business_category'];
        $data['city'] = $data['city'] ?? $data['city_area'];
        $data['number_of_branches'] = $data['number_of_branches'] ?? 1;
        $data['stage'] = ClientLifecycle::PROSPECT;
        $data['status'] = 'prospect';

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

        if (!empty($data['contact_person'])) {
            $client->contacts()->create([
                'name' => $data['contact_person'],
                'role' => null,
                'primary_phone' => null,
                'is_primary' => true,
            ]);
        }

        return redirect()->route('clients.show', $client->id)->with('success', 'تم إنشاء العميل بنجاح.');
    }

    public function show(int $id, ReceivableService $receivableService): View
    {
        $clientModel = Client::findOrFail($id);
        Gate::authorize('view', $clientModel);

        $client = $clientModel;
        $client->load(['contacts', 'primaryContact']);
        $timeline = DB::table('activity_logs')->where('client_id', $client->id)->orderByDesc('created_at')->get();
        $appointments = Appointment::where('client_id', $client->id)->with('users')->orderByDesc('appointment_date')->orderByDesc('appointment_time')->get();
        $payments = \App\Models\Payment::with(['reversal', 'allocations.invoice', 'allocations.reversal'])
            ->where('client_id', $client->id)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();
        $creditNotes = \App\Models\CreditNote::with(['originalInvoice', 'lines', 'applications.invoice', 'applications.reversal', 'refunds'])
            ->where('client_id', $client->id)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();
        $refunds = \App\Models\Refund::with(['payment', 'creditNote'])
            ->where('client_id', $client->id)
            ->orderByDesc('refunded_at')
            ->orderByDesc('id')
            ->get();
        $offers = DB::table('commercial_offers')->where('client_id', $client->id)->orderByDesc('offer_date')->get();
        $subscriptions = DB::table('subscriptions')->where('client_id', $client->id)->orderByDesc('start_date')->get();
        $invoices = Invoice::with('lines')
            ->where('client_id', $client->id)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();
        $invoiceReceivables = $receivableService->invoiceProjections($invoices);
        $paymentReceivables = $receivableService->paymentProjections($payments);
        $creditNoteReceivables = $receivableService->creditNoteProjections($creditNotes);
        $availableCustomerCredits = $receivableService->availableCustomerCredits(['client_id' => $client->id]);
        $receivableSummary = $receivableService->clientSummary($client);
        $followUps = DB::table('follow_ups')->where('client_id', $client->id)->orderByDesc('next_follow_up_date')->get();
        $outcomes = DB::table('meeting_outcomes')->where('client_id', $client->id)->orderByDesc('created_at')->get();
        $schedules = DB::table('payment_schedules')
            ->join('subscriptions', 'subscriptions.id', '=', 'payment_schedules.subscription_id')
            ->where('subscriptions.client_id', $client->id)
            ->select('payment_schedules.*')
            ->orderBy('payment_schedules.due_date')
            ->get();
        $teamUsers = User::where('role', 'admin')->get();
        $catalogServices = \App\Models\Service::active()->get();
        $contracts = \App\Models\Contract::where('client_id', $client->id)->orderByDesc('id')->get();
        $contactAttempts = \App\Models\ContactAttempt::where('client_id', $client->id)->orderByDesc('created_at')->get();
        $installations = Installation::with(['items', 'installedBy', 'appointment'])
            ->where('client_id', $client->id)
            ->orderByDesc('installed_at')
            ->get();
        $installationAppointments = $appointments
            ->where('appointment_type', AppointmentTypes::INSTALLATION)
            ->values();
        $activeInstallationAppointments = $installationAppointments
            ->whereIn('status', AppointmentTypes::activeStatuses())
            ->values();
        $appointmentTypeLabels = AppointmentTypes::labels();
        $lifecycleStages = ClientLifecycle::STAGES;
        $lifecycleLabels = ClientLifecycle::labels();
        $contactOutcomes = ClientLifecycle::CONTACT_OUTCOMES;
        $paymentMethodOptions = PaymentMethods::labels();
        $activeFinancialAccounts = \App\Models\FinancialAccount::where('is_active', true)
            ->whereNull('archived_at')
            ->orderBy('name_ar')
            ->get();
        $sellablePlans = Plan::sellable()
            ->with(['services', 'activePrices' => fn ($query) => $query->effective(now())->orderBy('billing_interval')])
            ->orderBy('code')
            ->get();

        return view('clients.show', compact('client', 'timeline', 'appointments', 'payments', 'creditNotes', 'refunds', 'offers', 'subscriptions', 'invoices', 'invoiceReceivables', 'paymentReceivables', 'creditNoteReceivables', 'availableCustomerCredits', 'receivableSummary', 'followUps', 'outcomes', 'schedules', 'teamUsers', 'catalogServices', 'contracts', 'contactAttempts', 'installations', 'installationAppointments', 'activeInstallationAppointments', 'appointmentTypeLabels', 'lifecycleStages', 'lifecycleLabels', 'contactOutcomes', 'paymentMethodOptions', 'activeFinancialAccounts', 'sellablePlans'));
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
            'business_phone' => 'nullable|string|max:50',
            'city_area' => 'required|string|max:120',
            'city' => 'nullable|string|max:120',
            'area' => 'nullable|string|max:120',
            'business_category' => 'required|string|max:120',
            'business_type' => 'nullable|string|max:120',
            'lead_source' => 'required|string|max:80',
            'source_reference' => 'nullable|string|max:255',
            'number_of_branches' => 'nullable|integer|min:1|max:999',
            'instagram' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'maps_url' => 'nullable|string|max:500',
            'location_text' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:120',
            'notes' => 'nullable|string',
            'partner_id' => 'nullable|exists:partners,id',
        ]);

        $data['business_phone'] = $data['business_phone'] ?? $data['phone'];
        $data['business_type'] = $data['business_type'] ?? $data['business_category'];
        $data['city'] = $data['city'] ?? $data['city_area'];
        $data['number_of_branches'] = $data['number_of_branches'] ?? 1;

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

        DB::transaction(function () use ($client) {
            $client->update([
                'stage' => ClientLifecycle::CLOSED,
                'status' => 'archived',
                'closed_at' => now(),
                'closed_reason' => 'إغلاق تشغيلي بدلاً من الحذف الدائم',
            ]);

            DB::table('activity_logs')->insert([
                'client_id' => $client->id,
                'user_id' => auth()->id(),
                'type' => 'client_closed',
                'description' => 'تم إغلاق ملف العميل مع الحفاظ على السجل بدلاً من الحذف الدائم',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('clients.index')->with('success', 'تم إغلاق ملف العميل مع الحفاظ على سجله.');
    }

    public function convert(Request $request, int $client): RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING);

        $clientModel = Client::findOrFail($client);
        Gate::authorize('update', $clientModel);

        $data = $request->validate([
            'billing_type' => 'required|in:monthly,annual,installment',
            'total_price' => 'nullable|numeric|min:0.01',
            'setup_fee' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'installments_count' => 'nullable|integer|min:2|max:12',
            'monthly_due_day' => 'nullable|integer|in:1,5,15,30',
            'services' => 'nullable|array',
            'services.*' => 'exists:services,id',
        ]);

        $pricingService = app(\App\Services\SubscriptionPricingService::class);
        $scheduleService = app(\App\Services\PaymentScheduleService::class);

        DB::transaction(function () use ($data, $client, $pricingService, $scheduleService) {
            $selectedServices = [];
            $servicesTotal = 0.000;

            if (!empty($data['services'])) {
                $selectedServices = \App\Models\Service::whereIn('id', $data['services'])->get();
                $servicesTotal = (float) $selectedServices->sum('default_price');
            }

            $baseSubtotal = (!empty($data['total_price']) && (float)$data['total_price'] > 0)
                ? (float) $data['total_price']
                : ($servicesTotal > 0 ? $servicesTotal : 100.000);

            $pricing = $pricingService->calculate(
                $baseSubtotal,
                $data['billing_type'],
                $data['setup_fee'] ?? 0.000,
                null, // Use global annual discount setting
                null, // Use global sales tax setting
                isset($data['installments_count']) ? (int) $data['installments_count'] : null,
                isset($data['monthly_due_day']) ? (int) $data['monthly_due_day'] : null
            );

            $subscription = \App\Models\Subscription::create([
                'client_id' => $client,
                'user_id' => auth()->id(),
                'billing_type' => $data['billing_type'],
                'total_price' => $pricing['grand_total'],
                'setup_fee' => $pricing['setup_fee'],
                'base_subtotal' => $pricing['base_subtotal'],
                'annual_discount_percentage' => $pricing['annual_discount_percentage'],
                'discount_amount' => $pricing['discount_amount'],
                'tax_percentage' => $pricing['tax_percentage'],
                'tax_amount' => $pricing['tax_amount'],
                'grand_total' => $pricing['grand_total'],
                'monthly_due_day' => $pricing['monthly_due_day'],
                'start_date' => $data['start_date'],
                'renewal_date' => Carbon::parse($data['start_date'])->addYear()->toDateString(),
                'installments_count' => $pricing['installments_count'],
                'status' => 'active',
                'version' => 1,
            ]);

            // Snapshot selected services
            foreach ($selectedServices as $svc) {
                DB::table('subscription_service')->insert([
                    'subscription_id' => $subscription->id,
                    'service_id' => $svc->id,
                    'service_key' => $svc->key,
                    'service_name_ar' => $svc->name_ar,
                    'service_name_en' => $svc->name_en,
                    'price_contribution' => $svc->default_price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Generate deterministic payment schedules
            $scheduleService->generateSchedules($subscription, $pricing, $data['start_date']);

            DB::table('clients')->where('id', $client)->update(['status' => 'subscriber', 'stage' => ClientLifecycle::SUBSCRIBER, 'closed_at' => null, 'closed_reason' => null, 'updated_at' => now()]);
            DB::table('activity_logs')->insert([
                'client_id' => $client,
                'user_id' => auth()->id(),
                'type' => 'converted',
                'description' => 'تم تحويل العميل إلى مشترك وإنشاء جدول الدفعات وفق محرك التسعير المعتمد',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'تم التحويل وإنشاء جدول الدفعات بنجاح.');
    }
}
