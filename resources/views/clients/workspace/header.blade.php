{{-- A. Client identity (§19.1): compact, high-value only. --}}
@php($h = $workspace->header)
<header class="notify-client-header" data-page-header data-client-header>
    <div class="notify-client-header__identity">
        <h1 class="notify-client-header__name">{{ $h['name'] }}</h1>
        <p class="notify-client-header__meta">
            @if($h['category'])<span>{{ $h['category'] }}</span>@endif
            @if($h['area'])<span>{{ $h['area'] }}</span>@endif
            @if($h['responsible'])<span>{{ __('notify.client_hub.header.responsible', ['name' => $h['responsible']]) }}</span>@endif
        </p>
        <div class="notify-client-header__chips">
            <span class="notify-status notify-status--{{ $h['stage_tone'] }}" data-client-stage-badge><x-notify.icon :name="$h['stage_icon']" :size="14" />{{ $h['stage_label'] }}</span>
            @if($h['subscription_chip'])
                <span class="notify-status notify-status--{{ $h['subscription_chip']['tone'] }}"><x-notify.icon name="check" :size="14" />{{ $h['subscription_chip']['label'] }}</span>
            @endif
            @if($h['custom_projects'] > 0 && $workspace->can['view_custom_projects'])
                <a class="notify-status notify-status--neutral notify-status--link" href="{{ route('clients.custom-projects.index', $client) }}" data-client-custom-projects>
                    <x-notify.icon name="folder-kanban" :size="14" />{{ __('notify.client_hub.header.custom_projects', ['count' => $h['custom_projects']]) }}
                </a>
            @endif
        </div>
    </div>
    @if($h['phone'])
        <div class="notify-client-header__contact">
            <a class="notify-client-header__phone" href="{{ $h['tel'] }}" aria-label="{{ __('notify.client_hub.header.call', ['phone' => $h['phone']]) }}" data-client-call>
                <x-notify.icon name="phone" :size="18" />
                <span dir="ltr">{{ $h['phone'] }}</span>
            </a>
            @if($h['whatsapp'])
                <a class="notify-button notify-button--ghost notify-client-header__whatsapp" href="{{ $h['whatsapp'] }}" target="_blank" rel="noopener noreferrer" data-client-whatsapp>
                    <x-notify.icon name="message-circle" :size="18" />
                    <span class="notify-button__label">{{ __('notify.client_hub.header.whatsapp') }}</span>
                </a>
            @endif
        </div>
    @endif
</header>
