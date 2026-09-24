<?php

namespace App\ViewModels;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientReviewItem;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use App\Support\Permissions;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Client Workspace (P10, §19). Presents one customer as an operational command centre.
 *
 * The state card and its actions are derived deterministically from existing workflow state
 * (stage, open appointments/follow-ups, money due, subscriptions) and the P2 permission matrix;
 * no business rule is decided here. Every action posts to the existing controllers/services.
 */
class ClientWorkspaceViewModel
{
    /** Activity types that are accounting/engine plumbing rather than customer history (§19). */
    private const HIDDEN_ACTIVITY_TYPES = [
        'payment_allocated', 'allocation_reversed', 'revenue_schedule_created', 'one_time_service_recognition_confirmed',
        'cash_event_account_assigned', 'credit_note_application_reversed', 'invoice_created',
    ];

    /** Types whose stored description carries engine wording; the localized title is enough. */
    private const TITLE_ONLY_ACTIVITY_TYPES = ['payment_received_v2'];

    /** Sheet ids used by the workspace action sheets (also reachable with ?open=). */
    public const SHEETS = [
        'record-call' => 'modal-record-call',
        'create-appointment' => 'modal-create-appointment',
        'appointment-result' => 'modal-appointment-result',
        'schedule-installation' => 'modal-schedule-installation',
        'complete-installation' => 'modal-complete-installation',
        'follow-up' => 'modal-follow-up',
        'record-payment' => 'modal-record-payment',
        'start-subscription' => 'modal-start-subscription',
        'grant-access' => 'modal-grant-access',
        'close-client' => 'modal-close-client',
        'reopen-client' => 'modal-reopen-client',
    ];

