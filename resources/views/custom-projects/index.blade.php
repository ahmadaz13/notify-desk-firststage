@extends('layouts.app')

{{-- Custom Projects (§20a, D-14, P13): top-level one-time work, outside MRR/ARR. Staff: read-only. --}}
@php
    $canManage = \App\Support\Permissions::allows(auth()->user(), \App\Support\Permissions::MANAGE_CUSTOM_PROJECTS);
    $statusTone = ['planned' => 'neutral', 'active' => 'info', 'completed' => 'success', 'cancelled' => 'neutral'];
    $baseQuery = array_filter(['status' => $status !== 'all' ? $status : null, 'q' => $search !== '' ? $search : null]);
@endphp

@section('content')
<div class="notify-admin" data-admin-page="custom-projects">
    <x-notify.page-header :title="__('custom_projects.title')" :description="__('custom_projects.subtitle')">
        <x-slot:actions>
            @if($canManage)
                <x-notify.button :href="route('custom-projects.create')" variant="primary" icon="plus" data-page-action="create-custom-project">{{ __('custom_projects.create') }}</x-notify.button>
            @else
                <x-notify.badge variant="neutral" icon="lock" data-read-only>{{ __('custom_projects.read_only') }}</x-notify.badge>
            @endif
        </x-slot:actions>
    </x-notify.page-header>

    <nav class="notify-segments" aria-label="{{ __('custom_projects.status_label') }}" data-project-status-filter>
        @foreach(['all', ...\App\Models\CustomProject::STATUSES] as $key)
            <a href="{{ route('custom-projects.index', array_filter(['status' => $key !== 'all' ? $key : null, 'q' => $search !== '' ? $search : null])) }}"
               class="notify-segments__item {{ $status === $key ? 'is-active' : '' }}" @if($status === $key) aria-current="page" @endif>
                <span>{{ __('custom_projects.status.'.$key) }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('custom-projects.index') }}" role="search" class="notify-admin-search">
        @if($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
        <x-notify.search id="project-search" :value="$search" :label="__('custom_projects.search_label')" :placeholder="__('custom_projects.search_placeholder')"
            :clear-href="route('custom-projects.index', \Illuminate\Support\Arr::except($baseQuery, 'q'))" />
        <button type="submit" class="notify-visually-hidden">{{ __('notify.ui.search') }}</button>
    </form>

    <x-notify.list data-project-list :label="__('custom_projects.title')" :empty="$projects->isEmpty()"
        :columns="[__('custom_projects.columns.project'), __('custom_projects.columns.status'), ['label' => __('custom_projects.columns.value'), 'class' => 'is-num'], __('custom_projects.columns.invoicing'), ['label' => __('notify.common.actions'), 'hidden' => true, 'class' => 'is-actions']]">
        @foreach($projects as $project)
            <tr @class(['is-muted' => $project->archived_at]) data-project="{{ $project->id }}">
                <td class="notify-list__primary">
                    <a class="notify-list__title" href="{{ route('custom-projects.show', $project) }}">{{ $project->name }}</a>
                    <span class="notify-list__sub">
                        @if($project->client)
                            {{ $project->client->business_name }}
                        @else
                            <span class="notify-admin-muted" data-no-client>{{ __('custom_projects.no_client') }}</span>
                        @endif
                        @if($project->target_completion_date)
                            · {{ __('custom_projects.target_short') }} <span dir="ltr">{{ $project->target_completion_date->toDateString() }}</span>
                        @endif
                    </span>
                </td>
                <td class="notify-list__end">
                    <span class="notify-status notify-status--{{ $statusTone[$project->status] ?? 'neutral' }}">{{ $project->statusLabel() }}</span>
                    @if($project->archived_at)<span class="notify-status notify-status--neutral">{{ __('custom_projects.archived_status') }}</span>@endif
                </td>
                <td class="is-num" data-label="{{ __('custom_projects.columns.value') }}">@if($project->agreed_value_minor > 0)<x-notify.money :minor="(int) $project->agreed_value_minor" />@endif</td>
                <td data-label="{{ __('custom_projects.columns.invoicing') }}">
                    @if($project->invoices_count > 0)
                        <span class="notify-status notify-status--info">{{ trans_choice('custom_projects.invoice_count', $project->invoices_count, ['count' => $project->invoices_count]) }}</span>
                    @elseif(! $project->client_id)
                        <span class="notify-admin-muted notify-admin-small">{{ __('custom_projects.needs_client_short') }}</span>
                    @else
                        <span class="notify-admin-muted notify-admin-small">{{ __('custom_projects.not_invoiced') }}</span>
                    @endif
                </td>
                <td class="notify-list__actions">
                    <a class="notify-button notify-button--secondary notify-button--sm" href="{{ route('custom-projects.show', $project) }}">{{ __('custom_projects.view') }}</a>
                </td>
            </tr>
        @endforeach
        <x-slot:emptyState>
            <x-notify.empty-state :compact="true" icon="folder-kanban"
                :title="$search !== '' || $status !== 'all' ? __('custom_projects.empty_filtered') : __('custom_projects.empty')"
                :action-href="$canManage && $search === '' && $status === 'all' ? route('custom-projects.create') : null"
                :action-label="$canManage ? __('custom_projects.create') : null" />
        </x-slot:emptyState>
        @if($projects->hasPages())
            <x-slot:footer>{{ $projects->links() }}</x-slot:footer>
        @endif
    </x-notify.list>
</div>
@endsection
