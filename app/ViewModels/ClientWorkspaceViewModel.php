<?php

namespace App\ViewModels;

use App\Models\Client;
use App\Models\ClientReviewItem;
use App\Models\Contract;
use App\Models\Subscription;
use App\Models\User;
use App\Services\OperationalQueueService;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use App\Support\FinancialPermissions;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class ClientWorkspaceViewModel
{
    public function __construct(
        // Legacy properties preserved for backwards compatibility:
        public readonly array $header,
        public readonly array $metrics,
        public readonly array $sections,
        public readonly array $overview,
        public readonly array $contacts,
        public readonly array $timeline,
        public readonly array $notes,

        // New Phase 04 properties:
        public readonly array $identity,
        public readonly array $preferredContact,
        public readonly array $stage,
        public readonly array $nextAction,
        public readonly array $contextActions,
        public readonly array $subscriptionsByProduct,
        public readonly array $amountDueSummary,
        public readonly array $contractAccess,
        public readonly array $recentActivities,
        public readonly array $details,
        public readonly array $fullHistory,
        public readonly array $managementFinance,
        public readonly ?array $latestPayment = null
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
        ?OperationalQueueService $queues = null,
        array $receivableSummary = [],
        ?Collection $invoiceReceivables = null,
        ?User $actor = null
    ): self {
        $actor ??= auth()->user();
        $stage = ClientLifecycle::normalizeStage($client->stage ?? null, $client->status ?? null);
        $stageLabel = $lifecycleLabels[$stage] ?? str($stage)->headline()->toString();
        $stageVariant = self::stageVariant($stage);

        $preferredContactData = $client->preferredOperationalContact();
        $contactPhone = $preferredContactData['phone'];
        $contactName = $preferredContactData['name'];
        $businessPhone = $client->business_phone ?: $client->phone;
        $callHref = $contactPhone ? 'tel:' . $contactPhone : null;
        $whatsappUrl = self::whatsappUrl($preferredContactData['whatsapp_number'] ?: $contactPhone);

        $nextAction = $queues?->nextActionFor($client) ?? null;
        $partner = $client->partnerAttribution?->partner ?: $client->partner;
        $commissionBps = $client->partnerAttribution?->commission_bps_snapshot;
        $commissionLabel = $commissionBps === null
            ? ($partner ? 'إحالة قديمة بلا لقطة عمولة' : 'عميل مباشر')
            : sprintf('%d.%02d%%', intdiv($commissionBps, 100), $commissionBps % 100);

        // 1. Identity
        $identity = [
            'business_name' => (string) $client->business_name,
            'business_category' => (string) ($client->business_category ?: $client->business_type ?: 'غير محدد'),
            'location_summary' => trim(collect([$client->city_area ?: $client->city, $client->area])->filter()->join(' · ')) ?: 'غير محدد',
        ];

        // 2. Preferred Contact
        $preferredContact = [
            'has_contact' => filled($contactPhone) || filled($contactName),
            'exists' => filled($contactPhone) || filled($contactName),
            'name' => $contactName ?: null,
            'role' => $preferredContactData['role'] ?: null,
            'phone' => $contactPhone ?: null,
            'call_href' => $callHref,
            'whatsapp_url' => $whatsappUrl,
        ];

        // 3. Current Stage
        $stageInfo = [
            'key' => $stage,
            'label' => $stageLabel,
            'variant' => $stageVariant,
        ];

        // 4. Next Action
        $nextActionInfo = [
            'label' => $nextAction ? (string) ($nextAction['label'] ?? 'Open client') : __('notify.client_workspace.no_recent_activity'),
            'at' => $nextAction && !empty($nextAction['at']) ? self::dateLabel($nextAction['at']) : null,
            'context' => $nextAction['queue'] ?? null,
        ];

        // 5 & 6. Context Actions Resolver (UX-D05)
        $contextActions = self::resolveContextActions(
            $client,
            $stage,
            $nextAction,
            $appointments,
            $followUps,
            $contracts,
            $subscriptions,
            $receivableSummary,
            $actor,
            $callHref,
            $whatsappUrl
        );

        // 7. Subscriptions by Product (UX-D04)
        $subscriptionsByProduct = $subscriptions->map(function (Subscription $sub) use ($invoiceReceivables, $contracts) {
            $plan = $sub->plan;
            $product = $plan?->product;
            $preferArabic = str_starts_with(app()->getLocale(), 'ar');
            $productName = $preferArabic
                ? ($product?->name_ar ?: ($product?->name_en ?: 'Notify Desk'))
                : ($product?->name_en ?: ($product?->name_ar ?: 'Notify Desk'));
            $planName = $preferArabic
                ? ($plan?->name_ar ?: ($plan?->name_en ?: ($sub->plan_name_snapshot ?: __('notify.subscriptions.authorized'))))
                : ($plan?->name_en ?: ($plan?->name_ar ?: ($sub->plan_name_snapshot ?: __('notify.subscriptions.authorized'))));

            $interval = $sub->billing_interval_v2 ?: ($sub->billing_type ?: 'monthly');
            $termLabel = $interval === 'annual' ? __('notify.catalog.annual') : __('notify.catalog.monthly');

            $status = (string) $sub->status;
            $statusVariant = match ($status) {
                'active' => 'success',
                'cancelled' => 'danger',
                'pending_change' => 'warning',
                default => 'info',
            };
            $statusLabel = match ($status) {
                'active' => __('notify.statuses.active'),
                'cancelled' => __('notify.statuses.cancelled'),
                'pending_change' => __('notify.statuses.pending'),
                default => str($status)->headline()->toString(),
            };

            $nextDate = $sub->next_billing_date ?: ($sub->renewal_date ?: $sub->current_period_end);
            $nextDateLabel = $nextDate ? Carbon::parse($nextDate)->format('Y-m-d') : null;

            // Outstanding amount for this subscription context
            $subOutstandingMinor = 0;
            if ($invoiceReceivables && $sub->relationLoaded('invoices')) {
                $subInvoiceIds = $sub->invoices->pluck('id')->all();
                $subOutstandingMinor = $invoiceReceivables
                    ->whereIn('invoice_id', $subInvoiceIds)
                    ->sum('outstanding_minor');
            }
            $hasBalance = $subOutstandingMinor > 0;
            $balanceLabel = $hasBalance
                ? Money::fromMinorUnits($subOutstandingMinor)->format() . ' ' . __('notify.common.currency_jod')
                : __('notify.client_workspace.paid_in_full');

            // Contract status
            $contract = $contracts->firstWhere('subscription_id', $sub->id) ?? $sub->contracts->first();
            $contractInfo = $contract ? [
                'id' => $contract->id,
                'number' => $contract->contract_number,
                'status' => $contract->status,
                'status_label' => match ($contract->status) {
                    'issued' => __('notify.statuses.issued'),
                    'draft' => __('notify.statuses.draft'),
                    'voided' => __('notify.statuses.voided'),
                    'superseded' => __('notify.statuses.superseded'),
                    default => $contract->status,
                },
                'status_variant' => match ($contract->status) {
                    'issued' => 'success',
                    'voided' => 'danger',
                    'superseded' => 'neutral',
                    default => 'warning',
                },
                'preview_url' => route('contracts.preview', $contract->id),
                'download_url' => route('contracts.download', $contract->id),
                'download_pdf_url' => route('contracts.download-pdf', $contract->id),
                'print_url' => route('contracts.print', $contract->id),
            ] : null;

            return [
                'id' => $sub->id,
                'product_name' => $productName,
                'plan_name' => $planName,
                'term' => $interval,
                'term_label' => $termLabel,
                'status' => $status,
                'status_label' => $statusLabel,
                'status_variant' => $statusVariant,
                'next_date' => $nextDateLabel,
                'has_balance' => $hasBalance,
                'balance_label' => $balanceLabel,
                'contract' => $contractInfo,
            ];
        })->values()->all();

        // 8. Amount Due Summary & Lightweight Financial Projection
        $canRecordPayment = $actor ? FinancialPermissions::allows($actor, FinancialPermissions::RECORD_PAYMENT) : Gate::allows(FinancialPermissions::RECORD_PAYMENT);
        $canViewFinancialReports = $actor ? FinancialPermissions::allows($actor, FinancialPermissions::VIEW_FINANCIAL_REPORTS) : Gate::allows(FinancialPermissions::VIEW_FINANCIAL_REPORTS);
        $totalOutstandingMinor = (int) ($receivableSummary['total_outstanding_minor'] ?? 0);
        $overdueOutstandingMinor = (int) ($receivableSummary['overdue_outstanding_minor'] ?? 0);
        $totalCustomerCreditMinor = (int) ($receivableSummary['total_customer_credit_minor'] ?? 0);

        $latestPaymentModel = $payments->whereNull('reversal')->first() ?? $payments->first();
        $latestPayment = $latestPaymentModel ? [
            'id' => $latestPaymentModel->id,
            'amount_minor' => (int) ($latestPaymentModel->amount_minor ?? round($latestPaymentModel->amount * 1000)),
            'amount_formatted' => Money::fromMinorUnits((int) ($latestPaymentModel->amount_minor ?? round($latestPaymentModel->amount * 1000)))->format() . ' ' . __('notify.common.currency_jod'),
            'paid_at' => $latestPaymentModel->paid_at ? Carbon::parse($latestPaymentModel->paid_at)->format('Y-m-d') : null,
            'method' => $latestPaymentModel->payment_method ? (\App\Support\PaymentMethods::labels()[$latestPaymentModel->payment_method] ?? $latestPaymentModel->payment_method) : '—',
            'reference' => $latestPaymentModel->reference ?: ($latestPaymentModel->reference_number ?: null),
        ] : null;

        $amountDueSummary = [
            'total_minor' => $totalOutstandingMinor,
            'total_formatted' => Money::fromMinorUnits($totalOutstandingMinor)->format(),
            'is_paid' => $totalOutstandingMinor <= 0,
            'overdue_minor' => $overdueOutstandingMinor,
            'overdue_formatted' => Money::fromMinorUnits($overdueOutstandingMinor)->format(),
            'has_overdue' => $overdueOutstandingMinor > 0,
            'credit_minor' => $totalCustomerCreditMinor,
            'credit_formatted' => Money::fromMinorUnits($totalCustomerCreditMinor)->format(),
            'has_credit' => $totalCustomerCreditMinor > 0,
            'currency' => __('notify.common.currency_jod'),
            'can_record_payment' => $canRecordPayment,
            'latest_payment' => $latestPayment,
            'view_financial_details_url' => route('collections.index', ['client_id' => $client->id]),
            'can_view_financial_details' => $canViewFinancialReports,
        ];

        // 9. Contract Access
        $contractAccess = $contracts->map(function (Contract $contract) use ($actor) {
            return [
                'id' => $contract->id,
                'number' => $contract->contract_number,
                'status' => $contract->status,
                'status_label' => match ($contract->status) {
                    'issued' => 'معتمد',
                    'draft' => 'مسودة',
                    'voided' => 'ملغي',
                    'superseded' => 'مستبدل',
                    default => $contract->status,
                },
                'status_variant' => match ($contract->status) {
                    'issued' => 'success',
                    'voided' => 'danger',
                    'superseded' => 'neutral',
                    default => 'warning',
                },
                'issued_at' => $contract->issued_at ? Carbon::parse($contract->issued_at)->format('Y-m-d') : null,
                'preview_url' => route('contracts.preview', $contract->id),
                'download_url' => route('contracts.download', $contract->id),
                'download_pdf_url' => route('contracts.download-pdf', $contract->id),
                'print_url' => route('contracts.print', $contract->id),
                'can_download' => $actor ? Gate::forUser($actor)->allows('download', $contract) : Gate::allows('download', $contract),
                'can_view' => $actor ? Gate::forUser($actor)->allows('view', $contract) : Gate::allows('view', $contract),
            ];
        })->values()->all();

        // 10. Last 3 Activities
        $recentActivities = $timeline->take(3)->map(function ($event) {
            return [
                'description' => (string) $event->description,
                'type' => (string) $event->type,
                'at' => self::dateLabel($event->created_at),
                'variant' => self::timelineVariant((string) $event->type),
            ];
        })->values()->all();

        // 11. Full History
        $fullHistory = [
            'timeline' => $timeline,
            'contact_attempts' => $contactAttempts,
            'appointments' => $appointments,
            'follow_ups' => $followUps,
            'installations' => $installations,
            'outcomes' => $outcomes,
        ];

        // 12. Expandable Details
        $details = [
            'business_name' => (string) $client->business_name,
            'business_category' => (string) ($client->business_category ?: $client->business_type ?: 'غير محدد'),
            'business_phone' => (string) ($client->business_phone ?: $client->phone ?: 'غير محدد'),
            'contact_person' => (string) ($client->contact_person ?: 'غير محدد'),
            'city_area' => (string) ($client->city_area ?: $client->city ?: 'غير محدد'),
            'city' => (string) ($client->city ?: 'غير محدد'),
            'area' => (string) ($client->area ?: 'غير محدد'),
            'location_text' => (string) ($client->location_text ?: 'غير محدد'),
            'maps_url' => $client->maps_url ?: null,
            'instagram' => $client->instagram ?: null,
            'website' => $client->website ?: null,
            'lead_source' => (string) ($client->lead_source ?: 'غير محدد'),
            'source_reference' => $client->source_reference ?: null,
            'number_of_branches' => (int) ($client->number_of_branches ?: 1),
            'partner_name' => $partner?->company_name ?: null,
            'partner_commission' => ($actor?->isAdmin() ?? false) ? $commissionLabel : null,
            'notes' => $client->notes ?: null,
            'contacts' => $client->contacts,
        ];

        // 13. Management & Finance
        $canManageBilling = $actor ? FinancialPermissions::allows($actor, FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING) : Gate::allows(FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING);
        $managementFinance = [
            'can_manage_billing' => $canManageBilling,
            'can_record_payment' => $canRecordPayment,
            'can_view_accounting' => $actor ? FinancialPermissions::allows($actor, FinancialPermissions::VIEW_ACCOUNTING) : Gate::allows(FinancialPermissions::VIEW_ACCOUNTING),
            'invoices_count' => $client->relationLoaded('invoices') ? $client->invoices->count() : 0,
            'payments_count' => $payments->count(),
            'offers_count' => $offers->count(),
        ];

        // Legacy sections preserved:
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
                'stage_label' => $stageLabel,
                'stage_variant' => $stageVariant,
                'contact_name' => $contactName ?: 'غير محدد',
                'contact_phone' => $contactPhone,
                'business_phone' => $businessPhone,
                'call_href' => $callHref,
                'whatsapp_url' => $whatsappUrl,
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
            ],
            identity: $identity,
            preferredContact: $preferredContact,
            stage: $stageInfo,
            nextAction: $nextActionInfo,
            contextActions: $contextActions,
            subscriptionsByProduct: $subscriptionsByProduct,
            amountDueSummary: $amountDueSummary,
            contractAccess: $contractAccess,
            recentActivities: $recentActivities,
            details: $details,
            fullHistory: $fullHistory,
            managementFinance: $managementFinance,
            latestPayment: $latestPayment
        );
    }

    public static function resolveContextActions(
        Client $client,
        string $stage,
        ?array $nextAction,
        Collection $appointments,
        Collection $followUps,
        Collection $contracts,
        Collection $subscriptions,
        array $receivableSummary,
        ?User $actor = null,
        ?string $callHref = null,
        ?string $whatsappUrl = null
    ): array {
        $canRecordPayment = $actor ? FinancialPermissions::allows($actor, FinancialPermissions::RECORD_PAYMENT) : Gate::allows(FinancialPermissions::RECORD_PAYMENT);
        $canManageBilling = $actor ? FinancialPermissions::allows($actor, FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING) : Gate::allows(FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING);
        $totalDue = (int) ($receivableSummary['total_outstanding_minor'] ?? 0);
        $overdueDue = (int) ($receivableSummary['overdue_outstanding_minor'] ?? 0);

        // Priority 1: Closed
        if ($stage === ClientLifecycle::CLOSED || $client->status === 'archived') {
            return [
                'state_key' => 'closed',
                'primary' => [
                    'label' => __('notify.client_workspace.action_reopen'),
                    'action' => 'scroll_to',
                    'target' => '#sec-reopen-client',
                    'type' => 'reopen',
                    'variant' => 'primary',
                ],
                'secondary' => [
                    ['label' => __('notify.client_workspace.view_history'), 'target' => '#sec-full-history', 'type' => 'anchor'],
                    ['label' => __('notify.client_workspace.view_contracts'), 'target' => '#sec-contracts', 'type' => 'anchor'],
                ],
            ];
        }

        // Priority 2: Review Required
        $pendingReview = $client->relationLoaded('reviewItems')
            ? $client->reviewItems->firstWhere('status', ClientReviewItem::STATUS_PENDING)
            : ClientReviewItem::where('client_id', $client->id)->where('status', ClientReviewItem::STATUS_PENDING)->oldest()->first();
        if ($pendingReview) {
            return [
                'state_key' => 'review_required',
                'primary' => [
                    'label' => __('notify.client_workspace.action_review'),
                    'action' => 'scroll_to',
                    'target' => '#sec-review-item-' . $pendingReview->id,
                    'type' => 'review',
                    'variant' => 'warning',
                ],
                'secondary' => array_values(array_filter([
                    ['label' => __('notify.clients.edit'), 'href' => route('clients.edit', $client->id), 'type' => 'link'],
                    ['label' => __('notify.client_workspace.action_record_call'), 'action' => 'open_contact_outcome', 'type' => 'button'],
                    ['label' => __('notify.client_workspace.action_close_client'), 'target' => '#sec-close-client', 'type' => 'anchor'],
                ])),
            ];
        }

        // Priority 3: Authorized Overdue Collection
        if ($canRecordPayment && $overdueDue > 0) {
            return [
                'state_key' => 'authorized_overdue_collection',
                'primary' => [
                    'label' => __('notify.client_workspace.action_record_payment'),
                    'action' => 'scroll_to',
                    'target' => '#sec-record-payment',
                    'type' => 'record_payment',
                    'variant' => 'primary',
                ],
                'secondary' => array_values(array_filter([
                    $callHref ? ['label' => __('notify.actions.call'), 'href' => $callHref, 'type' => 'call'] : null,
                    $whatsappUrl ? ['label' => __('notify.actions.whatsapp'), 'href' => $whatsappUrl, 'type' => 'whatsapp'] : null,
                    ['label' => __('notify.client_workspace.view_subscription'), 'target' => '#sec-subscriptions', 'type' => 'anchor'],
                    ['label' => __('notify.client_workspace.view_contracts'), 'target' => '#sec-contracts', 'type' => 'anchor'],
                ])),
            ];
        }

        // Priority 4: Due Installation
        $activeInstall = $appointments->where('appointment_type', AppointmentTypes::INSTALLATION)
            ->whereIn('status', AppointmentTypes::activeStatuses())
            ->sortBy('appointment_date')
            ->first();
        if ($activeInstall) {
            $isDue = Carbon::parse($activeInstall->appointment_date)->lessThanOrEqualTo(Carbon::today());
            return [
                'state_key' => 'installation_scheduled',
                'primary' => [
                    'label' => $isDue
                        ? __('notify.client_workspace.action_complete_installation')
                        : __('notify.client_workspace.action_prepare_installation'),
                    'action' => 'scroll_to',
                    'target' => $isDue ? '#sec-complete-installation' : '#sec-appointments',
                    'type' => $isDue ? 'complete_installation' : 'prepare_installation',
                    'variant' => 'primary',
                ],
                'secondary' => array_values(array_filter([
                    $canManageBilling ? ['label' => __('notify.client_workspace.action_start_subscription'), 'target' => '#sec-start-subscription', 'type' => 'start_subscription'] : null,
                    ['label' => __('notify.client_workspace.action_reschedule'), 'target' => '#sec-reschedule-appointment-' . $activeInstall->id, 'type' => 'anchor'],
                    ['label' => __('notify.client_workspace.action_close_client'), 'target' => '#sec-close-client', 'type' => 'close_client'],
                    $callHref ? ['label' => __('notify.actions.call'), 'href' => $callHref, 'type' => 'call'] : null,
                ])),
            ];
        }

        // Priority 5: Due Appointment
        $activeAppt = $appointments->where('appointment_type', '!=', AppointmentTypes::INSTALLATION)
            ->whereIn('status', AppointmentTypes::activeStatuses())
            ->sortBy('appointment_date')
            ->first();
        if ($activeAppt) {
            $isDue = Carbon::parse($activeAppt->appointment_date)->lessThanOrEqualTo(Carbon::today());
            return [
                'state_key' => 'appointment_scheduled',
                'primary' => [
                    'label' => $isDue
                        ? __('notify.client_workspace.action_record_result')
                        : __('notify.client_workspace.action_view_appointment'),
                    'action' => $isDue ? 'link' : 'scroll_to',
                    'href' => $isDue ? route('appointments.outcome.create', $activeAppt->id) : null,
                    'target' => $isDue ? null : '#sec-appointments',
                    'type' => $isDue ? 'record_result' : 'view_appointment',
                    'variant' => 'primary',
                ],
                'secondary' => array_values(array_filter([
                    ['label' => __('notify.client_workspace.action_schedule_installation'), 'target' => '#sec-schedule-installation', 'type' => 'schedule_installation'],
                    $canManageBilling ? ['label' => __('notify.client_workspace.action_start_subscription'), 'target' => '#sec-start-subscription', 'type' => 'start_subscription'] : null,
                    ['label' => __('notify.client_workspace.action_reschedule'), 'target' => '#sec-reschedule-appointment-' . $activeAppt->id, 'type' => 'anchor'],
                    ['label' => __('notify.client_workspace.action_close_client'), 'target' => '#sec-close-client', 'type' => 'close_client'],
                    $callHref ? ['label' => __('notify.actions.call'), 'href' => $callHref, 'type' => 'call'] : null,
                ])),
            ];
        }

        // Priority 6: Due Follow-up or Callback
        $hasDueFollowUp = $followUps->isNotEmpty();
        if ($stage === ClientLifecycle::INSTALLED_FREE || $stage === ClientLifecycle::DECISION_PENDING || $hasDueFollowUp) {
            $stateKey = $stage === ClientLifecycle::DECISION_PENDING ? 'decision_pending' : 'free_installed_or_followup_due';
            $secondary = array_values(array_filter([
                $canManageBilling ? ['label' => __('notify.client_workspace.action_start_subscription'), 'target' => '#sec-start-subscription', 'type' => 'start_subscription'] : null,
                ['label' => __('notify.client_workspace.action_create_appointment'), 'target' => '#sec-create-appointment', 'type' => 'create_appointment'],
                ['label' => __('notify.client_workspace.action_schedule_installation'), 'target' => '#sec-schedule-installation', 'type' => 'schedule_installation'],
                ['label' => __('notify.client_workspace.action_close_client'), 'target' => '#sec-close-client', 'type' => 'close_client'],
                $callHref ? ['label' => __('notify.actions.call'), 'href' => $callHref, 'type' => 'call'] : null,
                $whatsappUrl ? ['label' => __('notify.actions.whatsapp'), 'href' => $whatsappUrl, 'type' => 'whatsapp'] : null,
            ]));

            return [
                'state_key' => $stateKey,
                'primary' => [
                    'label' => __('notify.client_workspace.action_record_followup'),
                    'action' => 'scroll_to',
                    'target' => '#sec-follow-ups',
                    'type' => 'record_followup',
                    'variant' => 'primary',
                ],
                'secondary' => $secondary,
            ];
        }

        // Priority 8: Subscriber
        if ($stage === ClientLifecycle::SUBSCRIBER) {
            if ($totalDue > 0) {
                return [
                    'state_key' => 'subscriber_with_balance',
                    'primary' => [
                        'label' => $canRecordPayment
                            ? __('notify.client_workspace.action_record_payment')
                            : __('notify.client_workspace.action_view_balance'),
                        'action' => 'scroll_to',
                        'target' => $canRecordPayment ? '#sec-record-payment' : '#sec-amount-due',
                        'type' => $canRecordPayment ? 'record_payment' : 'view_balance',
                        'variant' => 'primary',
                    ],
                    'secondary' => array_values(array_filter([
                        ['label' => __('notify.client_workspace.view_financial_details'), 'href' => route('collections.index', ['client_id' => $client->id]), 'type' => 'link'],
                        ['label' => __('notify.client_workspace.view_subscription'), 'target' => '#sec-subscriptions', 'type' => 'anchor'],
                        ['label' => __('notify.client_workspace.view_contracts'), 'target' => '#sec-contracts', 'type' => 'anchor'],
                        ['label' => __('notify.client_workspace.action_close_client'), 'target' => '#sec-close-client', 'type' => 'close_client'],
                        $callHref ? ['label' => __('notify.actions.call'), 'href' => $callHref, 'type' => 'call'] : null,
                    ])),
                ];
            }

            return [
                'state_key' => 'subscriber_paid',
                'primary' => [
                    'label' => __('notify.client_workspace.view_subscription'),
                    'action' => 'scroll_to',
                    'target' => '#sec-subscriptions',
                    'type' => 'view_subscription',
                    'variant' => 'primary',
                ],
                'secondary' => array_values(array_filter([
                    ['label' => __('notify.client_workspace.view_financial_details'), 'href' => route('collections.index', ['client_id' => $client->id]), 'type' => 'link'],
                    ['label' => __('notify.client_workspace.view_contracts'), 'target' => '#sec-contracts', 'type' => 'anchor'],
                    $canManageBilling ? ['label' => __('notify.client_workspace.action_start_subscription'), 'target' => '#sec-start-subscription', 'type' => 'start_subscription'] : null,
                    ['label' => __('notify.client_workspace.action_close_client'), 'target' => '#sec-close-client', 'type' => 'close_client'],
                ])),
            ];
        }

        // Priority 7: New Contact / Contacting / Prospect
        $isContacting = $stage === ClientLifecycle::CONTACTING;
        return [
            'state_key' => $isContacting ? 'contacting' : 'new_prospect',
            'primary' => [
                'label' => __('notify.client_workspace.action_record_call'),
                'action' => 'open_contact_outcome',
                'target' => '#sec-contact-outcome',
                'type' => 'record_call',
                'variant' => 'primary',
            ],
            'secondary' => array_values(array_filter([
                ['label' => __('notify.client_workspace.action_create_appointment'), 'target' => '#sec-create-appointment', 'type' => 'create_appointment'],
                ['label' => __('notify.client_workspace.action_schedule_installation'), 'target' => '#sec-schedule-installation', 'type' => 'schedule_installation'],
                $canManageBilling ? ['label' => __('notify.client_workspace.action_start_subscription'), 'target' => '#sec-start-subscription', 'type' => 'start_subscription'] : null,
                ['label' => __('notify.client_workspace.action_close_client'), 'target' => '#sec-close-client', 'type' => 'close_client'],
                ['label' => __('notify.clients.edit'), 'href' => route('clients.edit', $client->id), 'type' => 'link'],
                $callHref ? ['label' => __('notify.actions.call'), 'href' => $callHref, 'type' => 'call'] : null,
                $whatsappUrl ? ['label' => __('notify.actions.whatsapp'), 'href' => $whatsappUrl, 'type' => 'whatsapp'] : null,
            ])),
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
