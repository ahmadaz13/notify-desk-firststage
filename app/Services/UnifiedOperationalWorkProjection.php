<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientReviewItem;
use App\Models\User;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use App\Support\FinancialPermissions;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    public function __construct(
        protected ReceivableService $receivableService
    ) {}

    /**
     * Build the projection for Today page.
     * Sections:
     * 1. Overdue: due_at < now
     * 2. Next: due now or within next 2 hours, plus nearest upcoming and undated prospects needing first contact
     * 3. Later Today: remaining work due today after the Next window
     */
    public function today(User $user, ?Carbon $now = null): array
    {
        $now = $this->businessTime($now);
        $nextWindowEnd = $now->copy()->addHours(2);

        $allItems = $this->projectAll($user, $now);

        // Filter items relevant for Today:
        // Overdue items, items due today, and undated items (actionable today). Future items (after today) are excluded.
        $overdue = collect();
        $next = collect();
        $laterToday = collect();

        foreach ($allItems as $item) {
            $dueAt = $item['due_at'];

            if ($dueAt === null) {
                // Undated prospect needing first contact belongs in Next
                $next->push($item);
                continue;
            }

            if ($dueAt->lt($now)) {
                // Due time has passed -> Overdue
                $item['priority'] = 'overdue';
                $overdue->push($item);
            } elseif ($dueAt->isSameDay($now)) {
                if ($dueAt->lte($nextWindowEnd)) {
                    $item['priority'] = 'next';
                    $next->push($item);
                } else {
                    $item['priority'] = 'later_today';
                    $laterToday->push($item);
                }
            }
            // Future items (due after today) do not belong on Today
        }

        // If Next is empty but we have items in laterToday, pull the nearest one into Next
        if ($next->isEmpty() && $laterToday->isNotEmpty()) {
            $firstLater = $laterToday->shift();
            $firstLater['priority'] = 'next';
            $next->push($firstLater);
        }

        // Sort Overdue: oldest due first
        $overdue = $overdue->sortBy(fn ($i) => $i['due_at']->timestamp)->values();

        // Sort Next: due time first, undated by created timestamp or default to end
        $next = $next->sortBy(function ($i) {
            return $i['due_at'] ? $i['due_at']->timestamp : PHP_INT_MAX;
        })->values();

        // Sort Later Today: due time first
        $laterToday = $laterToday->sortBy(fn ($i) => $i['due_at']->timestamp)->values();

        return [
            'overdue' => $overdue,
            'next' => $next,
            'later_today' => $laterToday,
            'counts' => [
                'overdue' => $overdue->count(),
                'next' => $next->count(),
                'later_today' => $laterToday->count(),
                'total' => $overdue->count() + $next->count() + $laterToday->count(),
            ],
        ];
    }

    /**
     * Build the projection for Work page.
     * Filters: all, calls, appointments, installations, follow_ups, collections
     * Groups:
     * 1. Overdue: due_at < now
     * 2. Today: due_at is today and >= now, plus undated items
     * 3. Upcoming: due_at > end of today
     */
    public function work(User $user, ?string $filter = self::FILTER_ALL, ?Carbon $now = null): array
    {
        $now = $this->businessTime($now);
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

        $allItems = $this->projectAll($user, $now);

        // Filter items
        $filteredItems = $allItems->filter(function ($item) use ($filter) {
            return $this->itemMatchesFilter($item, $filter);
        })->values();

        $overdue = collect();
        $today = collect();
        $upcoming = collect();

        foreach ($filteredItems as $item) {
            $dueAt = $item['due_at'];

            if ($dueAt === null) {
                // Undated items belong under Today group
                $today->push($item);
                continue;
            }

            if ($dueAt->lt($now)) {
                $item['priority'] = 'overdue';
                $overdue->push($item);
            } elseif ($dueAt->isSameDay($now)) {
                $item['priority'] = 'today';
                $today->push($item);
            } else {
                $item['priority'] = 'upcoming';
                $upcoming->push($item);
            }
        }

        // Sort Overdue: oldest due first
        $overdue = $overdue->sortBy(fn ($i) => $i['due_at']->timestamp)->values();

        // Sort Today: due time first, undated last
        $today = $today->sortBy(function ($i) {
            return $i['due_at'] ? $i['due_at']->timestamp : PHP_INT_MAX;
        })->values();

        // Sort Upcoming: earliest due first
        $upcoming = $upcoming->sortBy(fn ($i) => $i['due_at']->timestamp)->values();

        // Count per filter for UI tabs
        $filterCounts = [];
        foreach ($validFilters as $f) {
            if ($f === self::FILTER_COLLECTIONS && ! $this->canViewCollections($user)) {
                $filterCounts[$f] = 0;
                continue;
            }
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
     * Gather all open operational work across authoritative sources.
     */
    public function projectAll(User $user, ?Carbon $now = null): Collection
    {
        $now = $this->businessTime($now);
        $items = collect();

        // 1. Appointments & Installations
        $appointments = $this->fetchAppointments();
        foreach ($appointments as $apt) {
            $items->push($this->formatAppointmentItem($apt, $now));
        }

        // 2. Open Follow-ups (excluding completed follow-ups)
        $followUps = $this->fetchOpenFollowUps();
        foreach ($followUps as $fu) {
            $items->push($this->formatFollowUpItem($fu, $now));
        }

        // 3. Review-Required Items
        $reviews = $this->fetchPendingReviews();
        foreach ($reviews as $rev) {
            $items->push($this->formatReviewItem($rev, $now));
        }

        // 4. Undated Calls / Active Contact Queue
        $activeContactClients = $this->fetchActiveContactClients($user, $now);
        foreach ($activeContactClients as $client) {
            $items->push($this->formatActiveContactItem($client));
        }

        // 5. Collections (Permission-gated to authorized users only)
        if ($this->canViewCollections($user)) {
            $collections = $this->fetchCollections($now);
            foreach ($collections as $col) {
                $items->push($this->formatCollectionItem($col, $now));
            }
        }

        return $items;
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

    protected function fetchAppointments(): Collection
    {
        return Appointment::with(['client', 'users'])
            ->whereIn('status', AppointmentTypes::activeStatuses())
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'archived'))
            ->get();
    }

    protected function formatAppointmentItem(Appointment $apt, Carbon $now): array
    {
        $isInstallation = $apt->appointment_type === AppointmentTypes::INSTALLATION;
        $type = $isInstallation ? self::TYPE_INSTALLATION : self::TYPE_APPOINTMENT;

        $dueAt = null;
        if ($apt->appointment_date) {
            $timeStr = $apt->appointment_time ?: '09:00:00';
            $dueAt = Carbon::parse($apt->appointment_date->toDateString() . ' ' . $timeStr, 'Asia/Amman');
        }

        $client = $apt->client;
        $clientName = $client ? $client->business_name : ($apt->branch_name ?: 'عميل');

        $timeFormatted = $dueAt ? $dueAt->format('g:i A') : '';
        $dateFormatted = $dueAt ? $dueAt->format('Y-m-d') : '';

        $label = $isInstallation
            ? (__('notify.work.installation_label', ['time' => $timeFormatted]) ?: "تركيب — {$timeFormatted}")
            : (__('notify.work.appointment_label', ['time' => $timeFormatted]) ?: "موعد — {$timeFormatted}");

        $contextParts = array_values(array_filter([
            $apt->notes ?: ($apt->location ?: ($apt->appointment_type_label ?? $apt->appointment_type)),
            $apt->users->isNotEmpty()
                ? $apt->users->map(fn (User $attendee) => '👤 ' . $attendee->name)->join(' ')
                : null,
        ]));
        $context = implode(' | ', $contextParts);

        $primaryAction = $isInstallation
            ? [
                'label' => __('notify.actions.complete_installation') ?: 'إكمال التركيب',
                'href' => $client ? route('clients.show', $client->id) . '#installation' : '#',
                'type' => 'installation',
            ]
            : [
                'label' => __('notify.actions.record_outcome') ?: 'تسجيل النتيجة',
                'href' => $client ? route('clients.show', $client->id) . '#appointments' : '#',
                'type' => 'appointment_outcome',
            ];

        return [
            'id' => "apt-{$apt->id}",
            'type' => $type,
            'subtype' => $apt->appointment_type,
            'client_id' => $apt->client_id,
            'client_name' => $clientName,
            'label' => $label,
            'context' => $context,
            'due_at' => $dueAt,
            'due_formatted' => $timeFormatted ? "{$dateFormatted} {$timeFormatted}" : $dateFormatted,
            'priority' => 'today',
            'primary_action' => $primaryAction,
            'secondary_actions' => $this->clientQuickActions($client),
            'source_reference' => [
                'model' => 'Appointment',
                'id' => $apt->id,
            ],
        ];
    }

    protected function fetchOpenFollowUps(): Collection
    {
        return DB::table('follow_ups')
            ->join('clients', 'clients.id', '=', 'follow_ups.client_id')
            ->whereNull('follow_ups.completed_at')
            ->where('clients.status', '!=', 'archived')
            ->where('clients.stage', '!=', ClientLifecycle::CLOSED)
            ->select(
                'follow_ups.*',
                'clients.business_name',
                'clients.phone as client_phone',
                'clients.stage as client_stage'
            )
            ->get();
    }

    protected function formatFollowUpItem(object $fu, Carbon $now): array
    {
        $isTrial = ! empty($fu->installation_id);
        $isPhone = ($fu->method ?? '') === 'phone_call' || empty($fu->installation_id);

        $type = $isTrial ? self::TYPE_FOLLOW_UP : self::TYPE_CALL;
        $subtype = $isTrial ? 'trial_followup' : ($isPhone ? 'phone_call' : 'follow_up');

        $dueAt = null;
        if (! empty($fu->follow_up_date_time)) {
            $dueAt = Carbon::parse($fu->follow_up_date_time, 'Asia/Amman');
        } elseif (! empty($fu->next_follow_up_date)) {
            $dueAt = Carbon::parse($fu->next_follow_up_date . ' 09:00:00', 'Asia/Amman');
        }

        $timeFormatted = $dueAt ? $dueAt->format('g:i A') : '';
        $dateFormatted = $dueAt ? $dueAt->format('Y-m-d') : '';

        $label = $isTrial
            ? (__('notify.work.trial_followup_label') ?: 'متابعة تجربة مجانية')
            : (__('notify.work.callback_label') ?: 'متابعة هاتفية / اتصال');

        $context = $fu->reason ?: ($fu->notes ?: ($fu->next_action ?: 'متابعة مجدولة'));

        $primaryAction = [
            'label' => __('notify.actions.record_follow_up') ?: 'تسجيل المتابعة',
            'href' => route('clients.show', $fu->client_id) . '#follow-up',
            'type' => 'follow_up',
        ];

        return [
            'id' => "fu-{$fu->id}",
            'type' => $type,
            'subtype' => $subtype,
            'client_id' => $fu->client_id,
            'client_name' => $fu->business_name,
            'label' => $label,
            'context' => $context,
            'due_at' => $dueAt,
            'due_formatted' => $timeFormatted ? "{$dateFormatted} {$timeFormatted}" : $dateFormatted,
            'priority' => 'today',
            'primary_action' => $primaryAction,
            'secondary_actions' => [
                [
                    'label' => __('notify.actions.call') ?: 'اتصال',
                    'href' => $fu->client_phone ? 'tel:' . $fu->client_phone : '#',
                    'icon' => 'phone',
                ],
                [
                    'label' => __('notify.actions.open_client') ?: 'فتح ملف العميل',
                    'href' => route('clients.show', $fu->client_id),
                    'icon' => 'building',
                ],
            ],
            'source_reference' => [
                'model' => 'FollowUp',
                'id' => $fu->id,
                'follow_up_id' => $fu->id,
            ],
        ];
    }

    protected function fetchPendingReviews(): Collection
    {
        return ClientReviewItem::with('client')
            ->where('status', ClientReviewItem::STATUS_PENDING)
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'archived'))
            ->get();
    }

    protected function formatReviewItem(ClientReviewItem $rev, Carbon $now): array
    {
        $dueAt = $rev->created_at ? Carbon::parse($rev->created_at, 'Asia/Amman') : $now;
        $client = $rev->client;
        $clientName = $client ? $client->business_name : 'عميل';

        $label = __('notify.work.review_required') ?: 'مراجعة مطلوبة';
        $context = $rev->note ?: 'مراجعة حالة العميل';

        return [
            'id' => "rev-{$rev->id}",
            'type' => self::TYPE_REVIEW,
            'subtype' => 'client_review',
            'client_id' => $rev->client_id,
            'client_name' => $clientName,
            'label' => $label,
            'context' => $context,
            'due_at' => $dueAt,
            'due_formatted' => $dueAt->format('Y-m-d g:i A'),
            'priority' => 'today',
            'primary_action' => [
                'label' => __('notify.actions.review') ?: 'مراجعة',
                'href' => route('clients.show', $rev->client_id) . '#review',
                'type' => 'review',
            ],
            'secondary_actions' => $this->clientQuickActions($client),
            'source_reference' => [
                'model' => 'ClientReviewItem',
                'id' => $rev->id,
            ],
        ];
    }

    protected function fetchActiveContactClients(User $user, Carbon $now): Collection
    {
        $futureFollowClientIds = DB::table('follow_ups')
            ->whereNull('completed_at')
            ->where('follow_up_date_time', '>', $now)
            ->pluck('client_id')
            ->all();

        $pendingReviewClientIds = ClientReviewItem::where('status', ClientReviewItem::STATUS_PENDING)
            ->pluck('client_id')
            ->all();

        return Client::query()
            ->where('status', '!=', 'archived')
            ->whereIn('stage', [ClientLifecycle::PROSPECT, ClientLifecycle::CONTACTING])
            ->whereNotIn('id', array_unique(array_merge($futureFollowClientIds, $pendingReviewClientIds)))
            ->orderBy('created_at')
            ->limit(20)
            ->get();
    }

    protected function formatActiveContactItem(Client $client): array
    {
        $contact = $client->preferredOperationalContact();
        $contactDetail = !empty($contact['name']) ? $contact['name'] . (!empty($contact['phone']) ? ' · ' . $contact['phone'] : '') : ($contact['phone'] ?? null);
        $context = $contactDetail ?: ($client->phone ?: ($client->city_area ?: 'عميل محتمل'));

        $label = __('notify.work.call_prospect', ['name' => $client->business_name]) ?: "اتصال بـ {$client->business_name}";

        return [
            'id' => "contact-{$client->id}",
            'type' => self::TYPE_CALL,
            'subtype' => 'active_contact',
            'client_id' => $client->id,
            'client_name' => $client->business_name,
            'label' => $label,
            'context' => $context,
            'due_at' => null, // Undated new prospect
            'due_formatted' => __('notify.common.undated_actionable') ?: 'جاهز للتواصل',
            'priority' => 'today',
            'primary_action' => [
                'label' => __('notify.actions.record_call') ?: 'تسجيل اتصال',
                'href' => route('clients.show', $client->id) . '#call',
                'type' => 'call',
            ],
            'secondary_actions' => $this->clientQuickActions($client),
            'source_reference' => [
                'model' => 'Client',
                'id' => $client->id,
            ],
        ];
    }

    protected function fetchCollections(Carbon $now): Collection
    {
        // Safe aggregate read query via ReceivableService - zero N+1
        return $this->receivableService->outstandingInvoices([], $now);
    }

    protected function formatCollectionItem(array $invoiceData, Carbon $now): array
    {
        $invoice = $invoiceData['invoice'];
        $projection = $invoiceData['projection'];

        $dueAt = $invoice->due_date
            ? Carbon::parse($invoice->due_date->toDateString() . ' 17:00:00', 'Asia/Amman')
            : $now;

        $client = $invoice->client;
        $clientName = $client ? $client->business_name : 'عميل';

        $amountFormatted = $projection['outstanding'] ?? '';
        $label = __('notify.work.collection_due', ['amount' => $amountFormatted]) ?: "تحصيل مستحق — {$amountFormatted}";
        $context = __('notify.work.collection_context', [
            'invoice' => $invoice->invoice_number,
            'date' => $invoice->due_date ? $invoice->due_date->toDateString() : __('notify.common.unspecified'),
        ]);

        return [
            'id' => "col-{$invoice->id}",
            'type' => self::TYPE_COLLECTION,
            'subtype' => 'receivable_invoice',
            'client_id' => $invoice->client_id,
            'client_name' => $clientName,
            'label' => $label,
            'context' => $context,
            'due_at' => $dueAt,
            'due_formatted' => $invoice->due_date ? $invoice->due_date->toDateString() : '',
            'priority' => $projection['is_overdue'] ? 'overdue' : 'today',
            'primary_action' => [
                'label' => __('notify.actions.record_payment') ?: 'تسجيل دفعة',
                'href' => route('clients.show', $invoice->client_id) . '#payment',
                'type' => 'payment',
            ],
            'secondary_actions' => $this->clientQuickActions($client),
            'source_reference' => [
                'model' => 'Invoice',
                'id' => $invoice->id,
                'invoice_id' => $invoice->id,
            ],
        ];
    }

    protected function canViewCollections(User $user): bool
    {
        return FinancialPermissions::allows($user, FinancialPermissions::RECORD_PAYMENT)
            || FinancialPermissions::allows($user, FinancialPermissions::VIEW_FINANCIAL_REPORTS);
    }

    protected function clientQuickActions(?Client $client): array
    {
        if (! $client) {
            return [];
        }

        $actions = [];
        if ($client->phone) {
            $actions[] = [
                'label' => __('notify.actions.call') ?: 'اتصال',
                'href' => 'tel:' . $client->phone,
                'icon' => 'phone',
            ];
            $cleanPhone = preg_replace('/[^0-9]/', '', $client->phone);
            $actions[] = [
                'label' => 'WhatsApp',
                'href' => 'https://wa.me/' . $cleanPhone,
                'icon' => 'message-circle',
            ];
        }

        $actions[] = [
            'label' => __('notify.actions.open_client') ?: 'فتح ملف العميل',
            'href' => route('clients.show', $client->id),
            'icon' => 'building',
        ];

        return $actions;
    }

    protected function businessTime(?Carbon $time = null): Carbon
    {
        if ($time !== null) {
            return $time->copy()->timezone('Asia/Amman');
        }

        return Carbon::now('Asia/Amman');
    }
}
