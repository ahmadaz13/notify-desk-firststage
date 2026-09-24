@extends('layouts.app')

{{-- Contextual list of a client's Custom Projects (reached from the P10 workspace count link). --}}
@php
    $canManage = \App\Support\Permissions::allows(auth()->user(), \App\Support\Permissions::MANAGE_CUSTOM_PROJECTS);
    $statusTone = ['planned' => 'neutral', 'active' => 'info', 'completed' => 'success', 'cancelled' => 'neutral'];
@endphp

@section('content')
<div class="notify-admin" data-admin-page="client-custom-projects">
    <a class="notify-admin-back" href="{{ route('clients.show', $client) }}"><x-notify.icon name="chevron-right" :size="16" class="notify-admin-back__icon" />{{ $client->business_name }}</a>
    <x-notify.page-header :title="__('custom_projects.client_projects_title', ['client' => $client->business_name])">
        @if($canManage)
            <x-slot:actions>
                <x-notify.button :href="route('custom-projects.create', ['client_id' => $client->id])" variant="primary" icon="plus">{{ __('custom_projects.create') }}</x-notify.button>
            </x-slot:actions>
        @endif
    </x-notify.page-header>

    <x-notify.list :label="__('custom_projects.title')" :empty="$projects->isEmpty()"
        :columns="[__('custom_projects.columns.project'), __('custom_projects.columns.status'), ['label' => __('custom_projects.columns.value'), 'class' => 'is-num'], ['label' => __('notify.common.actions'), 'hidden' => true, 'class' => 'is-actions']]">
        @foreach($projects as $project)
            <tr @class(['is-muted' => $project->archived_at])>
                <td class="notify-list__primary">
                    <a class="notify-list__title" href="{{ route('custom-projects.show', $project) }}">{{ $project->name }}</a>
                    @if($project->invoices_count > 0)<span class="notify-list__sub">{{ trans_choice('custom_projects.invoice_count', $project->invoices_count, ['count' => $project->invoices_count]) }}</span>@endif
                </td>
                <td class="notify-list__end"><span class="notify-status notify-status--{{ $statusTone[$project->status] ?? 'neutral' }}">{{ $project->statusLabel() }}</span></td>
                <td class="is-num" data-label="{{ __('custom_projects.columns.value') }}">@if($project->agreed_value_minor > 0)<x-notify.money :minor="(int) $project->agreed_value_minor" />@endif</td>
                <td class="notify-list__actions"><a class="notify-button notify-button--secondary notify-button--sm" href="{{ route('custom-projects.show', $project) }}">{{ __('custom_projects.view') }}</a></td>
            </tr>
        @endforeach
        <x-slot:emptyState>
            <x-notify.empty-state :compact="true" icon="folder-kanban" :title="__('custom_projects.empty')" />
        </x-slot:emptyState>
    </x-notify.list>
</div>
@endsection
