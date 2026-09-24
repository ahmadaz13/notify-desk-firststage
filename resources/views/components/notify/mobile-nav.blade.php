@props([
    'nav',
    'targetLocale',
    'targetLocaleLabel',
    'userName',
    'roleLabel',
])

{{--
    Phone and iPad-portrait navigation (§3.4, §22, §23). Exactly three destinations + More;
    the sidebar is never copied here. Items come from App\Support\ShellNavigation.
--}}
@php
    $moreLabel = __('notify.shell_nav.more');
    $closeLabel = __('notify.shell_nav.close');
@endphp
<nav
    class="notify-mobile-nav"
    aria-label="{{ __('notify.shell_nav.mobile_navigation') }}"
    x-data="{
        moreOpen: false,
        openMore() {
            this.moreOpen = true;
            this.$nextTick(() => this.$refs.moreClose?.focus());
        },
        closeMore(returnFocus = true) {
            this.moreOpen = false;
            if (returnFocus) this.$nextTick(() => this.$refs.moreButton?.focus());
        },
        trapFocus(event) {
            const focusable = [...this.$refs.morePanel.querySelectorAll('a[href], button:not([disabled])')];
            if (focusable.length === 0) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (! event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    }"
    x-effect="document.body.classList.toggle('notify-mobile-menu-open', moreOpen)"
    @keydown.escape.window="if (moreOpen) closeMore()"
>
    <button
        class="notify-mobile-nav__backdrop"
        type="button"
        tabindex="-1"
        aria-label="{{ $closeLabel }}"
        x-show="moreOpen"
        x-cloak
        x-transition.opacity.duration.150ms
        @click="closeMore()"
    ></button>

    <section
        class="notify-mobile-nav__more"
        id="notify-mobile-more"
        role="dialog"
        aria-modal="true"
        aria-labelledby="notify-mobile-more-title"
        data-mobile-more
        x-ref="morePanel"
        x-show="moreOpen"
        x-cloak
        x-transition:enter="notify-mobile-sheet-enter"
        x-transition:enter-start="notify-mobile-sheet-enter-start"
        x-transition:enter-end="notify-mobile-sheet-enter-end"
        x-transition:leave="notify-mobile-sheet-leave"
        x-transition:leave-start="notify-mobile-sheet-leave-start"
        x-transition:leave-end="notify-mobile-sheet-leave-end"
        @keydown.tab="trapFocus($event)"
    >
        <header class="notify-mobile-nav__more-header">
            <div class="notify-mobile-nav__account-summary">
                <span class="notify-avatar" aria-hidden="true">{{ mb_substr($userName, 0, 1) }}</span>
                <div class="notify-user__identity">
                    <h2 id="notify-mobile-more-title">{{ $moreLabel }}</h2>
                    <span>{{ $userName }} · {{ $roleLabel }}</span>
                </div>
            </div>
            <button class="notify-icon-button" type="button" x-ref="moreClose" @click="closeMore()" aria-label="{{ $closeLabel }}">
                <x-notify.icon name="x" />
            </button>
        </header>

        <div class="notify-mobile-nav__more-content">
            @foreach($nav->moreSections as $section)
                <div class="notify-mobile-nav__layer" data-mobile-nav-layer="{{ $section['key'] }}">
                    <p class="notify-mobile-nav__layer-label" id="notify-more-{{ $section['key'] }}">{{ $section['label'] }}</p>
                    <ul class="notify-mobile-nav__links" aria-labelledby="notify-more-{{ $section['key'] }}">
                        @foreach($section['items'] as $item)
                            <li>
                                <a class="notify-mobile-nav__more-link {{ $item['active'] ? 'is-active' : '' }}" href="{{ $item['href'] }}" data-nav-destination="{{ $item['destination'] }}" @if($item['active']) aria-current="page" @endif>
                                    <span class="notify-mobile-nav__more-link-main"><x-notify.icon :name="$item['icon']" /><span>{{ $item['label'] }}</span></span>
                                    @if($item['badge'])
                                        <span class="notify-count-badge" aria-hidden="true">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                                        <span class="notify-visually-hidden">{{ __('notify.shell_nav.unread_badge', ['count' => $item['badge']]) }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div class="notify-mobile-nav__layer notify-mobile-nav__layer--account" data-mobile-nav-layer="account">
                <p class="notify-mobile-nav__layer-label" id="notify-more-account">{{ __('notify.shell_nav.sections.account') }}</p>
                <ul class="notify-mobile-nav__links" aria-labelledby="notify-more-account">
                    <li>
                        <a class="notify-mobile-nav__more-link {{ $nav->area === 'profile' ? 'is-active' : '' }}" href="{{ route('profile.edit') }}" data-nav-destination="profile" @if($nav->area === 'profile') aria-current="page" @endif>
                            <span class="notify-mobile-nav__more-link-main"><x-notify.icon name="user" /><span>{{ __('notify.shell_nav.areas.profile') }}</span></span>
                        </a>
                    </li>
                    <li>
                        <a class="notify-mobile-nav__more-link" href="{{ route('profile.edit') }}#password" data-nav-destination="change-password">
                            <span class="notify-mobile-nav__more-link-main"><x-notify.icon name="key-round" /><span>{{ __('notify.shell_nav.change_password') }}</span></span>
                        </a>
                    </li>
                    <li>
                        <a class="notify-mobile-nav__more-link" href="{{ route('locale.switch', $targetLocale) }}" data-nav-destination="language" lang="{{ $targetLocale }}">
                            <span class="notify-mobile-nav__more-link-main"><x-notify.icon name="languages" /><span>{{ $targetLocaleLabel }}</span></span>
                        </a>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="notify-mobile-nav__more-link" type="submit" data-nav-destination="logout">
                                <span class="notify-mobile-nav__more-link-main"><x-notify.icon name="log-out" class="notify-icon--directional" /><span>{{ __('notify.shell_nav.logout') }}</span></span>
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <div class="notify-mobile-nav__grid notify-mobile-nav__grid--{{ count($nav->tabs) + 1 }}">
        @foreach($nav->tabs as $tab)
            <a class="notify-mobile-nav__item {{ $tab['active'] ? 'is-active' : '' }}" data-nav-destination="{{ $tab['destination'] }}" href="{{ $tab['href'] }}" @if($tab['active']) aria-current="page" @endif>
                <x-notify.icon :name="$tab['icon']" />
                <span class="notify-mobile-nav__label">{{ $tab['label'] }}</span>
            </a>
        @endforeach

        <button
            class="notify-mobile-nav__item {{ $nav->moreActive ? 'is-active' : '' }}"
            data-nav-destination="more"
            type="button"
            x-ref="moreButton"
            @click="moreOpen ? closeMore(false) : openMore()"
            :aria-expanded="moreOpen.toString()"
            aria-expanded="false"
            aria-controls="notify-mobile-more"
        >
            <x-notify.icon name="menu" />
            <span class="notify-mobile-nav__label">{{ $moreLabel }}</span>
        </button>
    </div>
</nav>
