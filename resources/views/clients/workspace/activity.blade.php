{{-- H. Activity (§19.11, §21): newest first, human wording only (no codes, metadata, ids or secrets). --}}
@php($activity = $workspace->activity)
<section class="notify-card notify-ws-card" aria-labelledby="client-activity-title" id="activity" data-client-activity>
    <header class="notify-ws-card__head">
        <h2 class="notify-ws-card__title" id="client-activity-title"><x-notify.icon name="activity" :size="18" /> {{ __('notify.client_hub.activity.title') }}</h2>
    </header>
    @if($activity['items'])
        <ol class="notify-timeline">
            @foreach($activity['items'] as $item)
                <li class="notify-timeline__item" data-activity-item>
                    <span class="notify-timeline__icon" aria-hidden="true"><x-notify.icon :name="$item['icon']" :size="14" /></span>
                    <div class="notify-timeline__body">
                        <p class="notify-timeline__title">{{ $item['title'] }}</p>
                        @if($item['description'])
                            <p class="notify-timeline__detail">{{ $item['description'] }}</p>
                        @endif
                        <p class="notify-timeline__meta">
                            <time datetime="{{ $item['at']->toIso8601String() }}" dir="ltr">{{ $item['when'] }}</time>
                            @if($item['actor']) · {{ __('notify.client_hub.activity.by', ['name' => $item['actor']]) }}@endif
                        </p>
                    </div>
                </li>
            @endforeach
        </ol>
        @if($activity['has_more'])
            <a class="notify-ws-card__link" href="{{ route('clients.show', ['client' => $client->id, 'activity' => 'all']) }}#activity" data-activity-all>{{ __('notify.client_hub.actions.view_all') }}</a>
        @elseif($activity['showing_all'])
            <a class="notify-ws-card__link" href="{{ route('clients.show', $client->id) }}#activity">{{ __('notify.client_hub.actions.show_recent') }}</a>
        @endif
    @else
        <p class="notify-ws-card__empty">{{ __('notify.client_hub.activity.empty') }}</p>
    @endif
</section>
