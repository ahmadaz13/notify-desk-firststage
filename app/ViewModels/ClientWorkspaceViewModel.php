<?php

namespace App\ViewModels;

use App\Models\Client;
use App\Services\OperationalQueueService;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ClientWorkspaceViewModel
{
    public function __construct(
        public readonly array $header,
        public readonly array $metrics,
        public readonly array $sections,
        public readonly array $overview,
        public readonly array $contacts,
        public readonly array $timeline,
        public readonly array $notes
    ) {
    }

    public static function make(
        Client $client,
        Collection $timeline,
        Collection $appointments,
        Collection $followUps,
        Collection $outcomes,
        Collection $payments,
        Collection $subscriptions,
        Collection $contracts,
        Collection $offers,
        Collection $contactAttempts,
        Collection $installations,
        array $lifecycleLabels,
        ?OperationalQueueService $queues = null
    ): self {
        $stage = ClientLifecycle::normalizeStage($client->stage ?? null, $client->status ?? null);
        $preferredContact = $client->preferredOperationalContact();
        $contactPhone = $preferredContact['phone'];
        $contactName = $preferredContact['name'];
        $businessPhone = $client->business_phone ?: $client->phone;
        $nextAction = $queues?->nextActionFor($client) ?? null;
        $partner = $client->partnerAttribution?->partner ?: $client->partner;
        $commissionBps = $client->partnerAttribution?->commission_bps_snapshot;
        $commissionLabel = $commissionBps === null
            ? ($partner ? 'إحالة قديمة بلا لقطة عمولة' : 'عميل مباشر')
            : sprintf('%d.%02d%%', intdiv($commissionBps, 100), $commissionBps % 100);

        $sections = [
            ['id' => 'overview', 'label' => 'نظرة عامة', 'count' => null],
            ['id' => 'contacts', 'label' => 'جهات الاتصال', 'count' => $client->contacts->count()],
            ['id' => 'timeline', 'label' => 'الخط الزمني', 'count' => $timeline->count()],
            ['id' => 'appointments', 'label' => 'سجل المواعيد', 'count' => $appointments->count()],
            ['id' => 'installation', 'label' => 'التركيب والمتابعة', 'count' => $installations->count() + $followUps->count()],
            ['id' => 'billing', 'label' => 'الاشتراك والفوترة', 'count' => $subscriptions->count() + $payments->count()],
            ['id' => 'notes', 'label' => 'ملاحظات العميل', 'count' => $client->notes ? 1 : 0],
        ];

        return new self(
            header: [
                'id' => $client->id,
                'business_name' => (string) $client->business_name,
                'subtitle' => collect([
                    $client->business_type ?: $client->business_category,
                    trim(collect([$client->city ?: $client->city_area, $client->area])->filter()->join(' / ')),
                    $businessPhone,
                ])->filter()->join(' · '),
                'stage' => $stage,
                'stage_label' => $lifecycleLabels[$stage] ?? str($stage)->headline()->toString(),
                'stage_variant' => self::stageVariant($stage),
                'contact_name' => $contactName ?: 'غير محدد',
                'contact_phone' => $contactPhone,
                'business_phone' => $businessPhone,
                'call_href' => $contactPhone ? 'tel:'.$contactPhone : null,
                'whatsapp_url' => self::whatsappUrl($preferredContact['whatsapp_number']),
                'can_convert' => ! in_array($stage, [ClientLifecycle::SUBSCRIBER, ClientLifecycle::CLOSED], true),
                'next_action' => $nextAction ? [
                    'label' => (string) ($nextAction['label'] ?? 'Open client'),
                    'at' => self::dateLabel($nextAction['at'] ?? null),
                    'queue' => $nextAction['queue'] ?? null,
                ] : null,
            ],
            metrics: [
                ['label' => 'جهة الاتصال', 'value' => $contactName ?: 'غير محدد', 'meta' => 'مصدر الفرصة: '.($client->lead_source ?: 'غير محدد')],
                ['label' => 'العمليات', 'value' => $appointments->count().' مواعيد · '.$followUps->count().' متابعات', 'meta' => $outcomes->count().' نتائج اجتماعات · '.$installations->count().' تركيبات'],
                ['label' => 'الفوترة', 'value' => number_format((float) $payments->sum('amount'), 2).' د.أ', 'meta' => $subscriptions->count().' اشتراكات · '.$contracts->count().' عقود · '.$offers->count().' عروض'],
            ],
            sections: $sections,
            overview: [
                ['label' => 'اسم النشاط التجاري', 'value' => (string) $client->business_name],
                ['label' => 'هاتف النشاط التجاري', 'value' => (string) $businessPhone, 'ltr' => true],
                ['label' => 'جهة الاتصال الأساسية', 'value' => trim(($contactName ?: 'غير محدد').' '.($contactPhone ? '· '.$contactPhone : ''))],
                ['label' => 'المنطقة والمدينة', 'value' => trim(collect([$client->city ?: $client->city_area, $client->area])->filter()->join(' / ')) ?: 'غير محدد'],
                ['label' => 'نوع النشاط والفئة', 'value' => ($client->business_type ?: $client->business_category ?: 'غير محدد').' · '.($client->number_of_branches ?? 1).' فروع'],
                ['label' => 'مصدر العميل', 'value' => trim(($client->lead_source ?: 'غير محدد').' '.($client->source_reference ? '· '.$client->source_reference : ''))],
                ['label' => 'شريك الإحالة', 'value' => $partner?->company_name ?: 'لا يوجد', 'meta' => $commissionLabel],
                ['label' => 'الحضور الرقمي', 'value' => collect([$client->instagram, $client->website])->filter()->join(' · ') ?: 'غير محدد'],
                ['label' => 'الموقع', 'value' => $client->location_text ?: ($client->maps_url ?: 'غير محدد')],
            ],
            contacts: $client->contacts
                ->map(fn ($contact) => [
                    'name' => (string) $contact->name,
                    'role' => $contact->role ?: 'بدون دور',
                    'primary_phone' => $contact->primary_phone,
                    'whatsapp_number' => $contact->whatsapp_number,
                    'preferred_contact_method' => $contact->preferred_contact_method,
                    'is_primary' => (bool) $contact->is_primary,
                ])
                ->values()
                ->all(),
            timeline: $timeline
                ->take(20)
                ->map(fn ($event) => [
                    'description' => (string) $event->description,
                    'type' => (string) $event->type,
                    'at' => self::dateLabel($event->created_at),
                    'variant' => self::timelineVariant((string) $event->type),
                ])
                ->values()
                ->all(),
            notes: [
                'client_note' => $client->notes,
                'recent_attempts' => $contactAttempts
                    ->take(5)
                    ->map(fn ($attempt) => [
                        'method' => (string) $attempt->method,
                        'result' => (string) $attempt->result,
                        'note' => $attempt->note ?: 'بدون ملاحظة',
                        'next_action' => $attempt->next_action,
                        'next_follow_up_date' => self::dateLabel($attempt->next_follow_up_date),
                    ])
                    ->values()
                    ->all(),
            ]
        );
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

    private static function timelineVariant(string $type): string
    {
        if (str_contains($type, 'payment') || str_contains($type, 'converted')) {
            return 'success';
        }

        if (str_contains($type, 'outcome') || str_contains($type, 'review')) {
            return 'warning';
        }

        return 'info';
    }

    private static function dateLabel(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
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
