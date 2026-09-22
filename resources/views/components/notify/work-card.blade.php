@props([
    'item',
])

@php
    $type = $item['type'] ?? 'call';
    $icon = match($type) {
        'call' => 'phone',
        'appointment' => 'clipboard-list',
        'installation' => 'wrench',
        'follow_up' => 'activity',
        'collection' => 'wallet',
        'review' => 'bell',
        default => 'activity',
    };

    $badgeVariant = match($type) {
        'installation' => 'success',
        'collection', 'review' => 'danger',
        'follow_up' => 'warning',
        default => 'info',
    };

    $priority = $item['priority'] ?? 'today';
    $isOverdue = $priority === 'overdue';
    $responsibleStaff = $item['responsible_staff'] ?? null;
    $area = $item['area'] ?? null;
@endphp

<article {{ $attributes->merge(['class' => 'notify-work-card notify-work-card--' . $type . ($isOverdue ? ' notify-work-card--overdue' : '')]) }}>
    <div class="notify-work-card__header">
        <div class="notify-work-card__type">
            <span class="notify-work-card__icon notify-work-card__icon--{{ $badgeVariant }}">
                <x-notify.icon :name="$icon" :size="16" />
            </span>
            <span class="notify-work-card__client">{{ $item['client_name'] }}</span>
            @if(!empty($area))
                <span class="notify-work-card__area">{{ $area }}</span>
            @endif
        </div>
        @if(!empty($item['due_formatted']))
            <span class="notify-work-card__due {{ $isOverdue ? 'is-overdue' : '' }}">
                @if($isOverdue)
                    <span class="notify-badge notify-badge--danger">{{ __('notify.work.overdue') ?: 'متأخر' }}</span>
                @endif
                <time>{{ $item['due_formatted'] }}</time>
            </span>
        @endif
    </div>

    <div class="notify-work-card__body">
        <h3 class="notify-work-card__label">{{ $item['label'] }}</h3>
        @if(!empty($item['context']))
            <p class="notify-work-card__context">{{ $item['context'] }}</p>
        @endif
        @if(!empty($responsibleStaff))
            <div class="notify-work-card__staff" data-responsible-staff>
                <x-notify.icon name="user" :size="13" />
                <span>{{ $responsibleStaff }}</span>
            </div>
        @endif
    </div>

    <div class="notify-work-card__actions">
        @if(!empty($item['primary_action']))
            <x-notify.button
                :href="$item['primary_action']['href']"
                variant="primary"
                class="notify-work-card__primary-btn"
                data-card-primary-action
            >
                {{ $item['primary_action']['label'] }}
            </x-notify.button>
        @endif

        <div class="notify-work-card__secondary-actions">
            @if(!empty($item['secondary_actions']))
                @foreach($item['secondary_actions'] as $sec)
                    <x-notify.button
                        :href="$sec['href']"
                        variant="ghost"
                        :icon="$sec['icon'] ?? null"
                        :target="str_starts_with($sec['href'] ?? '', 'http') ? '_blank' : null"
                        :rel="str_starts_with($sec['href'] ?? '', 'http') ? 'noopener' : null"
                        class="notify-work-card__sec-btn"
                        :aria-label="$sec['label']"
                    >
                        {{ $sec['label'] }}
                    </x-notify.button>
                @endforeach
            @endif
        </div>
    </div>
</article>
