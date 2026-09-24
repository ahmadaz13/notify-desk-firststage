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

    @if(in_array(request('from'), ['today', 'work'], true))
        {{-- Opened from a Today card (P11): one tap back to the same queue. --}}
        <a class="notify-back-link" href="{{ route('dashboard', array_filter(['mode' => request('from') === 'work' ? 'work' : null, 'scope' => request('scope') === 'my' ? 'my' : null])) }}" data-return-to-today>
            <x-notify.icon name="chevron-left" :size="18" class="notify-icon--directional" />
            <span>{{ __('notify.today_board.back_to_today') }}</span>
        </a>
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
    {{-- Settings → Operations: default times in scheduling forms start at workday_start (§16). --}}
    @php($operations = \App\Support\OperationalSettings::current())
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

{{-- Destructive inline actions use the shared confirmation dialog in the shell (P12). --}}
@include('clients.workspace.workspace-scripts', ['openSheet' => $openSheet])
@endsection
