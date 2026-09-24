<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientReviewItem;
use App\Models\PaymentReceiptConfirmation;
use App\Models\User;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use App\Support\OperationalSettings;
use App\Support\OperationalTime;
use App\Support\Permissions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Today Board / Open Work projection (§20, P11).
 *
 * A read-only projection over the authoritative workflow records (appointments, installations,
 * follow-ups, pending client reviews, receivables, first-contact prospects). It never stores
 * workflow state and never writes; every action links to the existing Client Workspace sheets.
 */
class UnifiedOperationalWorkProjection
{
    public const TYPE_CALL = 'call';
    public const TYPE_APPOINTMENT = 'appointment';
    public const TYPE_INSTALLATION = 'installation';
    public const TYPE_FOLLOW_UP = 'follow_up';
    public const TYPE_COLLECTION = 'collection';
    public const TYPE_REVIEW = 'review';

    public const FILTER_ALL = 'all';
    public const FILTER_CALLS = 'calls';
    public const FILTER_APPOINTMENTS = 'appointments';
    public const FILTER_INSTALLATIONS = 'installations';
    public const FILTER_FOLLOW_UPS = 'follow_ups';
    public const FILTER_COLLECTIONS = 'collections';

    /**
     * "Next" holds work due within this window from now. Intentionally a product constant: the
     * frozen Settings (§16) define no key for it.
     */
    public const NEXT_WINDOW_MINUTES = 120;

    /*
     * Settings-driven timing (§16, P13) — see OperationalSettings:
     *  - an appointment stays "now" (in progress) for appointment_duration minutes after it starts,
     *    an installation for free_installation_duration, before it counts as late;
     *  - date-only work (a payment due today) is ordered at workday_end;
     *  - an appointment without a time, or a follow-up with a date only, is placed at workday_start.
     */
    protected ?OperationalSettings $operationalSettings = null;

    /** Overdue ordering: operational importance first, then oldest first. */
    private const OVERDUE_TIERS = [
        self::TYPE_APPOINTMENT => 0,
        self::TYPE_INSTALLATION => 0,
        self::TYPE_FOLLOW_UP => 1,
        self::TYPE_CALL => 1,
        self::TYPE_COLLECTION => 2,
        self::TYPE_REVIEW => 3,
    ];

    /** Where Client Workspace links return after an action ("today" or "work") plus the scope. */
    protected array $returnContext = ['from' => 'today', 'scope' => 'all'];

    public function __construct(
        protected ReceivableService $receivableService
    ) {}

    /**
     * Today Board. Sections:
     * 1. Overdue: timed work past its time (appointments/installations after the in-progress window),
     *    payments past their due date, reviews waiting since an earlier day.
     * 2. Next: work in progress or due within NEXT_WINDOW_MINUTES, reviews raised today; when empty,
     *    the nearest later item is pulled forward so the next action is always obvious.
     * 3. Later Today: remaining work for today, chronological.
     * First-contact prospects are undated and are not part of the timed queue (count only).
     */
    public function today(User $user, ?Carbon $now = null, string $scope = 'all'): array
    {
        $this->operationalSettings = null;
        $now = $this->businessTime($now);
        $scope = $scope === 'my' ? 'my' : 'all';
        $this->returnContext = ['from' => 'today', 'scope' => $scope];

        $items = $this->applyScope(
            $this->projectAll($user, $now, $now->copy()->endOfDay(), false),
            $user,
            $scope
        );

        $overdue = collect();
        $next = collect();
        $laterToday = collect();
        $nextWindowEnd = $now->copy()->addMinutes(self::NEXT_WINDOW_MINUTES);

        foreach ($items as $item) {
            $state = $item['timing']['state'];

            if ($state === 'overdue') {
                $item['priority'] = 'overdue';
                $overdue->push($item);
            } elseif (in_array($state, ['now', 'soon', 'waiting'], true)
                || ($state === 'due_today' && Carbon::createFromTimestamp($item['sort_at'], OperationalTime::TIMEZONE)->lte($nextWindowEnd))) {
                $item['priority'] = 'next';
                $next->push($item);
            } elseif (in_array($state, ['scheduled', 'due_today'], true) && $item['due_at']?->isSameDay($now)) {
                $item['priority'] = 'later_today';
                $laterToday->push($item);
            }
        }

        $laterToday = $laterToday->sortBy('sort_at')->values();

        if ($next->isEmpty() && $laterToday->isNotEmpty()) {
            $first = $laterToday->shift();
            $first['priority'] = 'next';
            $next->push($first);
        }

        $overdue = $overdue->sortBy([
            fn (array $a, array $b) => (self::OVERDUE_TIERS[$a['type']] ?? 9) <=> (self::OVERDUE_TIERS[$b['type']] ?? 9),
            fn (array $a, array $b) => $a['sort_at'] <=> $b['sort_at'],
        ])->values();

        // Timed work first (in time order), then reviews raised today.
        $next = $next->sortBy([
            fn (array $a, array $b) => ($a['timing']['state'] === 'waiting') <=> ($b['timing']['state'] === 'waiting'),
            fn (array $a, array $b) => $a['sort_at'] <=> $b['sort_at'],
        ])->values();

        return [
            'scope' => $scope,
            'overdue' => $overdue,
            'next' => $next,
            'later_today' => $laterToday,
            'ready_to_contact' => $this->readyToContactCount($user, $now, $scope),
            'counts' => [
                'overdue' => $overdue->count(),
                'next' => $next->count(),
                'later_today' => $laterToday->count(),
                'total' => $overdue->count() + $next->count() + $laterToday->count(),
            ],
        ];
    }

