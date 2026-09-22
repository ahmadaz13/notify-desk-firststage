@extends('layouts.app')

@section('content')
<div class="p5-wrap">
    <header class="p5-header">
        <div class="p5-header-main">
            <a href="{{ route('clients.show', $client) }}">{{ $client->business_name }}</a>
            <h1 class="p5-title">{{ __('custom_projects.title') }}</h1>
        </div>
        <a href="{{ route('custom-projects.create', ['client_id' => $client->id]) }}" class="p5-btn p5-btn-primary">{{ __('custom_projects.create') }}</a>
    </header>
    @forelse($projects as $project)
        <div class="p5-card" style="margin-block:10px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div><a href="{{ route('custom-projects.show', $project) }}"><strong>{{ $project->name }}</strong></a>
                <div>{{ $project->statusLabel() }} · {{ $project->agreedValueFormatted() }} {{ __('notify.common.currency_jod') }}</div>
            </div>
            <a href="{{ route('custom-projects.show', $project) }}" class="p5-btn p5-btn-ghost">{{ __('custom_projects.view') }}</a>
        </div>
    @empty
        <p class="p5-card">{{ __('custom_projects.empty') }}</p>
    @endforelse
</div>
@endsection
