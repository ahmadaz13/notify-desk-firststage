@props([
    'item',
])

{{--
    Operational work card (P11). Understandable in ~2 seconds: task type + time/lateness, the business
    (a link to its Client Workspace), one line of context, who is on it, and one primary action that
    opens the matching Client Workspace sheet. Colour is always paired with an icon and text.
--}}
@php
    $type = $item['type'] ?? 'call';
    $icon = match ($type) {
        'appointment' => 'calendar',
        'installation' => 'wrench',
        'follow_up' => 'clipboard-list',
        'collection' => 'wallet',
        'review' => 'alert-circle',
        default => 'phone',
    };

    $timing = $item['timing'] ?? ['state' => 'scheduled', 'text' => $item['due_formatted'] ?? '', 'exact' => null, 'datetime' => null];
    $state = $timing['state'];
    $isOverdue = $state === 'overdue';
    $chipTone = match ($state) {
        'overdue' => 'danger',
        'now' => 'info',
        'due_today', 'waiting' => 'warning',
        default => null,
    };
    // Overdue/now/due-today read as a status; upcoming work reads as a clock time.
    $timeText = in_array($state, ['soon', 'scheduled'], true) ? ($timing['exact'] ?? $timing['text']) : ($timing['exact'] ?? null);
    $headingId = 'work-item-'.$item['id'];
    $primary = $item['primary_action'] ?? null;
@endphp

<article {{ $attributes->merge(['class' => 'notify-task notify-task--'.$type.($isOverdue ? ' notify-task--overdue' : '')]) }}
    aria-labelledby="{{ $headingId }}"
    data-work-item="{{ $item['id'] }}"
    data-work-type="{{ $type }}"
    data-timing="{{ $state }}">
    <div class="notify-task__top">
        <span class="notify-task__type">
            <span class="notify-task__icon" aria-hidden="true"><x-notify.icon :name="$icon" :size="16" /></span>
            <span>{{ $item['label'] }}</span>
        </span>
        <span class="notify-task__when">
            @if($chipTone)
                <span class="notify-status notify-status--{{ $chipTone }}" data-timing-status>
                    @if($isOverdue)<x-notify.icon name="alert-circle" :size="14" />@endif
                    {{ $timing['text'] }}
                </span>
            @elseif($state === 'ready')
                <span class="notify-task__hint">{{ $timing['text'] }}</span>
            @endif
            @if($timeText)
                <time class="notify-task__time {{ $chipTone ? '' : 'notify-task__time--strong' }}" @if($timing['datetime']) datetime="{{ $timing['datetime'] }}" @endif>{{ $timeText }}</time>
            @endif
            @if($state === 'soon')
                <span class="notify-task__hint">{{ $timing['text'] }}</span>
            @endif
        </span>
    </div>

    <div class="notify-task__main">
        <div class="notify-task__body">
            <h3 class="notify-task__client" id="{{ $headingId }}">
                <a href="{{ $item['client_href'] }}" data-work-client-link>{{ $item['client_name'] }}</a>
            </h3>

            @if(($item['amount_minor'] ?? null) !== null)
                <p class="notify-task__amount">
                    <x-notify.money :minor="$item['amount_minor']" />
                    @if(($item['pending_minor'] ?? 0) > 0)
                        <span class="notify-status notify-status--neutral" data-pending-receipt>
                            {{ __('notify.today_board.pending_on_card') }} <x-notify.money :minor="$item['pending_minor']" />
                        </span>
                    @endif
                </p>
            @endif

            @if(!empty($item['context']))
                <p class="notify-task__context">{{ $item['context'] }}</p>
            @endif

            @if(!empty($item['responsible_staff']) || !empty($item['area']) || !empty($item['status_label']))
                <p class="notify-task__meta">
                    @if(!empty($item['responsible_staff']))
                        <span class="notify-task__staff" data-responsible-staff>
                            <x-notify.icon name="user" :size="14" />
                            <span>{{ $item['responsible_staff'] }}</span>
                        </span>
                    @endif
                    @if(!empty($item['area']))
                        <span>{{ $item['area'] }}</span>
                    @endif
                    @if(!empty($item['status_label']))
                        <span>{{ $item['status_label'] }}</span>
                    @endif
                </p>
            @endif
        </div>

        <div class="notify-task__actions">
            @if($primary)
                <x-notify.button
                    :href="$primary['href']"
                    variant="primary"
                    class="notify-task__primary"
                    aria-describedby="{{ $headingId }}"
                    data-card-primary-action="{{ $primary['type'] }}"
                >{{ $primary['label'] }}</x-notify.button>
            @else
                <x-notify.button
                    :href="$item['client_href']"
                    variant="secondary"
                    class="notify-task__primary"
                    aria-describedby="{{ $headingId }}"
                    data-card-primary-action="open-client"
                >{{ __('notify.today_board.actions.open_client') }}</x-notify.button>
            @endif
        </div>
    </div>
</article>
