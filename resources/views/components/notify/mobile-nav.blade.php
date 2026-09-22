@props([
    'labels',
    'canViewClients' => false,
    'canViewFinance' => false,
    'canViewAdministration' => false,
    'isStaff' => false,
    'isToday' => false,
    'isClients' => false,
    'isFinance' => false,
    'isAdministration' => false,
    'financeSubLinks' => [],
    'adminSubLinks' => [],
    'moreActive' => false,
    'targetLocale',
    'targetLocaleLabel',
    'userName',
    'roleLabel',
])

<nav
    class="notify-mobile-nav"
    aria-label="{{ $labels['mobile_navigation'] }}"
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
        aria-label="{{ $labels['close'] }}"
        x-show="moreOpen"
        x-cloak
        x-transition.opacity
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
                <span class="notify-avatar">{{ mb_substr($userName, 0, 1) }}</span>
                <div class="notify-user__identity">
                    <h2 id="notify-mobile-more-title">{{ $labels['more'] }}</h2>
                    <span>{{ $userName }} · {{ $roleLabel }}</span>
                </div>
            </div>
            <button class="notify-icon-button" type="button" x-ref="moreClose" @click="closeMore()" aria-label="{{ $labels['close'] }}">
                <x-notify.icon name="x" />
            </button>
        </header>

        <div class="notify-mobile-nav__more-content">
            @if(!$isStaff)
                @if(!empty($financeSubLinks))
                    <div class="notify-mobile-nav__layer" data-mobile-nav-layer="finance">
                        <p class="notify-mobile-nav__layer-label">{{ $labels['finance'] }}</p>
                        <div class="notify-mobile-nav__group-links">
                            @foreach($financeSubLinks as $link)
                                <a
                                    class="notify-mobile-nav__more-link {{ $link['active'] ? 'is-active' : '' }}"
                                    href="{{ $link['href'] }}"
                                    data-nav-destination="{{ $link['id'] }}"
                                    @if($link['active']) aria-current="page" @endif
                                >
                                    <span class="notify-mobile-nav__more-link-main">
                                        <x-notify.icon :name="$link['icon']" />
                                        <span>{{ $link['label'] }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($adminSubLinks))
                    <div class="notify-mobile-nav__layer" data-mobile-nav-layer="administration">
                        <p class="notify-mobile-nav__layer-label">{{ $labels['administration'] }}</p>
                        <div class="notify-mobile-nav__group-links">
                            @foreach($adminSubLinks as $link)
                                <a
                                    class="notify-mobile-nav__more-link {{ $link['active'] ? 'is-active' : '' }}"
                                    href="{{ $link['href'] }}"
                                    data-nav-destination="{{ $link['id'] }}"
                                    @if($link['active']) aria-current="page" @endif
                                >
                                    <span class="notify-mobile-nav__more-link-main">
                                        <x-notify.icon :name="$link['icon']" />
                                        <span>{{ $link['label'] }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif

            <div class="notify-mobile-nav__layer notify-mobile-nav__layer--account" data-mobile-nav-layer="account">
                <p class="notify-mobile-nav__layer-label">{{ $labels['account'] }}</p>
                <a class="notify-mobile-nav__more-link" href="{{ route('locale.switch', $targetLocale) }}">
                    <span class="notify-mobile-nav__more-link-main">
                        <x-notify.icon name="languages" />
                        <span>{{ $targetLocaleLabel }}</span>
                    </span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="notify-mobile-nav__more-link" type="submit">
                        <span class="notify-mobile-nav__more-link-main">
                            <x-notify.icon name="log-out" />
                            <span>{{ $labels['logout'] }}</span>
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </section>

    {{-- Bottom Nav Grid: Staff has 3 items, Admin/Finance has 4 items --}}
    <div class="notify-mobile-nav__grid {{ $isStaff ? 'notify-mobile-nav__grid--3' : 'notify-mobile-nav__grid--4' }}">
        {{-- Item 1: Today --}}
        <a class="notify-mobile-nav__item {{ $isToday ? 'is-active' : '' }}" data-nav-destination="today" href="{{ route('dashboard', ['mode' => 'daily']) }}" @if($isToday) aria-current="page" @endif>
            <x-notify.icon name="home" />
            <span class="notify-mobile-nav__label">{{ $labels['today'] }}</span>
        </a>

        {{-- Item 2: Clients --}}
        @if($canViewClients)
            <a class="notify-mobile-nav__item {{ $isClients ? 'is-active' : '' }}" data-nav-destination="clients" href="{{ route('clients.index') }}" @if($isClients) aria-current="page" @endif>
                <x-notify.icon name="users" />
                <span class="notify-mobile-nav__label">{{ $labels['clients'] }}</span>
            </a>
        @else
            <span class="notify-mobile-nav__item" aria-disabled="true">
                <x-notify.icon name="users" />
                <span class="notify-mobile-nav__label">{{ $labels['clients'] }}</span>
            </span>
        @endif

        {{-- Item 3: Finance (Only for non-staff with finance access) --}}
        @if(!$isStaff && $canViewFinance)
            <a class="notify-mobile-nav__item {{ $isFinance ? 'is-active' : '' }}" data-nav-destination="finance" href="{{ route('finance.index') }}" @if($isFinance) aria-current="page" @endif>
                <x-notify.icon name="chart" />
                <span class="notify-mobile-nav__label">{{ $labels['finance'] }}</span>
            </a>
        @endif

        {{-- More: Item 3 for Staff, Item 4 for Finance/Admin --}}
        <button
            class="notify-mobile-nav__item {{ $moreActive ? 'is-active' : '' }}"
            data-nav-destination="more"
            type="button"
            x-ref="moreButton"
            @click="moreOpen ? closeMore(false) : openMore()"
            :aria-expanded="moreOpen.toString()"
            aria-controls="notify-mobile-more"
        >
            <x-notify.icon name="menu" />
            <span class="notify-mobile-nav__label">{{ $labels['more'] }}</span>
        </button>
    </div>
</nav>