    /**
     * Open Work (Today's internal "work" mode).
     * Filters: all, calls, appointments, installations, follow_ups, collections
     * Groups: Overdue, Today (incl. undated first-contact prospects), Upcoming (after today).
     */
    public function work(User $user, ?string $filter = self::FILTER_ALL, ?Carbon $now = null, string $scope = 'all'): array
    {
        $this->operationalSettings = null;
        $now = $this->businessTime($now);
        $scope = $scope === 'my' ? 'my' : 'all';
        $this->returnContext = ['from' => 'work', 'scope' => $scope];
        $filter = strtolower(trim($filter ?? self::FILTER_ALL));

        $validFilters = [
            self::FILTER_ALL,
            self::FILTER_CALLS,
            self::FILTER_APPOINTMENTS,
            self::FILTER_INSTALLATIONS,
            self::FILTER_FOLLOW_UPS,
            self::FILTER_COLLECTIONS,
        ];

        if (! in_array($filter, $validFilters, true)) {
            $filter = self::FILTER_ALL;
        }

        $allItems = $this->applyScope($this->projectAll($user, $now), $user, $scope);

        $overdue = collect();
        $today = collect();
        $upcoming = collect();

        foreach ($allItems->filter(fn (array $item) => $this->itemMatchesFilter($item, $filter)) as $item) {
            $state = $item['timing']['state'];

            if ($state === 'overdue') {
                $item['priority'] = 'overdue';
                $overdue->push($item);
            } elseif ($item['due_at'] !== null && $item['due_at']->copy()->startOfDay()->gt($now->copy()->startOfDay())) {
                $item['priority'] = 'upcoming';
                $upcoming->push($item);
            } else {
                $item['priority'] = 'today';
                $today->push($item);
            }
        }

        $overdue = $overdue->sortBy('sort_at')->values();
        $today = $today->sortBy('sort_at')->values();
        $upcoming = $upcoming->sortBy('sort_at')->values();

        $filterCounts = [];
        foreach ($validFilters as $f) {
            $filterCounts[$f] = $allItems->filter(fn ($item) => $this->itemMatchesFilter($item, $f))->count();
        }

        return [
            'filter' => $filter,
            'overdue' => $overdue,
            'today' => $today,
            'upcoming' => $upcoming,
            'filter_counts' => $filterCounts,
            'counts' => [
                'overdue' => $overdue->count(),
                'today' => $today->count(),
                'upcoming' => $upcoming->count(),
                'total' => $overdue->count() + $today->count() + $upcoming->count(),
            ],
        ];
    }

