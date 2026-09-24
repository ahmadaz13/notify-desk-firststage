{{--
    Finance page header + section switcher (§12, §3.4). Horizontally scrollable on phone; the page never overflows.
    Sections come from App\Support\ShellNavigation, the same source as the desktop sidebar Finance group.
    Expects: $active (section key), $title, optional $subtitle.
--}}
@php
    $sections = \App\Support\ShellNavigation::financeSections(auth()->user());
@endphp
<header class="notify-fin-header">
    <div class="notify-fin-header__text">
        <p class="notify-fin-eyebrow">{{ __('notify.finance_hub.title') }}</p>
        <h1 class="notify-fin-title">{{ $title }}</h1>
        @isset($subtitle)
            <p class="notify-fin-subtitle">{{ $subtitle }}</p>
        @endisset
    </div>
</header>
<nav class="notify-fin-sections" aria-label="{{ __('notify.finance_hub.nav_label') }}" data-finance-sections
     x-data x-init="(() => { const current = $el.querySelector('[aria-current]'); if (!current || $el.scrollWidth <= $el.clientWidth) return; const box = $el.getBoundingClientRect(); const item = current.getBoundingClientRect(); $el.scrollLeft += (item.left + item.width / 2) - (box.left + box.width / 2); })()">
    @foreach($sections as $key => $section)
        <a href="{{ $section['href'] }}"
           class="notify-fin-sections__item @if($active === $key) is-active @endif"
           data-finance-section="{{ $key }}"
           @if($active === $key) aria-current="page" @endif>{{ $section['label'] }}</a>
    @endforeach
</nav>
