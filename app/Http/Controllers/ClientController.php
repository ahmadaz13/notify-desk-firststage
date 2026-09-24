<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientReviewItem;
use App\Models\ContactAttempt;
use App\Models\Contract;
use App\Models\CustomProject;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReceiptConfirmation;
use App\Models\Product;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ClientCredentialService;
use App\Services\ClientOperationalWorkflowService;
use App\Services\ClientPrimaryContactService;
use App\Services\ReceivableService;
use App\Services\ReferenceDataService;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use App\Support\ClientSegments;
use App\Support\Money;
use App\Support\PaymentMethods;
use App\Support\Permissions;
use App\ViewModels\ClientListViewModel;
use App\ViewModels\ClientWorkspaceViewModel;
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

    public function index(Request $request, ReceivableService $receivables): View
    {
        Gate::authorize('viewAny', Client::class);
        $user = $request->user();

        // Segment (§5): explicit ?view= wins and is remembered for the session; otherwise the role default (D-03).
        $requested = $request->query('view');
        if (ClientSegments::isValid($requested)) {
            $request->session()->put('clients.view', $requested);
            $segment = $requested;
        } else {
            $remembered = $request->session()->get('clients.view');
            $segment = ClientSegments::isValid($remembered) ? $remembered : ClientSegments::defaultFor($user);
        }

        $filters = [
            'view' => $segment,
            'q' => trim((string) $request->query('q', '')),
            'category' => trim((string) $request->query('category', '')),
            'area' => trim((string) $request->query('area', '')),
            'stage' => $segment === ClientSegments::PROSPECTS && in_array($request->query('stage'), ClientSegments::PROSPECT_STAGES, true)
                ? (string) $request->query('stage')
                : '',
        ];

        $query = Client::query()
            ->select('clients.*')
            ->selectSub(
                DB::table('activity_logs')
                    ->selectRaw('MAX(activity_logs.created_at)')
                    ->whereColumn('activity_logs.client_id', 'clients.id'),
                'last_activity_at'
            );

        if (($stages = ClientSegments::stages($segment)) !== null) {
            $query->whereIn('stage', $filters['stage'] !== '' ? [$filters['stage']] : $stages);
        }

        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $digits = preg_replace('/\D/', '', $filters['q']);
            $query->where(function ($inner) use ($term, $digits) {
                $inner->where('business_name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('business_phone', 'like', $term)
                    ->orWhere('city_area', 'like', $term)
                    ->orWhereHas('contacts', fn ($contacts) => $contacts
                        ->where('name', 'like', $term)
                        ->orWhere('primary_phone', 'like', $term)
                        ->orWhere('whatsapp_number', 'like', $term));
                // Phone numbers are stored with spaces; also match the typed digits without them.
                if (strlen($digits) >= 4) {
                    $inner->orWhereRaw("REPLACE(REPLACE(phone, ' ', ''), '-', '') LIKE ?", ['%'.$digits.'%'])
                        ->orWhereRaw("REPLACE(REPLACE(business_phone, ' ', ''), '-', '') LIKE ?", ['%'.$digits.'%']);
                }
            });
        }

        if ($filters['category'] !== '') {
            $query->where('business_category', $filters['category']);
        }
        if ($filters['area'] !== '') {
            $query->where('city_area', $filters['area']);
        }

        $query->when(
            $segment === ClientSegments::CLOSED,
            fn ($closed) => $closed->orderByDesc('closed_at'),
            fn ($open) => $open->orderByDesc('last_activity_at'),
        )->orderByDesc('clients.id');

        $clients = $query->paginate(15)->withQueryString();

        $stageCounts = Client::query()->select('stage', DB::raw('COUNT(*) as total'))->groupBy('stage')->pluck('total', 'stage');
        $segmentCounts = collect(ClientSegments::SEGMENTS)
            ->reject(fn (string $key) => $key === ClientSegments::ALL)
            ->mapWithKeys(fn (string $key) => [$key => (int) $stageCounts->only(ClientSegments::stages($key))->sum()])
            ->all();

        $filterOptions = [
            'categories' => Client::query()->whereNotNull('business_category')->where('business_category', '!=', '')
                ->distinct()->orderBy('business_category')->limit(60)->pluck('business_category')->all(),
            'areas' => Client::query()->whereNotNull('city_area')->where('city_area', '!=', '')
                ->distinct()->orderBy('city_area')->limit(60)->pluck('city_area')->all(),
            'stages' => $segment === ClientSegments::PROSPECTS
                ? collect(ClientSegments::PROSPECT_STAGES)->mapWithKeys(fn (string $stage) => [$stage => __('notify.clients.stages.'.$stage)])->all()
                : [],
        ];

        $list = ClientListViewModel::make($clients, $segment, $segmentCounts, $filters, $filterOptions, $user, $receivables);

        return view('clients.index', ['list' => $list]);
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

    public function show(Request $request, int $id, ReceivableService $receivableService, ClientCredentialService $credentialService): View
    {
        $client = Client::findOrFail($id);
        Gate::authorize('view', $client);
        $user = $request->user();

        // High-value summaries only (§19, P10): no allocation/journal/credit-note engine data is loaded here.
        $client->load(['contacts', 'primaryOwner', 'systems', 'reviewItems' => fn ($items) => $items->where('status', ClientReviewItem::STATUS_PENDING)]);

        $subscriptions = Subscription::query()
            ->with(['systems', 'plan.product'])
            ->where('client_id', $client->id)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $appointments = Appointment::query()
            ->with('users')
            ->where('client_id', $client->id)
            ->whereIn('status', AppointmentTypes::activeStatuses())
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->get();

        $followUp = DB::table('follow_ups')
            ->where('client_id', $client->id)
            ->whereNull('completed_at')
            ->orderBy('follow_up_date_time')
            ->first();

        $invoices = Invoice::query()->where('client_id', $client->id)->get();
        $projections = $receivableService->invoiceProjections($invoices);
        $summary = $receivableService->clientSummary($client);
        $receivable = [
            'due_minor' => (int) $summary['total_outstanding_minor'],
            'overdue_minor' => (int) $summary['overdue_outstanding_minor'],
            'credit_minor' => (int) $summary['total_customer_credit_minor'],
            'next_due_date' => $invoices
                ->filter(fn (Invoice $invoice) => ($projections[$invoice->id]['outstanding_minor'] ?? 0) > 0)
                ->pluck('due_date')->filter()->sort()->first(),
        ];

        $latestPayment = Payment::query()
            ->where('client_id', $client->id)
            ->whereDoesntHave('reversal')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first();

        $pendingReceipts = PaymentReceiptConfirmation::with('submitter:id,name')
            ->where('client_id', $client->id)
            ->pending()
            ->orderByDesc('received_at')
            ->get();

        $showAllActivity = $request->query('activity') === 'all';
        $activityLimit = $showAllActivity ? 200 : 10;
        $activity = DB::table('activity_logs')
            ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
            ->where('activity_logs.client_id', $client->id)
            ->orderByDesc('activity_logs.created_at')
            ->orderByDesc('activity_logs.id')
            ->limit($activityLimit + 1)
            ->get(['activity_logs.type', 'activity_logs.description', 'activity_logs.created_at', 'users.name as actor_name']);

        $credentialSystems = $credentialService->applicableSystems($client);
        $credentialPanel = [
            'systems' => $credentialSystems,
            // Hidden attributes: secret/note never reach the view as values.
            'credentials' => $client->credentials()->get()->keyBy('product_id'),
            'addable' => $credentialService->addableSystems($client)->whereNotIn('id', $credentialSystems->pluck('id')),
            'recipient' => $credentialService->defaultRecipient($client),
            'can_manage' => Permissions::allows($user, Permissions::MANAGE_CLIENT_CREDENTIALS),
            'can_reveal' => Permissions::allows($user, Permissions::REVEAL_CLIENT_CREDENTIALS),
        ];

        $sellableProducts = Product::sellable()->orderBy('name_ar')->get();
        $contracts = Contract::query()->where('client_id', $client->id)->orderByDesc('id')->get();

        $workspace = ClientWorkspaceViewModel::make($client, $user, [
            'subscriptions' => $subscriptions,
            'appointments' => $appointments,
            'followUp' => $followUp,
            'receivable' => $receivable,
            'latestPayment' => $latestPayment,
            'pendingReceipts' => $pendingReceipts,
            'contracts' => $contracts,
            'contactAttempts' => ContactAttempt::where('client_id', $client->id)->count(),
            'activity' => $activity->take($activityLimit),
            'activityHasMore' => $activity->count() > $activityLimit,
            'activityAll' => $showAllActivity,
            'credentialPanel' => $credentialPanel,
            'sellableProducts' => $sellableProducts,
            'customProjectsCount' => CustomProject::where('client_id', $client->id)->count(),
        ]);

        // Data the existing action sheets need (§13: the workflow itself is unchanged).
        $teamUsers = User::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('role')->orWhereIn('role', User::activeInternalRoles()))
            ->orderBy('name')
            ->get();
        $openKey = (string) $request->query('open');

        return view('clients.show', [
            'client' => $client,
            'workspace' => $workspace,
            'teamUsers' => $teamUsers,
            'appointmentTypeLabels' => AppointmentTypes::labels(),
            'catalogServices' => Service::active()->get(),
            'paymentMethodOptions' => PaymentMethods::v1Labels(),
            'sellableProducts' => $sellableProducts,
            'credentialPanel' => $credentialPanel,
            'credentialService' => $credentialService,
            'amountDue' => [
                'total_minor' => $receivable['due_minor'],
                'total_formatted' => Money::fromMinorUnits($receivable['due_minor'])->format(),
                'currency' => __('notify.common.currency_jod'),
            ],
            'openSheet' => ClientWorkspaceViewModel::SHEETS[$openKey] ?? null,
        ]);
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