    /**
     * Gather open operational work across the authoritative sources.
     * $until bounds the fetch (Today needs nothing after the end of the operational day).
     */
    public function projectAll(User $user, ?Carbon $now = null, ?Carbon $until = null, bool $includeContactQueue = true): Collection
    {
        $now = $this->businessTime($now);
        $items = collect();

        foreach ($this->fetchAppointments($until) as $apt) {
            $items->push($this->formatAppointmentItem($apt, $now));
        }

        foreach ($this->fetchOpenFollowUps($until) as $fu) {
            $items->push($this->formatFollowUpItem($fu, $now));
        }

        foreach ($this->fetchPendingReviews() as $rev) {
            $items->push($this->formatReviewItem($rev, $now));
        }

        if ($includeContactQueue) {
            foreach ($this->activeContactQuery($now)->with('primaryOwner')->orderBy('created_at')->limit(20)->get() as $client) {
                $items->push($this->formatActiveContactItem($client, $now));
            }
        }

        // Operational collections (§9.7, D-07): Owner and Staff; amounts per client only, never company totals.
        if ($this->canViewCollections($user)) {
            $items = $items->concat($this->collectionItems($user, $now, $until));
        }

        return $items->values();
    }

    /** "My Work": items whose assignee/attendee or client primary owner is the user. */
    protected function applyScope(Collection $items, User $user, string $scope): Collection
    {
        if ($scope !== 'my') {
            return $items;
        }

        return $items->filter(fn (array $item) => in_array($user->id, $item['assigned_user_ids'] ?? [], true))->values();
    }

    protected function itemMatchesFilter(array $item, string $filter): bool
    {
        if ($filter === self::FILTER_ALL) {
            return true;
        }

        if ($filter === self::FILTER_CALLS) {
            return $item['type'] === self::TYPE_CALL
                || ($item['type'] === self::TYPE_FOLLOW_UP && in_array($item['subtype'] ?? null, ['phone_call', 'callback'], true));
        }

        if ($filter === self::FILTER_APPOINTMENTS) {
            return $item['type'] === self::TYPE_APPOINTMENT;
        }

        if ($filter === self::FILTER_INSTALLATIONS) {
            return $item['type'] === self::TYPE_INSTALLATION;
        }

        if ($filter === self::FILTER_FOLLOW_UPS) {
            return $item['type'] === self::TYPE_FOLLOW_UP
                || ($item['type'] === self::TYPE_CALL && ! empty($item['source_reference']['follow_up_id']));
        }

        if ($filter === self::FILTER_COLLECTIONS) {
            return $item['type'] === self::TYPE_COLLECTION;
        }

        return false;
    }

