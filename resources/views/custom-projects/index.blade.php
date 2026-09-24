@extends('layouts.app')

@section('content')
<div class="p5-wrap">
    <x-notify.page-header :title="__('custom_projects.title')" :description="__('custom_projects.subtitle')">
        @can('manage_custom_projects')
            <x-slot:actions>
                <x-notify.button :href="route('custom-projects.create')" variant="primary" icon="plus" data-page-action="create-custom-project">{{ __('custom_projects.create') }}</x-notify.button>
            </x-slot:actions>
        @endcan
    </x-notify.page-header>
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
