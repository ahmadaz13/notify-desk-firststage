@extends('layouts.app')

@section('content')
@php
    $workspace = $clientWorkspaceViewModel;
    $identity = $workspace->identity;
    $prefContact = $workspace->preferredContact;
    $stage = $workspace->stage;
    $nextAction = $workspace->nextAction;
    $primaryAction = $workspace->contextActions['primary'] ?? null;
    $secondaryActions = $workspace->contextActions['secondary'] ?? [];
    $subscriptionsByProduct = $workspace->subscriptionsByProduct ?? [];
    $amountDue = $workspace->amountDueSummary;
    $contractsAccess = $workspace->contractAccess ?? [];
    $recentActivities = $workspace->recentActivities ?? [];
@endphp

<div class="notify-client-workspace notify-client-workspace--v2">
    {{-- Back to list navigation --}}
    <div class="notify-workspace-top-bar">
        <a class="notify-button notify-button--ghost notify-button--sm" href="{{ route('clients.index') }}">
            <span>← {{ __('notify.clients.back_to_list') }}</span>
        </a>
    </div>

    {{-- Post-Subscription Contract Success State --}}
    @if(session('lastStartedSubscriptionId'))
        <div class="notify-card notify-card--success-banner" style="margin-bottom:16px;padding:12px 16px;border-radius:8px;background:#F0FDF4;border:1px solid #86EFAC;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
            <div style="display:flex;align-items:center;gap:10px">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#16A34A;color:#fff;font-size:12px;font-weight:bold">✓</span>
                <div>
                    <strong style="color:#15803D;font-size:13px">{{ __('notify.subscriptions.activated_success') }}</strong>
                    <small style="display:block;color:#166534;margin-top:2px">{{ __('notify.client_workspace.subscription_activated_detail') }}</small>
                </div>
            </div>
            <a href="#sec-subscriptions" class="notify-button notify-button--soft notify-button--sm" data-scroll-to="#sec-subscriptions">
                <span>{{ __('notify.client_workspace.view_subscription') }} ↓</span>
            </a>
        </div>
    @endif

    {{-- SECTION 1, 2, 3, 4, 5, 6: Operational Command Card (Hero) --}}
    <section class="notify-workspace-command-card" aria-label="{{ __('notify.client_workspace.command_overview') }}">
        <div class="notify-workspace-command-card__header">
            {{-- 1. Client Identity --}}
            <div class="notify-workspace-identity">
                <div class="notify-workspace-identity__main">
                    <h1 class="notify-workspace-title">{{ $identity['business_name'] }}</h1>
                    <div class="notify-workspace-meta">
                        @if(!empty($identity['business_category']))
                            <span class="notify-workspace-meta-tag">{{ $identity['business_category'] }}</span>
                        @endif
                        @if(!empty($identity['location_summary']))
                            <span class="notify-workspace-meta-tag notify-workspace-meta-tag--location">{{ $identity['location_summary'] }}</span>
                        @endif
                    </div>
                </div>

                {{-- 3. Current Stage (Read-only human label) --}}
                <div class="notify-workspace-stage">
                    <span class="notify-badge notify-badge--{{ $stage['variant'] }} notify-badge--lg">
                        {{ $stage['label'] }}
                    </span>
                </div>
            </div>

            {{-- 2. Preferred Operational Contact --}}
            <div class="notify-workspace-contact-bar">
                <div class="notify-workspace-contact-info">
                    <span class="notify-workspace-contact-label">{{ __('notify.client_workspace.preferred_contact_title') }}:</span>
                    @if(!empty($prefContact['has_contact']))
                        <strong class="notify-workspace-contact-name">{{ $prefContact['name'] }}</strong>
                        @if(!empty($prefContact['role']))
                            <span class="notify-workspace-contact-role">({{ $prefContact['role'] }})</span>
                        @endif
                        @if(!empty($prefContact['phone']))
                            <span class="notify-workspace-contact-phone" dir="ltr">{{ $prefContact['phone'] }}</span>
                        @endif
                    @else
                        <span class="notify-muted">{{ __('notify.client_workspace.no_contact_on_file') }}</span>
                    @endif
                </div>

                @if(!empty($prefContact['has_contact']))
                    <div class="notify-workspace-contact-actions">
                        @if(!empty($prefContact['call_href']))
                            <a class="notify-button notify-button--soft notify-button--sm notify-contact-action"
                               href="{{ $prefContact['call_href'] }}"
                               aria-label="{{ __('notify.actions.call') }}"
                               title="{{ __('notify.actions.call') }}">
                                <span class="notify-icon-symbol">📞</span>
                                <span>{{ __('notify.actions.call') }}</span>
                            </a>
                        @endif
                        @if(!empty($prefContact['whatsapp_url']))
                            <a class="notify-button notify-button--soft notify-button--sm notify-contact-action notify-contact-action--whatsapp"
                               href="{{ $prefContact['whatsapp_url'] }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               aria-label="{{ __('notify.actions.whatsapp') }}"
                               title="{{ __('notify.actions.whatsapp') }}">
                                <span class="notify-icon-symbol">💬</span>
                                <span>{{ __('notify.actions.whatsapp') }}</span>
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- 4. Next Action Block --}}
        @if($nextAction && !empty($nextAction['label']))
            <div class="notify-workspace-next-action-card">
                <div class="notify-workspace-next-action-card__main">
                    <span class="notify-workspace-next-action-badge">{{ __('notify.clients.next_action') }}</span>
                    <strong class="notify-workspace-next-action-label">{{ $nextAction['label'] }}</strong>
                    @if(!empty($nextAction['at']))
                        <span class="notify-workspace-next-action-time" dir="ltr">⏱ {{ $nextAction['at'] }}</span>
                    @endif
                </div>
            </div>
        @endif

        {{-- 5 & 6. Primary and Secondary Context Actions --}}
        <div class="notify-workspace-actions-hub">
            {{-- 5. Visually Dominant Primary Context Action --}}
            @if($primaryAction)
                @php
                    $pUrl = $primaryAction['href'] ?? $primaryAction['target'] ?? '#';
                    $pType = $primaryAction['type'] ?? $primaryAction['key'] ?? '';
                    $pAction = $primaryAction['action'] ?? '';
                    $pIsCall = ($primaryAction['action_type'] ?? '') === 'call_outcome'
                        || $pType === 'record_call'
                        || $pAction === 'open_contact_outcome';
                    $pIsResult = $pType === 'record_result';
                    $pIsInstall = $pType === 'complete_installation' || $pUrl === '#sec-complete-installation';
                    $pIsFollowUp = $pType === 'record_followup' || $pUrl === '#sec-follow-ups';
                    $pIsCreateApt = $pType === 'create_appointment' || $pUrl === '#sec-create-appointment';
                    $pIsClose = $pType === 'close_client' || $pUrl === '#sec-close-client';
                    $pIsStartSub = $pType === 'start_subscription' || $pUrl === '#sec-paid-subscriptions';
                    $pIsRecordPayment = $pType === 'record_payment' || $pUrl === '#sec-record-payment';
                @endphp
                <div class="notify-workspace-primary-action">
                    @if($pIsCall)
                        <button type="button"
                                class="notify-button notify-button--primary notify-button--hero"
                                data-trigger-call-outcome>
                            <span>{{ $primaryAction['label'] }}</span>
                        </button>
                    @elseif($pIsResult)
                        <button type="button"
                                class="notify-button notify-button--primary notify-button--hero"
                                data-trigger-appointment-result>
                            <span>{{ $primaryAction['label'] }}</span>
                        </button>
                    @elseif($pIsInstall)
                        <button type="button"
                                class="notify-button notify-button--primary notify-button--hero"
                                data-trigger-installation-modal>
                            <span>{{ $primaryAction['label'] }}</span>
                        </button>
                    @elseif($pType === 'schedule_installation')
                        <button type="button"
                                class="notify-button notify-button--primary notify-button--hero"
                                data-trigger-schedule-installation>
                            <span>{{ $primaryAction['label'] }}</span>
                        </button>
                    @elseif($pType === 'reopen')
                        <button type="button"
                                class="notify-button notify-button--primary notify-button--hero"
                                data-scroll-to="#sec-reopen-client"
                                data-trigger-reopen-client>
                            <span>{{ $primaryAction['label'] }}</span>
                        </button>
                    @elseif($pIsFollowUp)
                        <button type="button"
                                class="notify-button notify-button--primary notify-button--hero"
                                data-trigger-followup-modal>
                            <span>{{ $primaryAction['label'] }}</span>
                        </button>
                    @elseif($pIsCreateApt)
                        <button type="button"
                                class="notify-button notify-button--primary notify-button--hero"
                                data-trigger-create-appointment>
                            <span>{{ $primaryAction['label'] }}</span>
                        </button>
                    @elseif($pIsClose)
                        <button type="button"
                                class="notify-button notify-button--primary notify-button--hero"
                                data-trigger-close-client>
                            <span>{{ $primaryAction['label'] }}</span>
                        </button>
                    @elseif($pIsStartSub && \App\Support\Permissions::allows(auth()->user(), \App\Support\Permissions::START_PAID_SUBSCRIPTION))
                        <button type="button"
                                class="notify-button notify-button--primary notify-button--hero"
                                data-trigger-start-subscription>
                            <span>{{ $primaryAction['label'] }}</span>
                        </button>
                    @elseif($pIsRecordPayment && \App\Support\Permissions::allows(auth()->user(), \App\Support\Permissions::RECORD_PAYMENT))
                        <button type="button"
                                class="notify-button notify-button--primary notify-button--hero"
                                data-trigger-record-payment>
                            <span>{{ $primaryAction['label'] }}</span>
                        </button>
                    @elseif(str_starts_with($pUrl, '#'))
                        <a href="{{ $pUrl }}"
                           class="notify-button notify-button--primary notify-button--hero"
                           data-scroll-to="{{ $pUrl }}">
                            <span>{{ $primaryAction['label'] }}</span>
                        </a>
                    @else
                        <a href="{{ $pUrl }}"
                           class="notify-button notify-button--primary notify-button--hero">
                            <span>{{ $primaryAction['label'] }}</span>
                        </a>
                    @endif
                </div>
            @endif

            {{-- 6. Relevant Secondary Actions --}}
            @if(!empty($secondaryActions))
                <div class="notify-workspace-secondary-actions">
                    @foreach($secondaryActions as $secAction)
                        @php
                            $sUrl = $secAction['href'] ?? $secAction['target'] ?? '#';
                            $sType = $secAction['type'] ?? $secAction['key'] ?? '';
                            $sAction = $secAction['action'] ?? '';
                            $sIsCall = ($secAction['action_type'] ?? '') === 'call_outcome'
                                || $sType === 'record_call'
                                || $sAction === 'open_contact_outcome';
                            $sIsResult = $sType === 'record_result';
                            $sIsInstall = $sType === 'complete_installation' || $sUrl === '#sec-complete-installation';
                            $sIsFollowUp = $sType === 'record_followup' || $sUrl === '#sec-follow-ups';
                            $sIsCreateApt = $sType === 'create_appointment' || $sUrl === '#sec-create-appointment';
                            $sIsClose = $sType === 'close_client' || $sUrl === '#sec-close-client';
                            $sIsStartSub = $sType === 'start_subscription' || $sUrl === '#sec-paid-subscriptions' || $sUrl === '#sec-start-subscription';
                            $sIsRecordPayment = $sType === 'record_payment' || $sUrl === '#sec-record-payment';
                        @endphp
                        @if($sIsCall)
                            <button type="button"
                                    class="notify-button notify-button--soft"
                                    data-trigger-call-outcome>
                                <span>{{ $secAction['label'] }}</span>
                            </button>
                        @elseif($sIsResult)
                            <button type="button"
                                    class="notify-button notify-button--soft"
                                    data-trigger-appointment-result>
                                <span>{{ $secAction['label'] }}</span>
                            </button>
                        @elseif($sIsInstall)
                            <button type="button"
                                    class="notify-button notify-button--soft"
                                    data-trigger-installation-modal>
                                <span>{{ $secAction['label'] }}</span>
                            </button>
                        @elseif($sType === 'schedule_installation')
                            <button type="button"
                                    class="notify-button notify-button--soft"
                                    data-trigger-schedule-installation>
                                <span>{{ $secAction['label'] }}</span>
                            </button>
                        @elseif($sType === 'reopen')
                            <button type="button"
                                    class="notify-button notify-button--soft"
                                    data-trigger-reopen-client>
                                <span>{{ $secAction['label'] }}</span>
                            </button>
                        @elseif($sIsFollowUp)
                            <button type="button"
                                    class="notify-button notify-button--soft"
                                    data-trigger-followup-modal>
                                <span>{{ $secAction['label'] }}</span>
                            </button>
                        @elseif($sIsCreateApt)
                            <button type="button"
                                    class="notify-button notify-button--soft"
                                    data-trigger-create-appointment>
                                <span>{{ $secAction['label'] }}</span>
                            </button>
                        @elseif($sIsClose)
                            <button type="button"
                                    class="notify-button notify-button--soft"
                                    data-trigger-close-client>
                                <span>{{ $secAction['label'] }}</span>
                            </button>
                        @elseif($sIsStartSub && \App\Support\Permissions::allows(auth()->user(), \App\Support\Permissions::START_PAID_SUBSCRIPTION))
                            <button type="button"
                                    class="notify-button notify-button--soft"
                                    data-trigger-start-subscription>
                                <span>{{ $secAction['label'] }}</span>
                            </button>
                        @elseif($sIsRecordPayment && \App\Support\Permissions::allows(auth()->user(), \App\Support\Permissions::RECORD_PAYMENT))
                            <button type="button"
                                    class="notify-button notify-button--soft"
                                    data-trigger-record-payment>
                                <span>{{ $secAction['label'] }}</span>
                            </button>
                        @elseif(str_starts_with($sUrl, '#'))
                            <a href="{{ $sUrl }}"
                               class="notify-button notify-button--soft"
                               data-scroll-to="{{ $sUrl }}">
                                <span>{{ $secAction['label'] }}</span>
                            </a>
                        @else
                            <a href="{{ $sUrl }}"
                               class="notify-button notify-button--soft"
                               @if(str_starts_with($sUrl, 'http')) target="_blank" rel="noopener noreferrer" @endif>
                                <span>{{ $secAction['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- Operational Summaries Grid (Sections 7, 8, 9) --}}
    <div class="notify-workspace-summary-grid">
        {{-- 7. Subscriptions by Product (UX-D04) --}}
        <section class="notify-workspace-card notify-workspace-card--subscriptions" aria-label="{{ __('notify.client_workspace.subscriptions_by_product') }}">
            <div class="notify-workspace-card__head">
                <h2 class="notify-workspace-card__title">{{ __('notify.client_workspace.subscriptions_by_product') }}</h2>
            </div>
            <div class="notify-workspace-card__body">
                @if(empty($subscriptionsByProduct))
                    <div class="notify-empty-state-simple">
                        <span class="notify-badge notify-badge--neutral">{{ __('notify.client_workspace.not_subscribed') }}</span>
                        <p class="notify-muted">{{ __('notify.client_workspace.no_subscriptions') }}</p>
                    </div>
                @else
                    <div class="notify-sub-cards-stack">
                        @foreach($subscriptionsByProduct as $sub)
                            <article class="notify-sub-card">
                                <div class="notify-sub-card__header">
                                    <div class="notify-sub-card__titles">
                                        <h3 class="notify-sub-card__product">{{ $sub['product_name'] }}</h3>
                                        <span class="notify-sub-card__plan">{{ $sub['plan_name'] }}</span>
                                    </div>
                                    <span class="notify-badge notify-badge--{{ $sub['status_variant'] }}">
                                        {{ $sub['status_label'] }}
                                    </span>
                                </div>
                                <div class="notify-sub-card__details">
                                    <div class="notify-sub-card__detail-item">
                                        <small>{{ __('notify.catalog.billing_interval') }}</small>
                                        <strong>{{ $sub['term_label'] }}</strong>
                                    </div>
                                    <div class="notify-sub-card__detail-item">
                                        <small>{{ __('notify.client_workspace.next_billing_or_renewal') }}</small>
                                        <strong dir="ltr">{{ $sub['next_date'] ?? '—' }}</strong>
                                    </div>
                                    @if(!empty($sub['balance_label']))
                                        <div class="notify-sub-card__detail-item">
                                            <small>{{ __('notify.client_workspace.outstanding') }}</small>
                                            <strong class="@if(!empty($sub['has_balance'])) notify-text-danger @else notify-text-success @endif">
                                                {{ $sub['balance_label'] }}
                                            </strong>
                                        </div>
                                    @endif
                                    @if(!empty($sub['contract']))
                                        <div class="notify-sub-card__detail-item notify-sub-card__detail-item--contract" style="grid-column:1 / -1;margin-top:6px;padding-top:8px;border-top:1px dashed var(--notify-border, #E2E8F0)">
                                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                                                <div style="display:flex;align-items:center;gap:6px">
                                                    <small style="color:var(--nd-muted, #64748B)">{{ __('notify.client_workspace.contract_number') }}:</small>
                                                    <span class="font-mono text-xs font-semibold" dir="ltr">{{ $sub['contract']['number'] }}</span>
                                                    <span class="notify-badge notify-badge--{{ $sub['contract']['status_variant'] }} notify-badge--sm">{{ $sub['contract']['status_label'] }}</span>
                                                </div>
                                                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                                                    <a href="{{ $sub['contract']['download_pdf_url'] }}" class="notify-button notify-button--ghost notify-button--sm">
                                                        <span>PDF</span>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- 8. Contract Status and Access --}}
        <section class="notify-workspace-card notify-workspace-card--contracts" aria-label="{{ __('notify.client_workspace.contract_status') }}">
            <div class="notify-workspace-card__head">
                <h2 class="notify-workspace-card__title">{{ __('notify.client_workspace.contract_status') }}</h2>
            </div>
            <div class="notify-workspace-card__body">
                @if(empty($contractsAccess))
                    <div class="notify-empty-state-simple">
                        <p class="notify-muted">{{ __('notify.client_workspace.no_contracts') }}</p>
                    </div>
                @else
                    <div class="notify-contracts-list">
                        @foreach($contractsAccess as $contract)
                            <div class="notify-contract-row">
                                <div class="notify-contract-row__info">
                                    <strong>{{ $contract['number'] ?? __('notify.client_workspace.contract_number') }}</strong>
                                    <span class="notify-badge notify-badge--{{ $contract['status_variant'] }}">
                                        {{ $contract['status_label'] }}
                                    </span>
                                </div>
                                <div class="notify-contract-row__actions">
                                    @if(!empty($contract['can_view']) && !empty($contract['preview_url']))
                                        <a href="{{ $contract['preview_url'] }}"
                                           target="_blank"
                                           class="notify-button notify-button--ghost notify-button--sm">
                                            <span>{{ __('notify.client_workspace.view_contract') }}</span>
                                        </a>
                                    @endif
                                    @if(!empty($contract['can_download']))
                                        <a href="{{ $contract['download_pdf_url'] ?? route('contracts.download-pdf', $contract['id']) }}"
                                           class="notify-button notify-button--ghost notify-button--sm">
                                            <span>{{ __('notify.contracts.download_pdf') }}</span>
                                        </a>
                                    @endif
                                    @if(!empty($contract['is_draft']) && \App\Support\Permissions::allows(auth()->user(), \App\Support\Permissions::ISSUE_CONTRACTS))
                                        <form method="POST" action="{{ route('contracts.issue', $contract['id']) }}" style="display:inline" data-issue-contract>
                                            @csrf
                                            <button type="submit" class="notify-button notify-button--primary notify-button--sm">
                                                <span>{{ __('notify.contracts.issue') }}</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- 9. Amount Due Summary & Latest Payment --}}
        <section class="notify-workspace-card notify-workspace-card--due" aria-label="{{ __('notify.client_workspace.amount_due') }}">
            <div class="notify-workspace-card__head"><h2 class="notify-workspace-card__title">{{ __('notify.client_workspace.amount_due') }}</h2></div>
            <div class="notify-workspace-card__body"><div class="notify-amount-due-box">
                <small class="notify-amount-due-box__label">{{ __('notify.client_workspace.total_due') }}</small>
                <div class="notify-amount-due-box__val-row"><span class="notify-amount-due-box__val @if(!empty($amountDue['total_minor']) && $amountDue['total_minor'] > 0) notify-text-danger @else notify-text-success @endif">{{ $amountDue['total_formatted'] }} {{ $amountDue['currency'] }}</span>@if(!empty($amountDue['is_paid']))<span class="notify-badge notify-badge--success">{{ __('notify.client_workspace.paid_in_full') }}</span>@endif</div>
                <div class="notify-latest-payment-box" style="margin-top:14px;padding-top:12px;border-top:1px solid var(--notify-border)"><small class="notify-amount-due-box__label">{{ __('notify.client_workspace.latest_payment') }}</small>@if(!empty($amountDue['latest_payment']))<div style="display:flex;justify-content:space-between;align-items:center"><strong class="notify-text-success">{{ $amountDue['latest_payment']['amount_formatted'] }}</strong><span class="notify-badge notify-badge--neutral" dir="ltr">{{ $amountDue['latest_payment']['paid_at'] }}</span></div><small class="notify-muted">{{ $amountDue['latest_payment']['method'] }} @if(!empty($amountDue['latest_payment']['reference'])) · #{{ $amountDue['latest_payment']['reference'] }} @endif</small>@else<p class="notify-muted">{{ __('notify.client_workspace.no_payments_yet') }}</p>@endif</div>
                @include('clients.workspace.pending-receipts', ['pendingPaymentReceipts' => $pendingPaymentReceipts ?? collect()])
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">@if(!empty($amountDue['total_minor']) && $amountDue['total_minor'] > 0 && !empty($amountDue['can_record_payment']))<button type="button" class="notify-button notify-button--primary notify-button--sm" data-trigger-record-payment>{{ __('notify.client_workspace.action_record_payment') }}</button>@endif @if(!empty($amountDue['can_submit_receipt']))<button type="button" class="notify-button notify-button--primary notify-button--sm" data-trigger-record-payment>{{ __('notify.payment_receipts.action_payment_received') }}</button>@endif @if(!empty($amountDue['can_view_financial_details']))<a href="{{ $amountDue['view_financial_details_url'] ?? route('finance.collections', ['client_id' => $client->id]) }}" class="notify-button notify-button--ghost notify-button--sm">{{ __('notify.client_workspace.view_financial_details') }} →</a>@endif</div>
            </div></div>
        </section>
    </div>

    {{-- System credentials (P5 minimal surface; final card design in P10) --}}
    @include('clients.workspace.credentials')

    @if(\App\Support\Permissions::allows(auth()->user(), \App\Support\Permissions::MANAGE_COMMERCIAL_CATALOG))
        <section class="notify-workspace-card" style="margin-block:16px" aria-label="{{ __('custom_projects.title') }}">
            <div class="notify-workspace-card__head"><h2 class="notify-workspace-card__title">{{ __('custom_projects.title') }}</h2><a class="notify-button notify-button--soft notify-button--sm" href="{{ route('custom-projects.create', ['client_id' => $client->id]) }}">{{ __('custom_projects.create') }}</a></div>
            <div class="notify-workspace-card__body">
                @forelse($customProjects as $project)<div style="display:flex;gap:12px;justify-content:space-between;flex-wrap:wrap;margin-block:8px"><a href="{{ route('custom-projects.show', $project) }}">{{ $project->name }}</a><span>{{ $project->statusLabel() }} · {{ $project->agreedValueFormatted() }} {{ __('notify.common.currency_jod') }}</span></div>@empty<p class="notify-muted">{{ __('custom_projects.empty') }}</p>@endforelse
                <a href="{{ route('clients.custom-projects.index', $client) }}">{{ __('custom_projects.view_all') }}</a>
            </div>
        </section>
    @endif

    {{-- 10. Last 3 Activities --}}
    <section class="notify-workspace-card notify-workspace-card--recent-activity" aria-label="{{ __('notify.client_workspace.recent_activity') }}">
        <div class="notify-workspace-card__head">
            <h2 class="notify-workspace-card__title">{{ __('notify.client_workspace.recent_activity') }}</h2>
        </div>
        <div class="notify-workspace-card__body">
            @if(empty($recentActivities))
                <div class="notify-empty-state-simple">
                    <p class="notify-muted">{{ __('notify.client_workspace.no_recent_activity') }}</p>
                </div>
            @else
                <div class="notify-recent-activities-list">
                    @foreach($recentActivities as $act)
                        <div class="notify-recent-activity-row">
                            <span class="notify-recent-activity-bullet"></span>
                            <div class="notify-recent-activity-main">
                                <div class="notify-recent-activity-header">
                                    <strong class="notify-recent-activity-title">{{ $act['description'] }}</strong>
                                    <span class="notify-recent-activity-time" dir="ltr">{{ $act['at'] }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- ADVANCED COLLAPSED SECTIONS (11, 12, 13) --}}
    <div class="notify-workspace-advanced-stack">
        @if(request()->boolean('finance_advanced') && \App\Support\Permissions::allows(auth()->user(), \App\Support\Permissions::VIEW_ACCOUNTING))
            <x-notify.collapsible-section
                :title="__('notify.finance.sections.advanced')"
                id="finance-advanced-tools"
                :open="true">
                @include('clients.workspace.subscription-billing')
            </x-notify.collapsible-section>
        @endif
        {{-- 11. Expandable Full History (Collapsed by default) --}}
        <x-notify.collapsible-section
            :title="__('notify.client_workspace.full_history')"
            id="collapsible-history"
            :open="false">
            @include('clients.workspace.history')
        </x-notify.collapsible-section>

        {{-- 12. Expandable Details (Collapsed by default) --}}
        <x-notify.collapsible-section
            :title="__('notify.client_workspace.details_section')"
            id="collapsible-details"
            :open="false">
            @include('clients.workspace.details')
        </x-notify.collapsible-section>

        {{-- 13. Permissioned Management & Finance (Collapsed by default) --}}
        <x-notify.collapsible-section
            :title="__('notify.client_workspace.management_finance')"
            id="collapsible-management"
            :open="false">
            @include('clients.workspace.management-finance')
        </x-notify.collapsible-section>
    </div>