    protected function fetchAppointments(?Carbon $until = null): Collection
    {
        return Appointment::with(['client.primaryOwner', 'users'])
            ->whereIn('status', AppointmentTypes::activeStatuses())
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'archived'))
            ->when($until, fn ($q) => $q->whereDate('appointment_date', '<=', $until->toDateString()))
            ->get();
    }

    protected function formatAppointmentItem(Appointment $apt, Carbon $now): array
    {
        $isInstallation = $apt->appointment_type === AppointmentTypes::INSTALLATION;
        $type = $isInstallation ? self::TYPE_INSTALLATION : self::TYPE_APPOINTMENT;

        $dueAt = null;
        if ($apt->appointment_date) {
            $dueAt = Carbon::parse($apt->appointment_date->toDateString().' '.($apt->appointment_time ?: $this->settings()->workdayStart()), OperationalTime::TIMEZONE);
        }

        $client = $apt->client;
        $attendees = $apt->users;
        $assignedUserIds = $attendees->pluck('id')->all();
        if ($client?->primary_owner_id) {
            $assignedUserIds[] = $client->primary_owner_id;
        }

        $typeLabel = $this->appointmentTypeLabel($apt->appointment_type);
        $status = in_array($apt->status, ['confirmed', 'rescheduled'], true)
            ? __('notify.today_board.statuses.'.$apt->status)
            : null;

        $primaryAction = $isInstallation
            ? [
                'label' => __('notify.today_board.actions.complete_installation'),
                'href' => $this->clientHref($apt->client_id, ['open' => 'complete-installation', 'appointment' => $apt->id]),
                'type' => 'installation',
            ]
            : [
                'label' => __('notify.today_board.actions.record_result'),
                'href' => $this->clientHref($apt->client_id, ['open' => 'appointment-result', 'appointment' => $apt->id]),
                'type' => 'appointment_outcome',
            ];

        return $this->item([
            'id' => "apt-{$apt->id}",
            'type' => $type,
            'subtype' => $apt->appointment_type,
            'client' => $client,
            'client_id' => $apt->client_id,
            'client_name' => $client?->business_name ?: ($apt->branch_name ?: __('notify.today_board.unknown_client')),
            'responsible_staff' => $attendees->isNotEmpty() ? $attendees->pluck('name')->join('، ') : $client?->primaryOwner?->name,
            'assigned_user_ids' => $assignedUserIds,
            'label' => $typeLabel,
            'status_label' => $status,
            'context' => $apt->notes ?: $apt->location,
            'due_at' => $dueAt,
            'due_kind' => 'datetime',
            'primary_action' => $primaryAction,
            'source_reference' => ['model' => 'Appointment', 'id' => $apt->id],
        ], $now);
    }

    protected function fetchOpenFollowUps(?Carbon $until = null): Collection
    {
        return DB::table('follow_ups')
            ->join('clients', 'clients.id', '=', 'follow_ups.client_id')
            ->leftJoin('users as assigned_user', 'assigned_user.id', '=', 'follow_ups.user_id')
            ->leftJoin('users as owner_user', 'owner_user.id', '=', 'clients.primary_owner_id')
            ->whereNull('follow_ups.completed_at')
            ->where('clients.status', '!=', 'archived')
            ->where('clients.stage', '!=', ClientLifecycle::CLOSED)
            ->when($until, fn ($q) => $q->where('follow_ups.follow_up_date_time', '<=', $until->format('Y-m-d H:i:s')))
            ->select(
                'follow_ups.id',
                'follow_ups.client_id',
                'follow_ups.user_id',
                'follow_ups.method',
                'follow_ups.reason',
                'follow_ups.next_action',
                'follow_ups.notes',
                'follow_ups.follow_up_date_time',
                'follow_ups.next_follow_up_date',
                'follow_ups.installation_id',
                'clients.business_name',
                'clients.city_area as client_city_area',
                'clients.primary_owner_id',
                'assigned_user.name as assigned_user_name',
                'owner_user.name as owner_user_name'
            )
            ->get();
    }

    protected function formatFollowUpItem(object $fu, Carbon $now): array
    {
        $isTrial = ! empty($fu->installation_id);
        $isPhone = in_array($fu->method ?? '', ['phone', 'phone_call'], true);

        $dueAt = null;
        if (! empty($fu->follow_up_date_time)) {
            $dueAt = Carbon::parse($fu->follow_up_date_time, OperationalTime::TIMEZONE);
        } elseif (! empty($fu->next_follow_up_date)) {
            $dueAt = Carbon::parse($fu->next_follow_up_date.' '.$this->settings()->workdayStart(), OperationalTime::TIMEZONE);
        }

        $label = match (true) {
            $isTrial => __('notify.today_board.types.trial_follow_up'),
            $isPhone => __('notify.today_board.types.call_follow_up'),
            default => __('notify.today_board.types.follow_up'),
        };

        return $this->item([
            'id' => "fu-{$fu->id}",
            // Scheduled follow-ups outside the free-installation trial are the operational "call" family.
            'type' => $isTrial ? self::TYPE_FOLLOW_UP : self::TYPE_CALL,
            'subtype' => $isTrial ? 'trial_followup' : 'phone_call',
            'client_id' => $fu->client_id,
            'client_name' => $fu->business_name,
            'area' => $fu->client_city_area ?? null,
            'responsible_staff' => $fu->assigned_user_name ?: ($fu->owner_user_name ?? null),
            'assigned_user_ids' => array_values(array_filter([(int) $fu->user_id, (int) $fu->primary_owner_id])),
            'label' => $label,
            'context' => $fu->reason ?: ($fu->next_action ?: $fu->notes),
            'due_at' => $dueAt,
            'due_kind' => 'datetime',
            'primary_action' => [
                'label' => __('notify.today_board.actions.complete_follow_up'),
                'href' => $this->clientHref($fu->client_id, ['open' => 'follow-up', 'follow_up' => $fu->id]),
                'type' => 'follow_up',
            ],
            'source_reference' => ['model' => 'FollowUp', 'id' => $fu->id, 'follow_up_id' => $fu->id],
        ], $now);
    }

    protected function fetchPendingReviews(): Collection
    {
        return ClientReviewItem::with(['client.primaryOwner'])
            ->where('status', ClientReviewItem::STATUS_PENDING)
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'archived'))
            ->get();
    }

    protected function formatReviewItem(ClientReviewItem $rev, Carbon $now): array
    {
        $client = $rev->client;
        $typeKey = 'notify.client_hub.review.types.'.$rev->type;
        $typeLabel = trans()->has($typeKey) ? __($typeKey) : null;

        return $this->item([
            'id' => "rev-{$rev->id}",
            'type' => self::TYPE_REVIEW,
            'subtype' => 'client_review',
            'client' => $client,
            'client_id' => $rev->client_id,
            'client_name' => $client?->business_name ?: __('notify.today_board.unknown_client'),
            'responsible_staff' => $client?->primaryOwner?->name,
            'assigned_user_ids' => array_values(array_filter([$client?->primary_owner_id])),
            'label' => __('notify.today_board.types.review'),
            'context' => collect([$typeLabel, $rev->note])->filter()->join(' · '),
            'due_at' => $rev->created_at ? OperationalTime::inZone(Carbon::parse($rev->created_at)) : $now->copy(),
            'due_kind' => 'since',
            'primary_action' => [
                'label' => __('notify.today_board.actions.open_review'),
                'href' => $this->clientHref($rev->client_id, [], 'review'),
                'type' => 'review',
            ],
            'source_reference' => ['model' => 'ClientReviewItem', 'id' => $rev->id],
        ], $now);
    }

    /** Prospects still waiting for a first contact: no open future follow-up and no pending review. */
    protected function activeContactQuery(Carbon $now): Builder
    {
        return Client::query()
            ->where('status', '!=', 'archived')
            ->whereIn('stage', [ClientLifecycle::PROSPECT, ClientLifecycle::CONTACTING])
            ->whereNotIn('id', DB::table('follow_ups')
                ->select('client_id')
                ->whereNull('completed_at')
                ->where('follow_up_date_time', '>', $now->format('Y-m-d H:i:s')))
            ->whereNotIn('id', DB::table('client_review_items')
                ->select('client_id')
                ->where('status', ClientReviewItem::STATUS_PENDING));
    }

    protected function readyToContactCount(User $user, Carbon $now, string $scope): int
    {
        return $this->activeContactQuery($now)
            ->when($scope === 'my', fn ($q) => $q->where('primary_owner_id', $user->id))
            ->count();
    }

    protected function formatActiveContactItem(Client $client, Carbon $now): array
    {
        $contact = $client->preferredOperationalContact();
        $contactDetail = ! empty($contact['name'])
            ? $contact['name'].(! empty($contact['phone']) ? ' · '.$contact['phone'] : '')
            : ($contact['phone'] ?? null);

        return $this->item([
            'id' => "contact-{$client->id}",
            'type' => self::TYPE_CALL,
            'subtype' => 'active_contact',
            'client' => $client,
            'client_id' => $client->id,
            'client_name' => $client->business_name,
            'responsible_staff' => $client->primaryOwner?->name,
            'assigned_user_ids' => array_values(array_filter([$client->primary_owner_id])),
            'label' => __('notify.today_board.types.first_contact'),
            'context' => $contactDetail ?: $client->phone,
            'due_at' => null,
            'due_kind' => 'none',
            'primary_action' => [
                'label' => __('notify.today_board.actions.record_call'),
                'href' => $this->clientHref($client->id, ['open' => 'record-call']),
                'type' => 'call',
            ],
            'source_reference' => ['model' => 'Client', 'id' => $client->id],
        ], $now);
    }

    /**
     * One collection item per client, aggregated from ReceivableService invoice projections
     * (the P6 authority; the same per-client aggregation as Collections due). Pending receipt
     * confirmations have no financial effect: the amount due is unchanged, but a client whose
     * pending receipts already cover the amount is waiting for owner confirmation, not for action.
     */
    protected function collectionItems(User $user, Carbon $now, ?Carbon $until): Collection
    {
        $rows = $this->receivableService->outstandingInvoices([], $now->copy()->startOfDay());
        if ($until) {
            $rows = $rows->filter(fn (array $row) => $row['invoice']->due_date === null
                || $row['invoice']->due_date->toDateString() <= $until->toDateString());
        }

        if ($rows->isEmpty()) {
            return collect();
        }

        $byClient = $rows->groupBy(fn (array $row) => $row['invoice']->client_id);
        $clientIds = $byClient->keys()->all();

        $pendingByClient = PaymentReceiptConfirmation::query()
            ->pending()
            ->whereIn('client_id', $clientIds)
            ->groupBy('client_id')
            ->selectRaw('client_id, SUM(amount_minor) as pending_minor')
            ->pluck('pending_minor', 'client_id');

        $ownerIds = $rows->map(fn (array $row) => $row['invoice']->client?->primary_owner_id)->filter()->unique()->all();
        $ownerNames = $ownerIds === [] ? collect() : User::whereIn('id', $ownerIds)->pluck('name', 'id');

        $canRecord = Permissions::allows($user, Permissions::RECORD_PAYMENT);
        $canSubmit = ! $canRecord && Permissions::allows($user, Permissions::SUBMIT_PAYMENT_RECEIPT);

        return $byClient->map(function (Collection $group, $clientId) use ($now, $pendingByClient, $ownerNames, $canRecord, $canSubmit) {
            $client = $group->first()['invoice']->client;
            if (! $client || $client->status === 'archived') {
                return null;
            }

            $dueMinor = (int) $group->sum(fn (array $row) => $row['projection']['outstanding_minor']);
            $pendingMinor = (int) ($pendingByClient[$clientId] ?? 0);
            if ($dueMinor <= 0 || ($pendingMinor > 0 && $pendingMinor >= $dueMinor)) {
                return null;
            }

            $earliestDue = $group->map(fn (array $row) => $row['invoice']->due_date)->filter()->sort()->first();
            $dueAt = $earliestDue
                ? Carbon::parse($earliestDue->toDateString(), OperationalTime::TIMEZONE)
                : $now->copy()->startOfDay();

            $primaryAction = null;
            if ($canRecord || $canSubmit) {
                $primaryAction = [
                    'label' => $canRecord
                        ? __('notify.client_workspace.action_record_payment')
                        : __('notify.payment_receipts.action_payment_received'),
                    'href' => $this->clientHref((int) $clientId, ['open' => 'record-payment']),
                    'type' => $canRecord ? 'payment' : 'payment_receipt',
                ];
            }

            return $this->item([
                'id' => "col-{$clientId}",
                'type' => self::TYPE_COLLECTION,
                'subtype' => 'client_receivable',
                'client' => $client,
                'client_id' => (int) $clientId,
                'client_name' => $client->business_name,
                'responsible_staff' => $client->primary_owner_id ? ($ownerNames[$client->primary_owner_id] ?? null) : null,
                'assigned_user_ids' => array_values(array_filter([$client->primary_owner_id])),
                'label' => __('notify.today_board.types.collection'),
                'context' => null,
                'amount_minor' => $dueMinor,
                'pending_minor' => $pendingMinor,
                'due_at' => $dueAt,
                'due_kind' => 'date',
                'primary_action' => $primaryAction,
                'source_reference' => [
                    'model' => 'Client',
                    'id' => (int) $clientId,
                    'invoice_ids' => $group->map(fn (array $row) => $row['invoice']->id)->values()->all(),
                ],
            ], $now);
        })->filter()->values();
    }

    protected function canViewCollections(User $user): bool
    {
        return Permissions::allows($user, Permissions::VIEW_COLLECTIONS_DUE);
    }

    /** Normalise a work item and attach its timing (all times in Asia/Amman). */
    protected function item(array $data, Carbon $now): array
    {
        $client = $data['client'] ?? null;
        unset($data['client']);

        $timing = $this->timing($data['due_at'], $data['due_kind'], $data['type'], $now);
        $clientHref = $this->clientHref($data['client_id']);

        return array_merge([
            'area' => $client?->city_area ?: ($client?->area ?: ($client?->city ?: null)),
            'status_label' => null,
            'amount_minor' => null,
            'pending_minor' => 0,
        ], $data, [
            'assigned_user_ids' => array_values(array_unique(array_map('intval', $data['assigned_user_ids'] ?? []))),
            'client_href' => $clientHref,
            'timing' => $timing,
            'sort_at' => $timing['sort_at'],
            'due_formatted' => $timing['exact'] ?? $timing['text'],
            'priority' => 'today',
            'secondary_actions' => [[
                'label' => __('notify.today_board.actions.open_client'),
                'href' => $clientHref,
                'icon' => 'building',
            ]],
        ]);
    }

    /**
     * Timing states: overdue · now · soon (within the Next window) · scheduled · due_today (date-only)
     * · waiting (review raised today) · ready (undated first contact).
     */
    protected function timing(?Carbon $dueAt, string $kind, string $type, Carbon $now): array
    {
        if ($dueAt === null || $kind === 'none') {
            return ['state' => 'ready', 'text' => __('notify.today_board.timing.ready'), 'exact' => null, 'datetime' => null, 'sort_at' => PHP_INT_MAX];
        }

        $base = ['datetime' => $dueAt->toIso8601String(), 'sort_at' => $dueAt->getTimestamp()];

        if ($kind === 'since') {
            $since = OperationalTime::duration(OperationalTime::minutesBetween($dueAt, $now));

            return $base + [
                'state' => $dueAt->isSameDay($now) ? 'waiting' : 'overdue',
                'text' => __('notify.today_board.timing.waiting_since', ['duration' => $since]),
                'exact' => OperationalTime::dayAndClock($dueAt, $now),
            ];
        }

        if ($kind === 'date') {
            $day = $dueAt->copy()->startOfDay();
            $today = $now->copy()->startOfDay();

            if ($day->lt($today)) {
                return $base + [
                    'state' => 'overdue',
                    'text' => __('notify.today_board.timing.late', ['duration' => OperationalTime::duration(OperationalTime::minutesBetween($day, $today))]),
                    'exact' => __('notify.today_board.timing.due_on', ['day' => OperationalTime::day($day, $now)]),
                ];
            }

            if ($day->equalTo($today)) {
                return [
                    'state' => 'due_today',
                    'text' => __('notify.today_board.timing.due_today'),
                    'exact' => null,
                    'datetime' => $day->toIso8601String(),
                    'sort_at' => $this->settings()->endOf($day)->getTimestamp(),
                ];
            }

            return $base + [
                'state' => 'scheduled',
                'text' => __('notify.today_board.timing.due_on', ['day' => OperationalTime::day($day, $now)]),
                'exact' => null,
            ];
        }

        $minutesLate = OperationalTime::minutesBetween($dueAt, $now);
        $grace = $this->inProgressMinutes($type);
        $exact = OperationalTime::dayAndClock($dueAt, $now);

        if ($minutesLate > $grace || ($minutesLate > 0 && ! $dueAt->isSameDay($now))) {
            return $base + [
                'state' => 'overdue',
                'text' => __('notify.today_board.timing.late', ['duration' => OperationalTime::duration($minutesLate)]),
                'exact' => $exact,
            ];
        }

        if ($dueAt->lte($now)) {
            return $base + ['state' => 'now', 'text' => __('notify.today_board.timing.now'), 'exact' => $exact];
        }

        $minutesUntil = (int) ceil(($dueAt->getTimestamp() - $now->getTimestamp()) / 60);
        if ($dueAt->isSameDay($now) && $minutesUntil <= self::NEXT_WINDOW_MINUTES) {
            return $base + [
                'state' => 'soon',
                'text' => __('notify.today_board.timing.in', ['duration' => OperationalTime::duration($minutesUntil)]),
                'exact' => $exact,
            ];
        }

        return $base + ['state' => 'scheduled', 'text' => $exact, 'exact' => $exact];
    }

    /**
     * Client Workspace link using the canonical P10 `?open=` sheet keys. `from`/`scope` let the
     * workspace send the user back to the same Today view after the action completes.
     */
    protected function clientHref(int $clientId, array $query = [], ?string $fragment = null): string
    {
        $params = array_merge(['client' => $clientId], $query, array_filter([
            'from' => $this->returnContext['from'],
            'scope' => $this->returnContext['scope'] === 'my' ? 'my' : null,
        ]));

        return route('clients.show', $params).($fragment ? '#'.$fragment : '');
    }

    protected function appointmentTypeLabel(?string $type): string
    {
        $key = 'notify.today_board.appointment_types.'.$type;

        return $type && trans()->has($key) ? __($key) : AppointmentTypes::label($type);
    }

    /** How long timed work stays "now" after it starts (Settings → Operations). */
    protected function inProgressMinutes(string $type): int
    {
        return match ($type) {
            self::TYPE_APPOINTMENT => $this->settings()->appointmentMinutes(),
            self::TYPE_INSTALLATION => $this->settings()->installationMinutes(),
            default => 0,
        };
    }

    protected function settings(): OperationalSettings
    {
        return $this->operationalSettings ??= OperationalSettings::current();
    }

    protected function businessTime(?Carbon $time = null): Carbon
    {
        if ($time !== null) {
            return $time->copy()->timezone(OperationalTime::TIMEZONE);
        }

        return OperationalTime::now();
    }
}
