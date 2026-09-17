@extends('layouts.app')

@section('content')
<div class="notify-page-head">
    <div>
        <div class="eyebrow">CLIENTS_01</div>
        <h1 class="page-title">{{ __('notify.clients.title') }}</h1>
        <p class="notify-page-lede">{{ __('notify.clients.subtitle') }}</p>
    </div>
</div>

<section class="notify-filter-panel">
    <form method="GET" class="notify-filter-grid">
        <div class="field">
            <label for="client-search">{{ __('notify.actions.search') }}</label>
            <input id="client-search" name="q" value="{{ $clientListViewModel->filters['q'] }}" placeholder="{{ __('notify.clients.search_placeholder') }}">
        </div>
        <div class="field">
            <label for="client-stage">{{ __('notify.clients.stage') }}</label>
            <select id="client-stage" name="status">
                @foreach($clientListViewModel->stageOptions as $stageOption)
                    <option value="{{ $stageOption['value'] }}" @selected($clientListViewModel->filters['status'] === $stageOption['value'])>
                        {{ $stageOption['label'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="notify-filter-actions">
            <x-notify.button type="submit" variant="secondary" icon="activity">{{ __('notify.actions.apply') }}</x-notify.button>
            @if($clientListViewModel->filters['q'] !== '' || $clientListViewModel->filters['status'] !== 'all')
                <x-notify.button :href="route('clients.index')" variant="ghost">{{ __('notify.actions.reset') }}</x-notify.button>
            @endif
        </div>
    </form>
</section>

@if($clientListViewModel->rows === [])
    <x-notify.empty-state
        title="{{ $clientListViewModel->filters['q'] !== '' || $clientListViewModel->filters['status'] !== 'all' ? __('notify.clients.no_matching_clients') : __('notify.clients.no_clients') }}"
        message="{{ $clientListViewModel->filters['q'] !== '' || $clientListViewModel->filters['status'] !== 'all' ? __('notify.clients.no_matching_message') : __('notify.clients.no_clients_message') }}"
        :action-href="route('clients.create')"
        action-label="+ {{ __('notify.clients.create_title') }}"
        icon="users"
    />
@else
    <section class="notify-client-desktop table-wrap" aria-label="Client list">
        <table class="notify-client-table">
            <thead>
                <tr>
                    <th>{{ __('notify.clients.business_name') }}</th>
                    <th>{{ __('notify.clients.business_type') }}</th>
                    <th>{{ __('notify.clients.phone') }}</th>
                    <th>{{ __('notify.clients.stage') }}</th>
                    <th>{{ __('notify.clients.next_action') }}</th>
                    <th>{{ __('notify.clients.responsible') }}</th>
                    <th>{{ __('notify.clients.last_activity') }}</th>
                    <th class="notify-client-table__actions">{{ __('notify.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($clientListViewModel->rows as $client)
                    <tr>
                        <td>
                            <a class="notify-client-link" href="{{ $client['href'] }}">
                                <span class="notify-avatar">{{ $client['initial'] }}</span>
                                <span>
                                    <strong>{{ $client['business_name'] }}</strong>
                                    @if($client['location'])
                                        <small>{{ $client['location'] }}</small>
                                    @endif
                                </span>
                            </a>
                        </td>
                        <td>{{ $client['business_type'] }}</td>
                        <td>
                            @if($client['business_phone'])
                                <span dir="ltr">{{ $client['business_phone'] }}</span>
                            @else
                                <span class="muted">No phone</span>
                            @endif
                        </td>
                        <td><x-notify.badge :variant="$client['stage_variant']">{{ $client['stage_label'] }}</x-notify.badge></td>
                        <td>
                            <strong>{{ $client['next_action']['label'] }}</strong>
                            @if($client['next_action']['at'])
                                <small>{{ $client['next_action']['at'] }}</small>
                            @endif
                        </td>
                        <td>{{ $client['responsible_user'] }}</td>
                        <td>{{ $client['last_activity'] ?? 'No activity yet' }}</td>
                        <td>
                            <div class="notify-row-actions">
                                @if($client['call_href'])
                                    <x-notify.button :href="$client['call_href']" variant="ghost" icon="activity" hide-label-on-mobile="true">{{ __('notify.actions.call') }}</x-notify.button>
                                @endif
                                @if($client['whatsapp_url'])
                                    <x-notify.button :href="$client['whatsapp_url']" variant="secondary" icon="bell" target="_blank" rel="noopener" hide-label-on-mobile="true">WhatsApp</x-notify.button>
                                @endif
                                <x-notify.button :href="$client['href']" variant="primary" icon="building" hide-label-on-mobile="true">{{ __('notify.actions.open') }}</x-notify.button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="notify-client-mobile" aria-label="Client cards">
        @foreach($clientListViewModel->rows as $client)
            <x-notify.client-card :client="$client" />
        @endforeach
    </section>

    <div class="notify-pagination">
        {{ $clientListViewModel->clients->links() }}
    </div>
@endif
@endsection