</div>

{{-- Phase 05 Focused Operational Action Dialogs / Sheets --}}
@include('clients.workspace.actions.record-call', [
    'client' => $client,
    'teamUsers' => $teamUsers,
    'appointmentTypeLabels' => $appointmentTypeLabels ?? [],
])

@include('clients.workspace.actions.create-appointment', [
    'client' => $client,
    'teamUsers' => $teamUsers,
    'appointmentTypeLabels' => $appointmentTypeLabels ?? [],
])

@include('clients.workspace.actions.appointment-result', [
    'client' => $client,
    'activeAppointment' => $appointments->where('appointment_type', '!=', \App\Support\AppointmentTypes::INSTALLATION)->whereIn('status', \App\Support\AppointmentTypes::activeStatuses())->first(),
])

@include('clients.workspace.actions.schedule-installation', [
    'client' => $client,
    'teamUsers' => $teamUsers,
])

@include('clients.workspace.actions.complete-installation', [
    'client' => $client,
    'catalogServices' => $catalogServices,
    'eligibleInstallationAppointment' => $appointments->where('appointment_type', \App\Support\AppointmentTypes::INSTALLATION)->whereIn('status', \App\Support\AppointmentTypes::activeStatuses())->first(),
])

@include('clients.workspace.actions.follow-up', [
    'client' => $client,
    'activeFollowUp' => $followUps->firstWhere('completed_at', null) ?? $followUps->first(),
    'appointmentTypeLabels' => $appointmentTypeLabels ?? [],
])

@include('clients.workspace.actions.close-client', [
    'client' => $client,
])

@include('clients.workspace.actions.reopen-client', [
    'client' => $client,
])

@include('clients.workspace.actions.record-payment', [
    'client' => $client,
    'amountDue' => $amountDue,
    'paymentMethodOptions' => $paymentMethodOptions ?? [],
])

@include('clients.workspace.actions.start-subscription', [
    'client' => $client,
    'sellableProducts' => $sellableProducts,
])

@include('clients.workspace.workspace-scripts')
@endsection
