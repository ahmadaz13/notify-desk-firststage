@props([
    'groupId',
    'label',
    'icon' => 'circle',
    'links' => [],
    'active' => false,
])

<details class="notify-nav-group {{ $active ? 'is-active' : '' }}" data-nav-group="{{ $groupId }}" @if($active) open @endif>
    <summary class="notify-nav-group__summary">
        <span class="notify-nav-group__identity">
            <span class="notify-nav-item__icon">
                <x-notify.icon :name="$icon" />
            </span>
            <span class="notify-nav-group__label">{{ $label }}</span>
        </span>
        <x-notify.icon name="chevron-down" :size="16" class="notify-nav-group__chevron" />
    </summary>
    <nav class="notify-nav notify-nav-group__links" aria-label="{{ $label }}">
        @foreach($links as $link)
            <x-notify.nav-item
                data-nav-destination="{{ $link['id'] }}"
                :href="$link['href']"
                :label="$link['label']"
                :icon="$link['icon']"
                :active="$link['active']"
            />
        @endforeach
    </nav>
</details>
