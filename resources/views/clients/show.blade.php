@extends('layouts.app')

{{--
    Client Workspace (P10, §19). One vertical card stack on phone (single column, in §19 order);
    from 1024px a main operational column plus a compact secondary column.
--}}
@section('content')
<div class="notify-workspace" data-client-workspace data-client-stage="{{ $workspace->header['stage'] }}">
    @if(session('lastStartedSubscriptionId'))
        <p class="notify-flash" role="status">{{ __('notify.subscriptions.activated_success') }}</p>
    @endif

    @include('clients.workspace.header')

    <div class="notify-workspace__layout">
        <div class="notify-workspace__main">
            <div class="notify-workspace__slot notify-workspace__slot--state">@include('clients.workspace.state')</div>
            <div class="notify-workspace__slot notify-workspace__slot--subscription">@include('clients.workspace.subscription')</div>
            <div class="notify-workspace__slot notify-workspace__slot--systems">@include('clients.workspace.systems')</div>
            <div class="notify-workspace__slot notify-workspace__slot--activity">@include('clients.workspace.activity')</div>
        </div>
        <aside class="notify-workspace__side" aria-label="{{ __('notify.client_hub.details.title') }}">
            <div class="notify-workspace__slot notify-workspace__slot--money">@include('clients.workspace.money')</div>
            <div class="notify-workspace__slot notify-workspace__slot--contract">@include('clients.workspace.contract')</div>
            <div class="notify-workspace__slot notify-workspace__slot--details">@include('clients.workspace.details')</div>
        </aside>
    </div>
</div>

{{-- Focused action sheets: the existing workflow forms, opened from the state card or ?open= / Today links. --}}
@if($workspace->can['update'])
    @include('clients.workspace.actions.record-call', ['client' => $client, 'teamUsers' => $teamUsers, 'appointmentTypeLabels' => $appointmentTypeLabels])
    @include('clients.workspace.actions.create-appointment', ['client' => $client, 'teamUsers' => $teamUsers, 'appointmentTypeLabels' => $appointmentTypeLabels])
    @if($workspace->sheets['meeting'])
        @include('clients.workspace.actions.appointment-result', ['client' => $client, 'activeAppointment' => $workspace->sheets['meeting']])
    @endif
    @include('clients.workspace.actions.schedule-installation', ['client' => $client, 'teamUsers' => $teamUsers])
    @if($workspace->sheets['installation'])
        @include('clients.workspace.actions.complete-installation', ['client' => $client, 'catalogServices' => $catalogServices, 'eligibleInstallationAppointment' => $workspace->sheets['installation']])
    @endif
    @if($workspace->sheets['follow_up'])
        @include('clients.workspace.actions.follow-up', ['client' => $client, 'activeFollowUp' => $workspace->sheets['follow_up'], 'appointmentTypeLabels' => $appointmentTypeLabels])
    @endif
    @include('clients.workspace.actions.close-client', ['client' => $client])
    @include('clients.workspace.actions.reopen-client', ['client' => $client])
@endif
@include('clients.workspace.actions.record-payment', ['client' => $client, 'amountDue' => $amountDue, 'paymentMethodOptions' => $paymentMethodOptions])
@include('clients.workspace.actions.start-subscription', ['client' => $client, 'sellableProducts' => $sellableProducts])

{{-- Shared confirmation for destructive inline actions (replaces browser confirm()). --}}
<div class="notify-modal-backdrop" id="modal-confirm" hidden>
    <div class="notify-modal-card notify-action-sheet notify-action-sheet--compact" role="alertdialog" aria-modal="true" aria-labelledby="modal-confirm-text">
        <p class="notify-confirm__text" id="modal-confirm-text" data-confirm-text></p>
        <div class="notify-modal-footer">
            <button type="button" class="notify-button notify-button--ghost" data-close-action-modal>{{ __('notify.client_hub.actions.cancel') }}</button>
            <button type="button" class="notify-button notify-button--danger" data-confirm-accept>{{ __('notify.actions.confirm') }}</button>
        </div>
    </div>
</div>

@include('clients.workspace.workspace-scripts', ['openSheet' => $openSheet])
@endsection
