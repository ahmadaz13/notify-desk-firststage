@extends('layouts.app')

@section('content')
<div class="p5-wrap">
    <header class="p5-header">
        <div class="p5-header-main">
            <h1 class="p5-title">{{ __('custom_projects.title') }}</h1>
            <p class="p5-subtitle">{{ __('custom_projects.subtitle') }}</p>
        </div>
        @can('manage_custom_projects')
        <a href="{{ route('custom-projects.create') }}" class="p5-btn p5-btn-primary">{{ __('custom_projects.create') }}</a>
        @endcan
    </header>
    <nav style="display:flex;gap:8px;flex-wrap:wrap;margin:16px 0" aria-label="{{ __('custom_projects.status_label') }}">
        @foreach(['all', ...\App\Models\CustomProject::STATUSES] as $key)
            <a class="p5-btn {{ $status === $key ? 'p5-btn-primary' : 'p5-btn-ghost' }}" href="{{ route('custom-projects.index', ['status' => $key]) }}">{{ __('custom_projects.status.'.$key) }}</a>
        @endforeach
    </nav>
    @forelse($projects as $project)
        <div class="p5-card" style="margin-block:10px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div>
                <a href="{{ route('custom-projects.show', $project) }}"><strong>{{ $project->name }}</strong></a>
                <div><a href="{{ route('clients.show', $project->client_id) }}">{{ $project->client->business_name }}</a></div>
                <small>{{ $project->statusLabel() }} · {{ $project->agreedValueFormatted() }} {{ __('notify.common.currency_jod') }}</small>
            </div>
            <a href="{{ route('custom-projects.show', $project) }}" class="p5-btn p5-btn-ghost">{{ __('custom_projects.view') }}</a>
        </div>
    @empty
        <p class="p5-card">{{ __('custom_projects.empty') }}</p>
    @endforelse
    {{ $projects->links() }}
</div>
@endsection
