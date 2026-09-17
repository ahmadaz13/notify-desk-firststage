@extends('layouts.app')

@section('content')
@php
    $workspace = $clientWorkspaceViewModel;
    $header = $workspace->header;
@endphp

<div class="notify-client-workspace">
    <header class="notify-workspace-header">
        <div class="notify-workspace-header__main">
            <p class="notify-eyebrow">
                <a href="{{ route('clients.index') }}">{{ __('notify.clients.title') }}</a> / ملف العميل #{{ $header['id'] }}
            </p>
            <div class="notify-workspace-title-row">
                <h1>{{ $header['business_name'] }}</h1>
                <span class="notify-badge notify-badge--{{ $header['stage_variant'] }}">{{ $header['stage_label'] }}</span>
            </div>
            <p class="notify-workspace-subtitle">{{ $header['subtitle'] }}</p>
            @if($header['next_action'])
                <div class="notify-next-action">
                    <strong>{{ $header['next_action']['label'] }}</strong>
                    @if($header['next_action']['at'])
                        <span>{{ $header['next_action']['at'] }}</span>
                    @endif
                </div>
            @endif
        </div>

        <div class="notify-workspace-header__actions">
            @if($header['call_href'])
                <a class="notify-icon-action" href="{{ $header['call_href'] }}" aria-label="اتصال هاتفي" title="اتصال هاتفي">☎</a>
            @endif
            @if($header['whatsapp_url'])
                <a class="notify-icon-action notify-icon-action--success" href="{{ $header['whatsapp_url'] }}" target="_blank" aria-label="واتساب" title="واتساب">WA</a>
            @endif
            @if($header['can_convert'])
                <button class="notify-button notify-button--primary" type="button" onclick="switchTab('billing');document.getElementById('convert-section')?.scrollIntoView({behavior:'smooth'})">
                    <span class="notify-button__label">{{ __('notify.clients.convert_to_subscriber') }}</span>
                </button>
            @endif
            <a class="notify-button notify-button--ghost" href="{{ route('clients.index') }}">
                <span class="notify-button__label">{{ __('notify.clients.back_to_list') }}</span>
            </a>
        </div>
    </header>

    <section class="notify-workspace-metrics" aria-label="Client workspace summary">
        @foreach($workspace->metrics as $metric)
            <article class="notify-metric-tile">
                <small>{{ $metric['label'] }}</small>
                <strong>{{ $metric['value'] }}</strong>
                <span>{{ $metric['meta'] }}</span>
            </article>
        @endforeach
    </section>

    <x-notify.workspace-nav :sections="$workspace->sections" />

    <main class="notify-workspace-body">
        @include('clients.workspace.overview')
        @include('clients.workspace.contacts')
        @include('clients.workspace.timeline')
        @include('clients.workspace.appointments')
        @include('clients.workspace.installation-followup')
        @include('clients.workspace.subscription-billing')
        @include('clients.workspace.notes')
    </main>
</div>

@include('clients.workspace.workspace-scripts')
@endsection