    public function __construct(
        public readonly array $header,
        public readonly array $state,
        public readonly ?array $primaryAction,
        public readonly array $secondaryActions,
        public readonly array $moreActions,
        public readonly ?array $review,
        public readonly array $subscription,
        public readonly array $systems,
        public readonly ?array $money,
        public readonly array $contracts,
        public readonly array $details,
        public readonly ?array $referral,
        public readonly array $activity,
        public readonly array $sheets,
        public readonly array $can,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data  Collections loaded by ClientController::show.
     */
    public static function make(Client $client, User $actor, array $data): self
    {
        $stage = ClientLifecycle::normalizeStage($client->stage, $client->status);
        $can = [
            'update' => Gate::forUser($actor)->allows('update', $client),
            'record_payment' => Permissions::allows($actor, Permissions::RECORD_PAYMENT),
            'submit_receipt' => Permissions::allows($actor, Permissions::SUBMIT_PAYMENT_RECEIPT),
            'approve_receipts' => Permissions::allows($actor, Permissions::APPROVE_PAYMENT_RECEIPTS),
            'start_subscription' => Permissions::allows($actor, Permissions::START_PAID_SUBSCRIPTION),
            'manage_lifecycle' => Permissions::allows($actor, Permissions::MANAGE_SUBSCRIPTION_LIFECYCLE),
            'manage_access' => Permissions::allows($actor, Permissions::MANAGE_SYSTEM_ACCESS),
            'issue_contracts' => Permissions::allows($actor, Permissions::ISSUE_CONTRACTS),
            'view_finance' => Permissions::allows($actor, Permissions::VIEW_FINANCIAL_REPORTS),
            'edit_commission' => Permissions::allows($actor, Permissions::EDIT_REFERRAL_COMMISSION),
            'view_custom_projects' => Permissions::allows($actor, Permissions::VIEW_CUSTOM_PROJECTS),
        ];

        /** @var Collection<int, Subscription> $subscriptions */
        $subscriptions = $data['subscriptions'];
        $active = $subscriptions->where('status', 'active')->values();
        $receivable = $data['receivable'];
        $appointments = $data['appointments'];
        $installation = $appointments->firstWhere('appointment_type', AppointmentTypes::INSTALLATION);
        $meeting = $appointments->first(fn (Appointment $appointment) => $appointment->appointment_type !== AppointmentTypes::INSTALLATION);
        $followUp = $data['followUp'];
        $pendingReceipts = $data['pendingReceipts'];
        $pendingReview = $client->reviewItems->firstWhere('status', ClientReviewItem::STATUS_PENDING);

        $context = [
            'stage' => $stage,
            'active' => $active,
            'last' => $subscriptions->first(),
            'due' => (int) $receivable['due_minor'],
            'overdue' => (int) $receivable['overdue_minor'],
            'next_due' => $receivable['next_due_date'],
            'pending_receipts' => $pendingReceipts->count(),
            'installation' => $installation,
            'meeting' => $meeting,
            'follow_up' => $followUp,
            'contact_attempts' => (int) $data['contactAttempts'],
            'can' => $can,
            'phone' => $client->phone ?: $client->business_phone,
        ];

        [$state, $actionKeys] = self::resolveState($client, $context);
        $actionKeys = array_values(array_filter($actionKeys));
        $actions = self::actions($client, $context);
        $primaryKey = $actionKeys[0] ?? null;
        $primary = $primaryKey ? ($actions[$primaryKey] ?? null) : null;
        $rest = collect($actionKeys)->slice(1)->merge(array_keys($actions))->unique()
            ->reject(fn (string $key) => $key === $primaryKey || ! isset($actions[$key]))
            ->map(fn (string $key) => $actions[$key]);
        [$danger, $regular] = $rest->partition(fn (array $action) => ($action['tone'] ?? null) === 'danger');
        $secondary = $regular->take(2)->values()->all();
        $more = $regular->slice(2)->merge($danger)->values()->all();

        return new self(
            header: self::header($client, $stage, $active, $data),
            state: $state,
            primaryAction: $primary,
            secondaryActions: $secondary,
            moreActions: $more,
            review: $pendingReview ? [
                'id' => $pendingReview->id,
                'title' => __('notify.client_hub.review.title'),
                'type' => trans()->has('notify.client_hub.review.types.'.$pendingReview->type) ? __('notify.client_hub.review.types.'.$pendingReview->type) : null,
                'note' => $pendingReview->note ?? null,
                'can_resolve' => $can['update'],
            ] : null,
            subscription: self::subscriptionCard($subscriptions, $can),
            systems: self::systemsCard($client, $data, $can, $stage === ClientLifecycle::CLOSED),
            money: self::moneyCard($client, $context, $receivable, $data['latestPayment'], $pendingReceipts, $can),
            contracts: self::contractsCard($data['contracts'], $actor, $can),
            details: self::details($client),
            referral: self::referral($client, $can),
            activity: self::activity($data['activity']) + ['has_more' => (bool) $data['activityHasMore'], 'showing_all' => (bool) $data['activityAll']],
            sheets: [
                'meeting' => $meeting,
                'installation' => $installation,
                'follow_up' => $followUp,
            ],
            can: $can,
        );
    }

    /**
     * Deterministic next step (§11): [state, ordered action keys (first = primary)].
     *
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private static function resolveState(Client $client, array $c): array
    {
        $can = $c['can'];
        $payKey = $can['record_payment'] ? 'record_payment' : ($can['submit_receipt'] ? 'payment_received' : null);

        if ($c['stage'] === ClientLifecycle::CLOSED) {
            return [self::state('closed', 'neutral', 'x', [
                'date' => ClientPresenter::date($client->closed_at),
                'reason' => ClientPresenter::closedReasonLabel($client->closed_reason),
            ]), ['reopen', 'edit']];
        }

        if ($c['stage'] === ClientLifecycle::FORMER_SUBSCRIBER) {
            $last = $c['last'];
            $ended = $last?->ended_at ?? $last?->cancelled_at ?? $last?->current_period_end;

            return [self::state('renewal', 'warning', 'activity', [
                'date' => ClientPresenter::date($ended),
                'systems' => $last ? ClientPresenter::systemNames($last) : null,
            ]), ['renew', 'record_call']];
        }

        if ($c['stage'] === ClientLifecycle::SUBSCRIBER) {
            if ($c['overdue'] > 0) {
                return [self::state('payment_overdue', 'danger', 'alert-circle', ['money_minor' => $c['overdue']]), [$payKey, 'record_call']];
            }
            if ($c['due'] > 0) {
                return [self::state('payment_due', 'warning', 'wallet', [
                    'money_minor' => $c['due'],
                    'date' => ClientPresenter::date($c['next_due']),
                ]), [$payKey, 'record_call']];
            }
            if ($c['pending_receipts'] > 0) {
                return [self::state('payment_pending', 'info', 'clipboard-list', ['count' => $c['pending_receipts']]), [$can['approve_receipts'] ? 'review_receipts' : 'record_call']];
            }

            $active = $c['active']->first();
            $renewal = $active?->current_period_end ?? $active?->next_billing_date ?? $active?->renewal_date;

            return [self::state($active?->cancel_at_period_end ? 'subscriber_ending' : 'subscriber', $active?->cancel_at_period_end ? 'warning' : 'success', 'check-circle', [
                'date' => ClientPresenter::date($renewal),
            ]), ['record_call', 'start_subscription']];
        }

        if ($c['installation']) {
            $due = Carbon::parse($c['installation']->appointment_date)->lessThanOrEqualTo(today());

            return [self::state($due ? 'installation_due' : 'installation_upcoming', $due ? 'warning' : 'info', 'wrench', [
                'when' => ClientPresenter::appointmentWhen($c['installation']),
            ]), ['complete_installation', 'record_call']];
        }

        if ($c['meeting']) {
            $due = Carbon::parse($c['meeting']->appointment_date)->lessThanOrEqualTo(today());

            return [self::state($due ? 'appointment_result' : 'appointment_upcoming', $due ? 'warning' : 'info', 'calendar', [
                'when' => ClientPresenter::appointmentWhen($c['meeting']),
            ]), ['appointment_result', 'record_call']];
        }

        if ($c['follow_up']) {
            $at = Carbon::parse($c['follow_up']->follow_up_date_time);
            $due = $at->lessThanOrEqualTo(now());

            return [self::state($due ? 'follow_up_due' : 'follow_up_scheduled', $due ? 'warning' : 'info', 'clipboard-list', [
                'when' => ClientPresenter::dateTime($at),
            ]), [$due ? 'follow_up' : 'record_call', $due ? 'record_call' : 'follow_up']];
        }

        return match ($c['stage']) {
            ClientLifecycle::INSTALLED_FREE, ClientLifecycle::DECISION_PENDING => [
                self::state('decision', 'warning', 'clipboard-list'),
                [$can['start_subscription'] ? 'start_subscription' : 'record_call', 'record_call', 'create_appointment'],
            ],
            ClientLifecycle::INSTALLATION_SCHEDULED => [self::state('schedule_installation', 'info', 'wrench'), ['schedule_installation', 'record_call']],
            ClientLifecycle::CONTACTING, ClientLifecycle::APPOINTMENT => [self::state('schedule_appointment', 'info', 'calendar'), ['create_appointment', 'record_call']],
            default => $c['contact_attempts'] === 0
                ? [self::state('first_contact', 'info', 'phone'), ['record_call', 'create_appointment']]
                : [self::state('schedule_appointment', 'info', 'calendar'), ['create_appointment', 'record_call']],
        };
    }

    /** @return array<string, mixed> */
    private static function state(string $key, string $tone, string $icon, array $params = []): array
    {
        $replace = array_merge(
            ['date' => '—', 'when' => '—', 'count' => 0],
            collect($params)->filter(fn ($value) => is_string($value) || is_int($value))->all(),
        );

        return [
            'key' => $key,
            'tone' => $tone,
            'icon' => $icon,
            'title' => __('notify.client_hub.state.'.$key.'.title', $replace),
            'detail' => __('notify.client_hub.state.'.$key.'.detail', $replace),
            'params' => $params,
        ];
    }

    /**
     * Every action the actor may take on this client right now, keyed for the resolver.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function actions(Client $client, array $c): array
    {
        $can = $c['can'];
        $stage = $c['stage'];
        $open = ! in_array($stage, [ClientLifecycle::CLOSED], true);
        $pipeline = ! in_array($stage, [ClientLifecycle::SUBSCRIBER, ClientLifecycle::FORMER_SUBSCRIBER, ClientLifecycle::CLOSED], true);
        $actions = [];
        $sheet = fn (string $key, string $label, string $icon, array $extra = []) => ['key' => $key, 'label' => $label, 'icon' => $icon, 'sheet' => self::SHEETS[$key]] + $extra;

        if ($can['update'] && $open) {
            $actions['record_call'] = $sheet('record-call', __('notify.client_hub.actions.record_call'), 'phone');
            if ($c['meeting']) {
                $actions['appointment_result'] = $sheet('appointment-result', __('notify.client_hub.actions.appointment_result'), 'check');
            }
            if ($pipeline) {
                $actions['create_appointment'] = $sheet('create-appointment', __('notify.client_hub.actions.create_appointment'), 'calendar');
                if ($c['installation']) {
                    $actions['complete_installation'] = $sheet('complete-installation', __('notify.client_hub.actions.complete_installation'), 'check');
                } else {
                    $actions['schedule_installation'] = $sheet('schedule-installation', __('notify.client_hub.actions.schedule_installation'), 'wrench');
                }
            }
            if ($c['follow_up']) {
                $actions['follow_up'] = $sheet('follow-up', __('notify.client_hub.actions.follow_up'), 'clipboard-list');
            }
        }

        if ($can['start_subscription'] && $open) {
            $key = $stage === ClientLifecycle::FORMER_SUBSCRIBER ? 'renew' : 'start_subscription';
            $label = $stage === ClientLifecycle::FORMER_SUBSCRIBER
                ? __('notify.client_hub.actions.renew')
                : ($stage === ClientLifecycle::SUBSCRIBER ? __('notify.client_hub.actions.add_subscription') : __('notify.client_hub.actions.start_subscription'));
            $actions[$key] = $sheet('start-subscription', $label, 'plus');
        }

        $hasMoneyContext = $c['due'] > 0 || $c['active']->isNotEmpty() || $c['last'] !== null;
        if ($hasMoneyContext && $open) {
            if ($can['record_payment']) {
                $actions['record_payment'] = $sheet('record-payment', __('notify.client_hub.actions.record_payment'), 'wallet');
            } elseif ($can['submit_receipt']) {
                $actions['payment_received'] = $sheet('record-payment', __('notify.client_hub.actions.payment_received'), 'wallet');
            }
        }

        if ($can['approve_receipts'] && $c['pending_receipts'] > 0) {
            $actions['review_receipts'] = [
                'key' => 'review-receipts',
                'label' => __('notify.client_hub.actions.review_receipts'),
                'icon' => 'clipboard-list',
                'href' => route('finance.collections', ['tab' => 'pending', 'client_id' => $client->id]),
            ];
        }

        if ($can['update']) {
            $actions['edit'] = ['key' => 'edit', 'label' => __('notify.client_hub.actions.edit'), 'icon' => 'settings', 'href' => route('clients.edit', $client->id)];
            if ($stage === ClientLifecycle::CLOSED) {
                $actions['reopen'] = $sheet('reopen-client', __('notify.client_hub.actions.reopen'), 'activity');
            } elseif ($c['active']->isEmpty()) {
                // §4: an active paid subscription blocks closing; the server enforces it too.
                $actions['close'] = $sheet('close-client', __('notify.client_hub.actions.close'), 'x', ['tone' => 'danger']);
            }
        }

        return $actions;
    }

    /** @return array<string, mixed> */
    private static function header(Client $client, string $stage, Collection $active, array $data): array
    {
        $phone = $client->phone ?: $client->business_phone;
        $activeSubscription = $active->first();

        return [
            'name' => (string) $client->business_name,
            'category' => $client->business_category ?: $client->business_type,
            'area' => $client->city_area ?: $client->city,
            'phone' => $phone,
            'tel' => ClientPresenter::telUrl($phone),
            'whatsapp' => ClientPresenter::whatsappUrl($client->preferredOperationalContact()['whatsapp_number'] ?: $phone),
            'stage' => $stage,
            'stage_label' => ClientPresenter::stageLabel($stage),
            'stage_tone' => ClientPresenter::stageTone($stage),
            'stage_icon' => ClientPresenter::stageIcon($stage),
            'subscription_chip' => match (true) {
                $activeSubscription && $activeSubscription->cancel_at_period_end => ['tone' => 'warning', 'label' => __('notify.client_hub.subscription.chip_ending')],
                $activeSubscription !== null => ['tone' => 'success', 'label' => ClientPresenter::cycleLabel($activeSubscription).' · '.__('notify.client_hub.subscription.chip_active')],
                default => null,
            },
            'responsible' => $client->primaryOwner?->name,
            'custom_projects' => (int) $data['customProjectsCount'],
        ];
    }

    /** @return array<string, mixed> */
    private static function subscriptionCard(Collection $subscriptions, array $can): array
    {
        $map = fn (Subscription $subscription) => [
            'id' => $subscription->id,
            'systems' => ClientPresenter::systemNames($subscription),
            'cycle' => ClientPresenter::cycleLabel($subscription),
            'is_annual' => ($subscription->billing_interval_v2 ?: $subscription->billing_type) === 'annual',
            'installments' => $subscription->payment_terms === 'installments' ? (int) $subscription->installments_count : null,
            'value_minor' => (int) ($subscription->agreed_value_minor ?? $subscription->total_minor ?? round(((float) $subscription->total_price) * 1000)),
            'start' => $subscription->start_date?->format('Y-m-d'),
            'period_end' => ($subscription->current_period_end ?? $subscription->renewal_date)?->format('Y-m-d'),
            'status' => $subscription->status,
            'ending' => $subscription->status === 'active' && $subscription->cancel_at_period_end,
            'ended' => ($subscription->ended_at ?? $subscription->cancelled_at)?->format('Y-m-d'),
            'can_cancel' => $can['manage_lifecycle'] && $subscription->status === 'active' && ! $subscription->cancel_at_period_end,
            'can_undo' => $can['manage_lifecycle'] && $subscription->status === 'active' && $subscription->cancel_at_period_end,
        ];

        return [
            'active' => $subscriptions->where('status', 'active')->map($map)->values()->all(),
            'past' => $subscriptions->where('status', '!=', 'active')->map($map)->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private static function systemsCard(Client $client, array $data, array $can, bool $closed): array
    {
        $panel = $data['credentialPanel'];
        $showCredentials = $panel['can_manage'] || $panel['can_reveal'];
        $credentials = $panel['credentials'];

        $rows = $client->systems->toBase()
            ->filter(fn ($system) => $system->pivot->revoked_at === null)
            ->unique('id')
            ->map(fn ($system) => [
                'system' => $system,
                'name' => ClientPresenter::systemName($system),
                'access' => $system->pivot->access_type === 'paid' ? 'paid' : 'free',
                'granted' => $system->pivot->granted_at ? Carbon::parse($system->pivot->granted_at)->format('Y-m-d') : null,
                'requires_credentials' => (bool) $system->requires_credentials,
                'credential' => $credentials->get($system->id),
                'show_credential' => $showCredentials && $system->requires_credentials,
                'can_revoke' => $can['manage_access'] && $system->pivot->access_type !== 'paid',
            ]);

        // A credential may be stored before access is granted (§18.1): keep it next to the Systems.
        $withoutAccess = $showCredentials
            ? $panel['systems']->toBase()->reject(fn ($system) => $rows->contains(fn ($row) => $row['system']->id === $system->id))
                ->map(fn ($system) => [
                    'system' => $system,
                    'name' => ClientPresenter::systemName($system),
                    'access' => null,
                    'granted' => null,
                    'requires_credentials' => true,
                    'credential' => $credentials->get($system->id),
                    'show_credential' => true,
                    'can_revoke' => false,
                ])
            : collect();

        $activeIds = $rows->pluck('system.id');

        return [
            'rows' => $rows->merge($withoutAccess)->values()->all(),
            // A closed file keeps its history but offers no new access or login details.
            'grantable' => $can['manage_access'] && ! $closed ? $data['sellableProducts']->whereNotIn('id', $activeIds)->values() : collect(),
            'can_grant' => $can['manage_access'] && ! $closed,
            'can_add_credentials' => $panel['can_manage'] && ! $closed,
            'show_credentials' => $showCredentials,
        ];
    }

    /** @return array<string, mixed>|null */
    private static function moneyCard(Client $client, array $c, array $receivable, ?Payment $latestPayment, Collection $pendingReceipts, array $can): ?array
    {
        $relevant = $c['due'] > 0 || $latestPayment !== null || $pendingReceipts->isNotEmpty()
            || $c['active']->isNotEmpty() || $c['last'] !== null;
        if (! $relevant) {
            return null;
        }

        return [
            'due_minor' => $c['due'],
            'overdue_minor' => $c['overdue'],
            'next_due' => ClientPresenter::date($c['next_due']),
            'credit_minor' => $can['view_finance'] ? (int) $receivable['credit_minor'] : 0,
            'latest_payment' => $latestPayment ? [
                'amount_minor' => (int) ($latestPayment->amount_minor ?? round(((float) $latestPayment->amount) * 1000)),
                'date' => ClientPresenter::date($latestPayment->paid_at),
                'method' => $latestPayment->payment_method ? __('notify.client_workspace.payment_methods.'.$latestPayment->payment_method) : null,
                'reference' => $latestPayment->reference ?: ($latestPayment->reference_number ?? null),
            ] : null,
            'pending' => $pendingReceipts,
            'details_url' => $can['view_finance'] ? route('finance.collections', ['client_id' => $client->id]) : null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function contractsCard(Collection $contracts, User $actor, array $can): array
    {
        return $contracts
            ->reject(fn (Contract $contract) => $contract->isVoided())
            ->take(3)
            ->map(fn (Contract $contract) => [
                'id' => $contract->id,
                'is_draft' => $contract->isDraft(),
                'number' => $contract->isDraft() ? null : $contract->contract_number,
                'status' => $contract->status,
                'status_label' => trans()->has('notify.client_hub.contract.status.'.$contract->status)
                    ? __('notify.client_hub.contract.status.'.$contract->status)
                    : $contract->status,
                'issued_at' => $contract->issued_at ? Carbon::parse($contract->issued_at)->format('Y-m-d') : null,
                'preview_url' => Gate::forUser($actor)->allows('view', $contract) ? route('contracts.preview', $contract->id) : null,
                'pdf_url' => Gate::forUser($actor)->allows('download', $contract) ? route('contracts.download-pdf', $contract->id) : null,
                'issue_url' => $contract->isDraft() && $can['issue_contracts'] ? route('contracts.issue', $contract->id) : null,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private static function details(Client $client): array
    {
        // Primary contact first; legacy files may only have other contacts or the old free-text name.
        $primary = $client->contacts->firstWhere('is_primary', true) ?? $client->contacts->first();
        $businessPhone = $client->business_phone ?: ($client->primary_phone_type === 'business' || blank($client->primary_phone_type) ? $client->phone : null);
        $contactPhone = $primary?->primary_phone;

        return [
            'business' => [
                'phone' => $businessPhone,
                'tel' => ClientPresenter::telUrl($businessPhone),
                'category' => $client->business_category ?: $client->business_type,
                'area' => $client->city_area ?: $client->city,
                'location' => $client->location_text ?: null,
                'maps_url' => filled($client->maps_url) && str_starts_with((string) $client->maps_url, 'http') ? $client->maps_url : null,
                'lead_source' => $client->lead_source,
                'phone_owner' => __('notify.clients.contact_model.phone_types.'.(in_array($client->primary_phone_type, Client::PRIMARY_PHONE_TYPES, true) ? $client->primary_phone_type : 'business')),
            ],
            'contact' => $primary ? [
                'name' => filled($primary->name) ? $primary->name : null,
                'role' => match ($primary->role) {
                    'owner', 'manager', 'other' => __('notify.clients.contact_roles.'.$primary->role),
                    default => $primary->role,
                },
                'phone' => $contactPhone,
                'tel' => ClientPresenter::telUrl($contactPhone),
                'whatsapp' => $primary->whatsapp_number,
                'whatsapp_url' => ClientPresenter::whatsappUrl($primary->whatsapp_number ?: $contactPhone),
                'email' => $primary->email,
            ] : (filled($client->contact_person) ? [
                'name' => $client->contact_person,
                'role' => null,
                'phone' => null,
                'tel' => null,
                'whatsapp' => null,
                'whatsapp_url' => null,
                'email' => null,
            ] : null),
            'other_contacts' => max(0, $client->contacts->count() - 1),
            'notes' => $client->notes,
            'edit_url' => route('clients.edit', $client->id),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function referral(Client $client, array $can): ?array
    {
        $bps = $client->referral_commission_bps;
        if (blank($client->referred_by_name) && blank($client->referral_note) && ($bps === null || ! $can['edit_commission'])) {
            return null;
        }

        return [
            'name' => $client->referred_by_name,
            'note' => $client->referral_note,
            // Commission % is Owner-only (§2.3, D-08): not rendered for Staff at all.
            'commission' => $can['edit_commission'] && $bps !== null ? sprintf('%d.%02d%%', intdiv($bps, 100), $bps % 100) : null,
        ];
    }

    /** @return array<string, mixed> */
    private static function activity(Collection $events): array
    {
        $items = $events
            ->reject(fn ($event) => in_array($event->type, self::HIDDEN_ACTIVITY_TYPES, true))
            ->map(function ($event) {
                $key = 'notify.client_hub.activity.types.'.$event->type;
                $title = trans()->has($key) ? __($key) : null;

                return [
                    'title' => $title ?? (string) $event->description,
                    'description' => $title && filled($event->description) && $event->description !== $title
                        && ! in_array($event->type, self::TITLE_ONLY_ACTIVITY_TYPES, true) ? (string) $event->description : null,
                    'actor' => $event->actor_name,
                    'at' => Carbon::parse($event->created_at),
                    'when' => ClientPresenter::dateTime($event->created_at),
                    'icon' => self::activityIcon((string) $event->type),
                ];
            })
            ->values();

        return ['items' => $items->all()];
    }

    private static function activityIcon(string $type): string
    {
        return match (true) {
            str_contains($type, 'payment') || str_contains($type, 'refund') || str_contains($type, 'invoice') || str_contains($type, 'credit_note') => 'wallet',
            str_contains($type, 'subscription') || str_contains($type, 'former') => 'check-circle',
            str_contains($type, 'contract') => 'file-chart',
            str_contains($type, 'appointment') || str_contains($type, 'meeting') || str_contains($type, 'callback') => 'calendar',
            str_contains($type, 'installation') || str_contains($type, 'trial') => 'wrench',
            str_contains($type, 'credential') || str_contains($type, 'access') => 'key-round',
            str_contains($type, 'contact') || str_contains($type, 'call') => 'phone',
            str_contains($type, 'closed') || str_contains($type, 'reopen') || str_contains($type, 'stage') => 'activity',
            default => 'circle',
        };
    }
}
