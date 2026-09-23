@props([
    'client',
])

<article {{ $attributes->merge(['class' => 'notify-client-card']) }}>
    <a class="notify-client-card__main" href="{{ $client['href'] }}">
        <span class="notify-avatar">{{ $client['initial'] }}</span>
        <span class="notify-client-card__identity">
            <strong>{{ $client['business_name'] }}</strong>
            <small>{{ $client['contact_name'] }}@if($client['contact_phone']) · <span dir="ltr">{{ $client['contact_phone'] }}</span>@endif</small>
        </span>
        <x-notify.badge :variant="$client['stage_variant']">{{ $client['stage_label'] }}</x-notify.badge>
    </a>

    <dl class="notify-client-card__facts">
        <div>
            <dt>{{ __('notify.clients.next_action') }}</dt>
            <dd>
                {{ $client['next_action']['label'] }}
                @if($client['next_action']['at'])
                    <span>{{ $client['next_action']['at'] }}</span>
                @endif
            </dd>
        </div>
    </dl>

    <div class="notify-client-card__actions">
        @if($client['call_href'])
            <x-notify.button :href="$client['call_href']" variant="ghost" icon="phone" aria-label="{{ __('notify.actions.call') }} {{ $client['business_name'] }}">{{ __('notify.actions.call') }}</x-notify.button>
        @endif
        @if($client['whatsapp_url'])
            <x-notify.button :href="$client['whatsapp_url']" variant="secondary" icon="phone" target="_blank" rel="noopener">WhatsApp</x-notify.button>
        @endif
        <x-notify.button :href="$client['href']" variant="primary" icon="building">{{ __('notify.actions.open') }}</x-notify.button>
    </div>
</article>
