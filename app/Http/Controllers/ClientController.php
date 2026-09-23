<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\CustomProject;
use App\Models\Installation;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ClientCredentialService;
use App\Services\ClientPrimaryContactService;
use App\Services\ReceivableService;
use App\Services\ReferenceDataService;
use App\Services\ClientOperationalWorkflowService;
use App\Services\OperationalQueueService;
use App\Services\PaymentScheduleService;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use App\Support\Permissions;
use App\Support\PaymentMethods;
use App\ViewModels\ClientListViewModel;
use App\ViewModels\ClientWorkspaceViewModel;
use App\ViewModels\ContactOutcomeViewModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    /** Category select value for "Other" -> free text stored as typed (§15.1). */
    public const OTHER_CATEGORY = '__other__';

    public function index(Request $request, ?OperationalQueueService $operationalQueues = null): View
    {
        Gate::authorize('viewAny', Client::class);
        $operationalQueues ??= app(OperationalQueueService::class);

        $queryBuilder = Client::query()
            ->with(['contacts', 'primaryContact', 'primaryOwner'])
            ->select('clients.*')
            ->selectSub(
                DB::table('activity_logs')
                    ->selectRaw('MAX(activity_logs.created_at)')
                    ->whereColumn('activity_logs.client_id', 'clients.id'),
                'last_activity_at'
            );

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
        $clientListViewModel = ClientListViewModel::make(
            $clients,
            ['q' => $query, 'status' => $status],
            $lifecycleStages,
            $lifecycleLabels,
            $operationalQueues
        );

        return view('clients.index', compact('clients', 'search', 'query', 'status', 'lifecycleStages', 'lifecycleLabels', 'clientListViewModel'));
    }

    public function create(): View
    {
        Gate::authorize('create', Client::class);

        return view('clients.create', $this->formOptions());
    }

    public function store(Request $request, ClientPrimaryContactService $contacts): RedirectResponse
    {
        Gate::authorize('create', Client::class);

        $data = $this->validateClient($request);

        $clientData = $this->clientAttributes($data);
        // Commission is only set later by Owner-level users on edit (P2 field-level rule).
        $clientData['referral_commission_bps'] = null;
        $clientData['business_type'] = filled($data['business_type'] ?? null) ? $data['business_type'] : $clientData['business_category'];
        $clientData['number_of_branches'] = $data['number_of_branches'] ?? 1;
        $clientData['stage'] = ClientLifecycle::PROSPECT;
        $clientData['status'] = 'prospect';
        $clientData['primary_owner_id'] = auth()->id();

        $client = DB::transaction(function () use ($clientData, $data, $contacts) {
            $client = new Client($clientData);
            $contacts->sync($client, $data['primary_phone_type'], $data['phone'], $data['business_phone'] ?? null, $this->contactInput($data));

            DB::table('activity_logs')->insert([
                'client_id' => $client->id,
                'user_id' => auth()->id(),
                'type' => 'client_created',
                'description' => 'تم إنشاء عميل جديد',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $client;
        });

        return redirect()->route('clients.show', $client->id)->with('success', __('notify.clients.created_successfully'));
    }

    public function show(
        int $id,
        ReceivableService $receivableService,
        OperationalQueueService $operationalQueues,
        PaymentScheduleService $paymentScheduleService
    ): View
    {
        $clientModel = Client::findOrFail($id);
        Gate::authorize('view', $clientModel);

        $client = $clientModel;
        $customProjects = CustomProject::where('client_id', $client->id)->orderByDesc('created_at')->get();
        $client->load(['contacts', 'primaryContact', 'systems']);
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
        $subscriptions = Subscription::with([
            'plan.product',
            'plan.services',
            'billingPeriods.invoice',
            'lifecycleEvents',
            'pendingPlanPrice.plan',
            'contracts',
            'contract',
            'systems',
            'invoices',
        ])
            ->where('client_id', $client->id)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
        $installmentScheduleProjections = $subscriptions
            ->mapWithKeys(fn (Subscription $subscription) => [
                $subscription->id => $paymentScheduleService->annualInstallmentProjection($subscription),
            ])
            ->filter();
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
            ->whereNull('payment_schedules.schedule_engine_version')
            ->select('payment_schedules.*')
            ->orderBy('payment_schedules.due_date')
            ->get();
        $teamUsers = User::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('role')
                    ->orWhereIn('role', User::activeInternalRoles());
            })
            ->orderBy('name')
            ->get();
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
        $paymentMethodOptions = PaymentMethods::v1Labels();
        $pendingPaymentReceipts = \App\Models\PaymentReceiptConfirmation::with('submitter:id,name')
            ->where('client_id', $client->id)
            ->pending()
            ->orderByDesc('received_at')
            ->get();
        $activeFinancialAccounts = \App\Models\FinancialAccount::where('is_active', true)
            ->whereNull('archived_at')
            ->orderBy('name_ar')
            ->get();
        $credentialService = app(ClientCredentialService::class);
        $credentialSystems = $credentialService->applicableSystems($client);
        $credentialPanel = [
            'systems' => $credentialSystems,
            // Hidden attributes: secret/note never reach the view as values.
            'credentials' => $client->credentials()->get()->keyBy('product_id'),
            'addable' => $credentialService->addableSystems($client)->whereNotIn('id', $credentialSystems->pluck('id')),
            'recipient' => $credentialService->defaultRecipient($client),
            'can_manage' => Permissions::allows(auth()->user(), Permissions::MANAGE_CLIENT_CREDENTIALS),
            'can_reveal' => Permissions::allows(auth()->user(), Permissions::REVEAL_CLIENT_CREDENTIALS),
        ];
        $sellableProducts = Product::sellable()->orderBy('name_ar')->get();
        $sellablePlans = collect();
        $accountingTrace = collect();
        if (Gate::allows(Permissions::VIEW_ACCOUNTING)) {
            $sourcePairs = [
                Invoice::class => $invoices->pluck('id')->all(),
                \App\Models\PaymentAllocation::class => $payments->flatMap->allocations->pluck('id')->all(),
                \App\Models\PaymentAllocationReversal::class => $payments->flatMap->allocations->pluck('reversal.id')->filter()->all(),
                \App\Models\CreditNote::class => $creditNotes->pluck('id')->all(),
                \App\Models\CreditNoteApplication::class => $creditNotes->flatMap->applications->pluck('id')->all(),
                \App\Models\CreditNoteApplicationReversal::class => $creditNotes->flatMap->applications->pluck('reversal.id')->filter()->all(),
                \App\Models\Refund::class => $refunds->pluck('id')->all(),
            ];
            $accountingTrace = collect($sourcePairs)
                ->flatMap(function (array $ids, string $type) {
                    if ($ids === []) {
                        return collect();
                    }

                    return JournalEntry::query()
                        ->where('source_type', $type)
                        ->whereIn('source_id', $ids)
                        ->orderBy('entry_date')
                        ->orderBy('id')
                        ->get()
                        ->map(fn (JournalEntry $entry) => [
                            'entry' => $entry,
                            'source_label' => class_basename($type).' #'.$entry->source_id,
                        ]);
                })
                ->values();
        }

        $clientWorkspaceViewModel = ClientWorkspaceViewModel::make(
            $client,
            $timeline,
            $appointments,
            $followUps,
            $outcomes,
            $payments,
            $subscriptions,
            $contracts,
            $offers,
            $contactAttempts,
            $installations,
            $lifecycleLabels,
            $operationalQueues,
            $receivableSummary,
            $invoiceReceivables,
            auth()->user()
        );
        $contactOutcomeViewModel = ContactOutcomeViewModel::make($appointmentTypeLabels);

        return view('clients.show', compact('customProjects', 'client', 'timeline', 'appointments', 'payments', 'creditNotes', 'refunds', 'offers', 'subscriptions', 'installmentScheduleProjections', 'invoices', 'invoiceReceivables', 'paymentReceivables', 'creditNoteReceivables', 'availableCustomerCredits', 'receivableSummary', 'followUps', 'outcomes', 'schedules', 'teamUsers', 'catalogServices', 'contracts', 'contactAttempts', 'installations', 'installationAppointments', 'activeInstallationAppointments', 'appointmentTypeLabels', 'lifecycleStages', 'lifecycleLabels', 'contactOutcomes', 'paymentMethodOptions', 'pendingPaymentReceipts', 'activeFinancialAccounts', 'sellableProducts', 'sellablePlans', 'accountingTrace', 'clientWorkspaceViewModel', 'contactOutcomeViewModel', 'credentialPanel', 'credentialService'));
    }

    public function edit(int $id): View
    {
        $client = Client::findOrFail($id);
        Gate::authorize('update', $client);

        $client->load(['contacts', 'primaryContact']);

        return view('clients.edit', ['client' => $client] + $this->formOptions($client));
    }

    public function update(Request $request, int $id, ClientPrimaryContactService $contacts): RedirectResponse
    {
        $client = Client::findOrFail($id);
        Gate::authorize('update', $client);

        $data = $this->validateClient($request);

        $clientData = $this->clientAttributes($data);
        $clientData['referral_commission_bps'] = Permissions::allows($request->user(), Permissions::EDIT_REFERRAL_COMMISSION)
            ? $this->percentageToBps($data['referral_commission_percentage'] ?? null)
            : $client->referral_commission_bps;
        $clientData['business_type'] = filled($data['business_type'] ?? null)
            ? $data['business_type']
            : ($client->business_type && $client->business_type !== $client->business_category
                ? $client->business_type
                : $clientData['business_category']);
        $clientData['number_of_branches'] = $data['number_of_branches'] ?? 1;

        DB::transaction(function () use ($client, $clientData, $data, $contacts) {
            $client->fill($clientData);
            $contacts->sync($client, $data['primary_phone_type'], $data['phone'], $data['business_phone'] ?? null, $this->contactInput($data));

            DB::table('activity_logs')->insert([
                'client_id' => $client->id,
                'user_id' => auth()->id(),
                'type' => 'client_updated',
                'description' => 'تم تحديث بيانات العميل',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('clients.show', $client->id)->with('success', __('notify.clients.updated_successfully'));
    }

    public function destroy(int $id, ClientOperationalWorkflowService $workflow): RedirectResponse
    {
        $client = Client::findOrFail($id);
        Gate::authorize('delete', $client);

        $workflow->closeClient($client, auth()->user(), 'other', 'إغلاق تشغيلي بدلاً من الحذف الدائم');

        return redirect()->route('clients.index')->with('success', 'تم إغلاق ملف العميل مع الحفاظ على سجله.');
    }

    /**
     * Shared create/edit validation (§28.1). Reference lists stay strings: unknown historical values and
     * free-text categories/cities are accepted (§15.1, D-20).
     */
    private function validateClient(Request $request): array
    {
        return $request->validate([
            'business_name' => 'required|string|max:255',
            'business_category' => 'required|string|max:120',
            'business_category_other' => ['nullable', 'string', 'max:120', 'required_if:business_category,'.self::OTHER_CATEGORY],
            'phone' => 'required|string|max:50',
            'primary_phone_type' => ['required', Rule::in(Client::PRIMARY_PHONE_TYPES)],
            'business_phone' => 'nullable|string|max:50',
            'city_area' => 'required|string|max:120',
            'city' => 'nullable|string|max:120',
            'area' => 'nullable|string|max:120',
            'business_type' => 'nullable|string|max:120',
            'lead_source' => 'required|string|max:80',
            'source_reference' => 'nullable|string|max:255',
            'number_of_branches' => 'nullable|integer|min:1|max:999',
            'instagram' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'maps_url' => 'nullable|string|max:500',
            'location_text' => 'nullable|string|max:255',
            'contact_name' => 'nullable|string|max:120',
            'contact_role' => 'nullable|string|max:120',
            'contact_phone' => 'nullable|string|max:50',
            'contact_whatsapp' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
            'referred_by_name' => 'nullable|string|max:255',
            'referral_commission_percentage' => 'nullable|numeric|min:0|max:100',
            'referral_note' => 'nullable|string|max:1000',
        ], $this->clientValidationMessages());
    }

    /**
     * Client columns only; phone ownership and contact details are applied by ClientPrimaryContactService.
     */
    private function clientAttributes(array $data): array
    {
        $attributes = collect($data)->except([
            'phone', 'primary_phone_type', 'business_phone', 'business_category_other',
            'contact_name', 'contact_role', 'contact_phone', 'contact_whatsapp', 'contact_email',
            'referral_commission_percentage',
        ])->all();

        if ($attributes['business_category'] === self::OTHER_CATEGORY) {
            $attributes['business_category'] = trim((string) $data['business_category_other']);
        }

        $attributes['city'] = filled($data['city'] ?? null) ? $data['city'] : $data['city_area'];

        return $attributes;
    }

    /**
     * Only submitted contact fields are passed on, so omitted fields never erase stored contact details.
     */
    private function contactInput(array $data): array
    {
        $map = [
            'contact_name' => 'name',
            'contact_role' => 'role',
            'contact_phone' => 'phone',
            'contact_whatsapp' => 'whatsapp',
            'contact_email' => 'email',
        ];

        $contact = [];
        foreach ($map as $input => $key) {
            if (array_key_exists($input, $data)) {
                $contact[$key] = $data[$input];
            }
        }

        return $contact;
    }

    private function formOptions(?Client $client = null): array
    {
        $referenceData = app(ReferenceDataService::class);
        $hasCategoryOptions = $referenceData->options(ReferenceDataService::CLIENT_CATEGORY) !== [];

        return [
            'leadSourceOptions' => $referenceData->options(ReferenceDataService::LEAD_SOURCE, $client?->lead_source),
            // Controlled category select once categories are configured; until then free text keeps create usable.
            'categoryOptions' => $hasCategoryOptions
                ? $referenceData->options(ReferenceDataService::CLIENT_CATEGORY, $client?->business_category)
                : [],
            'businessCategorySuggestions' => $this->businessCategorySuggestions($client?->business_category),
            'cityAreaSuggestions' => array_keys($referenceData->options(ReferenceDataService::CITY_AREA)),
            'otherCategoryValue' => self::OTHER_CATEGORY,
        ];
    }

    private function businessCategorySuggestions(?string $current = null): array
    {
        return Client::query()
            ->whereNotNull('business_category')
            ->where('business_category', '!=', '')
            ->distinct()
            ->orderBy('business_category')
            ->limit(30)
            ->pluck('business_category')
            ->when(filled($current), fn ($categories) => $categories->prepend($current))
            ->unique()
            ->values()
            ->all();
    }

    private function clientValidationMessages(): array
    {
        return [
            'business_name.required' => __('notify.clients.validation.business_name_required'),
            'business_category.required' => __('notify.clients.validation.business_category_required'),
            'phone.required' => __('notify.clients.validation.phone_required'),
            'city_area.required' => __('notify.clients.validation.city_area_required'),
            'lead_source.required' => __('notify.clients.validation.lead_source_required'),
            'primary_phone_type.required' => __('notify.clients.contact_model.validation.primary_phone_type_required'),
            'primary_phone_type.in' => __('notify.clients.contact_model.validation.primary_phone_type_invalid'),
            'contact_email.email' => __('notify.clients.contact_model.validation.contact_email_invalid'),
            'business_category_other.required_if' => __('notify.clients.contact_model.validation.business_category_other_required'),
        ];
    }

    private function percentageToBps(mixed $percentage): ?int
    {
        if ($percentage === null || $percentage === '') {
            return null;
        }

        return (int) round(((float) $percentage) * 100);
    }
}
