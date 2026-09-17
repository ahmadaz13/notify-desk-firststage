<?php

namespace App\ViewModels;

use App\Models\Client;
use App\Services\OperationalQueueService;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientListViewModel
{
    public function __construct(
        public readonly LengthAwarePaginator $clients,
        public readonly array $rows,
        public readonly array $filters,
        public readonly array $stageOptions
    ) {
    }

    public static function make(
        LengthAwarePaginator $clients,
        array $filters,
        array $lifecycleStages,
        array $lifecycleLabels,
        OperationalQueueService $queues
    ): self {
        $rows = $clients->getCollection()
            ->map(fn (Client $client) => self::row($client, $lifecycleLabels, $queues))
            ->values()
            ->all();

        $stageOptions = collect($lifecycleStages)
            ->map(fn (string $stage) => [
                'value' => $stage,
                'label' => self::stageLabel($stage, $lifecycleLabels),
            ])
            ->prepend(['value' => 'all', 'label' => __('notify.clients.all_stages')])
            ->push(['value' => 'archived', 'label' => __('notify.clients.closed_archived')])
            ->values()
            ->all();

        return new self($clients, $rows, $filters, $stageOptions);
    }

    private static function row(Client $client, array $lifecycleLabels, OperationalQueueService $queues): array
    {
        $stage = ClientLifecycle::normalizeStage($client->stage ?? null, $client->status ?? null);
        $nextAction = $queues->nextActionFor($client);
        $preferredContact = $client->preferredOperationalContact();
        $contactName = $preferredContact['name'];
        $contactPhone = $preferredContact['phone'];
        $businessPhone = $client->business_phone ?: $client->phone;
        $whatsappPhone = $preferredContact['whatsapp_number'];

        return [
            'id' => $client->id,
            'initial' => mb_substr((string) $client->business_name, 0, 1),
            'business_name' => (string) $client->business_name,
            'business_type' => (string) ($client->business_type ?: $client->business_category ?: 'Unspecified'),
            'location' => trim(collect([$client->city ?: $client->city_area, $client->area])->filter()->join(' / ')),
            'business_phone' => $businessPhone,
            'contact_name' => $contactName ?: __('notify.clients.unassigned'),
            'contact_phone' => $contactPhone,
            'whatsapp_url' => self::whatsappUrl($whatsappPhone),
            'stage_label' => self::stageLabel($stage, $lifecycleLabels),
            'stage_variant' => self::stageVariant($stage),
            'next_action' => [
                'label' => self::nextActionLabel((string) ($nextAction['label'] ?? 'Open client')),
                'at' => self::dateLabel($nextAction['at'] ?? null),
                'queue' => $nextAction['queue'] ?? null,
            ],
            'responsible_user' => (string) ($client->primaryOwner?->name ?? 'Unassigned'),
            'last_activity' => self::dateLabel($client->last_activity_at ?? $client->contact_attempts_max_created_at ?? $client->updated_at),
            'href' => route('clients.show', $client->id),
            'call_href' => $contactPhone ? 'tel:'.$contactPhone : null,
        ];
    }

    private static function stageVariant(string $stage): string
    {
        return match ($stage) {
            ClientLifecycle::SUBSCRIBER => 'success',
            ClientLifecycle::CLOSED => 'neutral',
            ClientLifecycle::DECISION_PENDING,
            ClientLifecycle::INSTALLED_FREE,
            ClientLifecycle::INSTALLATION_SCHEDULED => 'warning',
            default => 'info',
        };
    }

    private static function stageLabel(string $stage, array $fallbacks): string
    {
        $key = 'notify.clients.stages.'.$stage;
        $translated = __($key);

        return $translated !== $key ? $translated : ($fallbacks[$stage] ?? str($stage)->headline()->toString());
    }

    private static function nextActionLabel(string $label): string
    {
        $key = match ($label) {
            'Attend appointment' => 'attend_appointment',
            'Call client' => 'call_client',
            'Closed' => 'closed',
            'Needs review' => 'needs_review',
            'Open client' => 'open_client',
            'Prepare installation' => 'prepare_installation',
            'Subscriber' => 'subscriber',
            'Trial follow-up' => 'trial_follow_up',
            'Waiting for decision' => 'waiting_for_decision',
            'Callback at date/time' => 'callback_at_date_time',
            default => null,
        };

        return $key ? __('notify.clients.next_actions.'.$key) : $label;
    }

    private static function dateLabel(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d H:i');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private static function whatsappUrl(?string $phone): ?string
    {
        $clean = preg_replace('/[^0-9]/', '', (string) $phone);
        if ($clean === '') {
            return null;
        }

        if (str_starts_with($clean, '0')) {
            $clean = '962'.substr($clean, 1);
        } elseif (str_starts_with($clean, '7')) {
            $clean = '962'.$clean;
        }

        return 'https://wa.me/'.$clean;
    }
}
