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

    {{-- SECTION 1, 2, 3, 4, 5, 6: Operational Command Card (Hero) --}}
    <section class="notify-workspace-command-card" aria-label="Command Overview">
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
                               aria-label="{{ __('notify.action_call') }}"
                               title="{{ __('notify.action_call') }}">
                                <span class="notify-icon-symbol">📞</span>
                                <span>{{ __('notify.action_call') }}</span>
                            </a>
                        @endif
                        @if(!empty($prefContact['whatsapp_url']))
                            <a class="notify-button notify-button--soft notify-button--sm notify-contact-action notify-contact-action--whatsapp"
                               href="{{ $prefContact['whatsapp_url'] }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               aria-label="{{ __('notify.action_whatsapp') }}"
                               title="{{ __('notify.action_whatsapp') }}">
                                <span class="notify-icon-symbol">💬</span>
                                <span>{{ __('notify.action_whatsapp') }}</span>
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
                    <span class="notify-workspace-next-action-badge">{{ __('notify.next_action') }}</span>
                    <strong class="notify-workspace-next-action-label">{{ $nextAction['label'] }}</strong>
                    @if(!empty($nextAction['at']))
                        <span class="notify-workspace-next-action-time" dir="ltr">⏱ {{ $nextAction['at'] }}</span>
                    @endif
                    @if(!empty($nextAction['context']))
                        <p class="notify-workspace-next-action-context">{{ $nextAction['context'] }}</p>
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
                    $pIsCall = ($primaryAction['action_type'] ?? '') === 'call_outcome'
                        || ($primaryAction['key'] ?? '') === 'record_call'
                        || ($primaryAction['action'] ?? '') === 'open_contact_outcome'
                        || ($primaryAction['type'] ?? '') === 'record_call';
                @endphp
                <div class="notify-workspace-primary-action">
                    @if($pIsCall)
                        <button type="button"
                                class="notify-button notify-button--primary notify-button--hero"
                                data-trigger-call-outcome>
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
                            $sIsCall = ($secAction['action_type'] ?? '') === 'call_outcome'
                                || ($secAction['key'] ?? '') === 'record_call'
                                || ($secAction['action'] ?? '') === 'open_contact_outcome'
                                || ($secAction['type'] ?? '') === 'record_call';
                        @endphp
                        @if($sIsCall)
                            <button type="button"
                                    class="notify-button notify-button--soft"
                                    data-trigger-call-outcome>
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
        <section class="notify-workspace-card notify-workspace-card--subscriptions" aria-label="Subscriptions Summary">
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
                                        <div class="notify-sub-card__detail-item">
                                            <small>{{ __('notify.client_workspace.contract_access_title') }}</small>
                                            <span>{{ $sub['contract']['status_label'] }}</span>
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- 8. Amount Due Summary (ReceivableService) --}}
        <section class="notify-workspace-card notify-workspace-card--due" aria-label="Amount Due Summary">
            <div class="notify-workspace-card__head">
                <h2 class="notify-workspace-card__title">{{ __('notify.client_workspace.amount_due') }}</h2>
            </div>
            <div class="notify-workspace-card__body">
                <div class="notify-amount-due-box">
                    <small class="notify-amount-due-box__label">{{ __('notify.client_workspace.total_due') }}</small>
                    <div class="notify-amount-due-box__val-row">
                        <span class="notify-amount-due-box__val @if(!empty($amountDue['total_minor']) && $amountDue['total_minor'] > 0) notify-text-danger @else notify-text-success @endif">
                            {{ $amountDue['total_formatted'] }} {{ $amountDue['currency'] }}
                        </span>
                        @if(!empty($amountDue['is_paid']))
                            <span class="notify-badge notify-badge--success">{{ __('notify.client_workspace.paid_in_full') }}</span>
                        @endif
                    </div>

                    @if(!empty($amountDue['total_minor']) && $amountDue['total_minor'] > 0 && !empty($amountDue['can_record_payment']))
                        <div class="notify-amount-due-box__action">
                            <a href="#sec-record-payment"
                               class="notify-button notify-button--primary notify-button--sm"
                               data-scroll-to="#sec-record-payment">
                                <span>{{ __('notify.client_workspace.action_record_payment') }}</span>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- 9. Contract Status and Access --}}
        <section class="notify-workspace-card notify-workspace-card--contracts" aria-label="Contracts Summary">
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
                                    @if(!empty($contract['can_download']) && !empty($contract['download_url']))
                                        <a href="{{ $contract['download_url'] }}"
                                           class="notify-button notify-button--ghost notify-button--sm">
                                            <span>{{ __('notify.client_workspace.download_contract') }}</span>
                                        </a>
                                    @endif
                                    @if(!empty($contract['print_url']))
                                        <a href="{{ $contract['print_url'] }}"
                                           target="_blank"
                                           class="notify-button notify-button--ghost notify-button--sm">
                                            <span>{{ __('notify.client_workspace.print_contract') }}</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>

    {{-- 10. Last 3 Activities --}}
    <section class="notify-workspace-card notify-workspace-card--recent-activity" aria-label="Recent Activities">
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

@include('clients.workspace.workspace-scripts')
@endsection
