<?php

namespace App\ViewModels;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PaymentReceiptConfirmation;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ReceivableService;
use App\Services\ReferenceDataService;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use App\Support\ClientSegments;
use App\Support\Permissions;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Client list rows (P10, §5, §21). One operational signal per row, chosen by the client's segment,
 * from data loaded in a fixed number of batched queries for the current page (no per-row queries).
 */
class ClientListViewModel
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $segments
     * @param  array<string, mixed>  $filterOptions
     */
    public function __construct(
        public readonly LengthAwarePaginator $clients,
        public readonly array $rows,
        public readonly string $segment,
        public readonly array $segments,
        public readonly array $filters,
        public readonly array $filterOptions,
        public readonly bool $hasActiveFilters,
    ) {
    }

    /**
     * @param  array<string, int>  $segmentCounts
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $filterOptions
     */
    public static function make(
        LengthAwarePaginator $clients,
        string $segment,
        array $segmentCounts,
        array $filters,
        array $filterOptions,
        User $user,
        ReceivableService $receivables,
    ): self {
        $page = $clients->getCollection();
        $signals = self::loadSignals($page->pluck('id')->all(), $receivables);
        $signals['category_labels'] = app(ReferenceDataService::class)->labels(ReferenceDataService::CLIENT_CATEGORY);
        $can = [
            'record_payment' => Permissions::allows($user, Permissions::RECORD_PAYMENT),
            'submit_receipt' => Permissions::allows($user, Permissions::SUBMIT_PAYMENT_RECEIPT),
            'start_subscription' => Permissions::allows($user, Permissions::START_PAID_SUBSCRIPTION),
        ];

        $rows = $page->map(fn (Client $client) => self::row($client, $signals, $can))->values()->all();

        $segments = collect(ClientSegments::SEGMENTS)->map(fn (string $key) => [
            'key' => $key,
            'label' => __('notify.client_hub.segments.'.$key),
            'count' => $key === ClientSegments::ALL ? array_sum($segmentCounts) : ($segmentCounts[$key] ?? 0),
            'active' => $key === $segment,
            'href' => route('clients.index', ['view' => $key]),
        ])->all();

        $hasActiveFilters = collect($filters)->except('view')->filter(fn ($value) => filled($value))->isNotEmpty();

        return new self($clients, $rows, $segment, $segments, $filters, $filterOptions, $hasActiveFilters);
    }

    /** @return array<string, Collection> */
    private static function loadSignals(array $clientIds, ReceivableService $receivables): array
    {
        if ($clientIds === []) {
            return ['follow_ups' => collect(), 'appointments' => collect(), 'subscriptions' => collect(), 'money' => collect(), 'pending' => collect()];
        }

        $followUps = DB::table('follow_ups')
            ->whereIn('client_id', $clientIds)
            ->whereNull('completed_at')
            ->orderBy('follow_up_date_time')
            ->get(['client_id', 'follow_up_date_time', 'installation_id'])
            ->groupBy('client_id')
            ->map->first();

        $appointments = Appointment::query()
            ->whereIn('client_id', $clientIds)
            ->whereIn('status', AppointmentTypes::activeStatuses())
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->get(['id', 'client_id', 'appointment_date', 'appointment_time', 'appointment_type'])
            ->groupBy('client_id')
            ->map->first();

        $subscriptions = Subscription::query()
            ->with(['systems', 'plan.product'])
            ->whereIn('client_id', $clientIds)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('client_id');

        $invoices = Invoice::query()->whereIn('client_id', $clientIds)->get();
        $projections = $receivables->invoiceProjections($invoices);
        $money = $invoices->groupBy('client_id')->map(function (Collection $clientInvoices) use ($projections) {
            $open = $clientInvoices->filter(fn (Invoice $invoice) => ($projections[$invoice->id]['outstanding_minor'] ?? 0) > 0);

            return [
                'due_minor' => (int) $open->sum(fn (Invoice $invoice) => $projections[$invoice->id]['outstanding_minor']),
                'overdue_minor' => (int) $open->filter(fn (Invoice $invoice) => $projections[$invoice->id]['is_overdue'])
                    ->sum(fn (Invoice $invoice) => $projections[$invoice->id]['outstanding_minor']),
                'next_due_date' => $open->pluck('due_date')->filter()->sort()->first(),
            ];
        });

        $pending = PaymentReceiptConfirmation::query()
            ->pending()
            ->whereIn('client_id', $clientIds)
            ->select('client_id', DB::raw('COUNT(*) as pending_count'))
            ->groupBy('client_id')
            ->pluck('pending_count', 'client_id');

        return [
            'follow_ups' => $followUps,
            'appointments' => $appointments,
            'subscriptions' => $subscriptions,
            'money' => $money,
            'pending' => $pending,
        ];
    }

    /**
     * @param  array<string, Collection>  $signals
     * @param  array<string, bool>  $can
     * @return array<string, mixed>
     */
    private static function row(Client $client, array $signals, array $can): array
    {
        $stage = ClientLifecycle::normalizeStage($client->stage);
        $segment = ClientSegments::forStage($stage);
        $phone = $client->phone ?: $client->business_phone;
        $subscriptions = $signals['subscriptions']->get($client->id, collect());
        $money = $signals['money']->get($client->id, ['due_minor' => 0, 'overdue_minor' => 0, 'next_due_date' => null]);
        $href = route('clients.show', $client->id);

        $row = [
            'id' => $client->id,
            'initial' => mb_substr((string) $client->business_name, 0, 1),
            'business_name' => (string) $client->business_name,
            'category' => ClientPresenter::referenceLabel($signals['category_labels'] ?? [], $client->business_category ?: $client->business_type),
            'area' => $client->city_area ?: $client->city,
            'phone' => $phone,
            'phone_href' => $phone ? 'tel:'.preg_replace('/[^0-9+]/', '', $phone) : null,
            'stage' => $stage,
            'stage_label' => ClientPresenter::stageLabel($stage),
            'stage_tone' => ClientPresenter::stageTone($stage),
            'stage_icon' => ClientPresenter::stageIcon($stage),
            'segment' => $segment,
            'href' => $href,
            'signal' => null,
            'action' => null,
        ];

        switch ($segment) {
            case ClientSegments::SUBSCRIBERS:
                $row['signal'] = self::subscriberSignal($subscriptions, $money, (int) $signals['pending']->get($client->id, 0));
                if ($money['due_minor'] > 0 && ($can['record_payment'] || $can['submit_receipt'])) {
                    $row['action'] = [
                        'label' => $can['record_payment'] ? __('notify.client_hub.actions.record_payment') : __('notify.client_hub.actions.payment_received'),
                        'href' => $href.'?open=record-payment',
                        'icon' => 'wallet',
                    ];
                }
                break;

            case ClientSegments::RENEWAL:
                $last = $subscriptions->first();
                $ended = $last?->ended_at ?? $last?->cancelled_at ?? $last?->current_period_end;
                $row['signal'] = [
                    'tone' => 'warning',
                    'icon' => 'calendar',
                    'text' => $ended
                        ? __('notify.client_hub.signals.ended_on', ['date' => ClientPresenter::date($ended)])
                        : __('notify.client_hub.signals.renewal_needed'),
                    'meta' => $last ? ClientPresenter::systemNames($last) : null,
                ];
                if ($can['start_subscription']) {
                    $row['action'] = ['label' => __('notify.client_hub.actions.renew'), 'href' => $href.'?open=start-subscription', 'icon' => 'plus'];
                }
                break;

            case ClientSegments::CLOSED:
                $row['signal'] = [
                    'tone' => 'neutral',
                    'icon' => 'x',
                    'text' => $client->closed_at
                        ? __('notify.client_hub.signals.closed_on', ['date' => ClientPresenter::date($client->closed_at)])
                        : __('notify.client_hub.signals.closed'),
                    'meta' => ClientPresenter::closedReasonLabel($client->closed_reason),
                ];
                break;

            default:
                $row['signal'] = self::prospectSignal(
                    $signals['follow_ups']->get($client->id),
                    $signals['appointments']->get($client->id),
                    $client->last_activity_at ?? null,
                );
                if ($row['phone_href']) {
                    $row['action'] = ['label' => __('notify.client_hub.actions.call'), 'href' => $row['phone_href'], 'icon' => 'phone', 'external' => true];
                }
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private static function subscriberSignal(Collection $subscriptions, array $money, int $pendingReceipts): array
    {
        if ($money['overdue_minor'] > 0) {
            return ['tone' => 'danger', 'icon' => 'alert-circle', 'money_minor' => $money['overdue_minor'], 'text' => __('notify.client_hub.signals.overdue')];
        }

        if ($money['due_minor'] > 0) {
            return [
                'tone' => 'warning',
                'icon' => 'wallet',
                'money_minor' => $money['due_minor'],
                'text' => __('notify.client_hub.signals.due'),
                'meta' => $money['next_due_date'] ? __('notify.client_hub.signals.due_on', ['date' => ClientPresenter::date($money['next_due_date'])]) : null,
            ];
        }

        if ($pendingReceipts > 0) {
            return ['tone' => 'info', 'icon' => 'clipboard-list', 'text' => __('notify.client_hub.signals.pending_receipt')];
        }

        $active = $subscriptions->firstWhere('status', 'active');
        if (! $active) {
            return ['tone' => 'neutral', 'icon' => 'circle', 'text' => __('notify.client_hub.signals.no_active_subscription')];
        }

        $renewal = $active->current_period_end ?? $active->next_billing_date ?? $active->renewal_date;

        return [
            'tone' => $active->cancel_at_period_end ? 'warning' : 'success',
            'icon' => 'check',
            'text' => $active->cancel_at_period_end
                ? __('notify.client_hub.signals.ends_on', ['date' => ClientPresenter::date($renewal)])
                : ($renewal ? __('notify.client_hub.signals.renews_on', ['date' => ClientPresenter::date($renewal)]) : __('notify.client_hub.signals.active')),
            'meta' => ClientPresenter::systemNames($active).' · '.ClientPresenter::cycleLabel($active),
        ];
    }

    /** @return array<string, mixed> */
    private static function prospectSignal(?object $followUp, ?Appointment $appointment, mixed $lastActivity): array
    {
        $now = now();
        $followUpAt = $followUp ? Carbon::parse($followUp->follow_up_date_time) : null;

        if ($followUpAt && $followUpAt->lessThanOrEqualTo($now)) {
            return ['tone' => 'danger', 'icon' => 'alert-circle', 'text' => __('notify.client_hub.signals.follow_up_due'), 'meta' => ClientPresenter::dateTime($followUpAt)];
        }

        if ($appointment) {
            $isInstallation = $appointment->appointment_type === AppointmentTypes::INSTALLATION;

            return [
                'tone' => 'info',
                'icon' => 'calendar',
                'text' => $isInstallation ? __('notify.client_hub.signals.installation_on') : __('notify.client_hub.signals.appointment_on'),
                'meta' => ClientPresenter::appointmentWhen($appointment),
            ];
        }

        if ($followUpAt) {
            return ['tone' => 'info', 'icon' => 'calendar', 'text' => __('notify.client_hub.signals.follow_up_on'), 'meta' => ClientPresenter::dateTime($followUpAt)];
        }

        return [
            'tone' => 'neutral',
            'icon' => 'activity',
            'text' => $lastActivity
                ? __('notify.client_hub.signals.last_activity', ['date' => ClientPresenter::date($lastActivity)])
                : __('notify.client_hub.signals.no_activity'),
        ];
    }
}
