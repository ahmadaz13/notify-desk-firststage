@props([
    'queue',
])

@php
    $variant = $queue['variant'] ?? 'neutral';
    $queueKey = $queue['key'] ?? null;
    $titleKey = $queueKey ? 'notify.today.queues.'.$queueKey.'.title' : null;
    $descriptionKey = $queueKey ? 'notify.today.queues.'.$queueKey.'.description' : null;
    $translatedTitle = $titleKey && \Illuminate\Support\Facades\Lang::has($titleKey) ? __($titleKey) : $queue['title'];
    $translatedDesc = $descriptionKey && \Illuminate\Support\Facades\Lang::has($descriptionKey) ? __($descriptionKey) : ($queue['description'] ?? '');
@endphp

<article {{ $attributes->merge(['class' => 'notify-queue-card notify-queue-card--'.$variant]) }}>
    <div class="notify-queue-card__head">
        <span class="notify-queue-card__icon">
            <x-notify.icon :name="$queue['icon'] ?? 'activity'" :size="18" />
        </span>
        <div class="notify-queue-card__title">
            <h3>{{ $translatedTitle }}</h3>
            <p>{{ $translatedDesc }}</p>
        </div>
        <strong class="notify-queue-card__count">{{ $queue['count'] ?? 0 }}</strong>
    </div>

    @if(!empty($queue['items']))
        <div class="notify-queue-card__items">
            @foreach($queue['items'] as $item)
                <a class="notify-queue-card__item" href="{{ $item['href'] ?? '#' }}">
                    <span>
                        <strong>{{ $item['title'] }}</strong>
                        <small>{{ $item['description'] }}</small>
                    </span>
                    @if(!empty($item['meta']))
                        <em>{{ $item['meta'] }}</em>
                    @endif
                </a>
            @endforeach
        </div>
    @elseif(!empty($queue['deferred']))
        <x-notify.empty-state
            title="{{ __('notify.common.deferred') }}"
            message="{{ __('notify.today.empty.deferred_message') }}"
            compact
        />
    @else
        <x-notify.empty-state
            title="{{ __('notify.common.clear') }}"
            message="{{ __('notify.today.empty.queue_clear') }}"
            compact
        />
    @endif
</article>
