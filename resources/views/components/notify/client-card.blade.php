@props([
    'client',
])

<article {{ $attributes->merge(['class' => 'notify-client-card']) }}>
    <a class="notify-client-card__main" href="{{ $client['href'] }}">
        <span class="notify-avatar">{{ $client['initial'] }}</span>
        <span class="notify-client-card__identity">
            <strong>{{ $client['business_name'] }}</strong>
            <small>{{ $client['business_type'] }}@if($client['location']) · {{ $client['location'] }}@endif</small>
        </span>
        <x-notify.badge :variant="$client['stage_variant']">{{ $client['stage_label'] }}</x-notify.badge>
    </a>

    <dl class="notify-client-card__facts">
        <div>
            <dt>Next action</dt>
            <dd>
                {{ $client['next_action']['label'] }}
                @if($client['next_action']['at'])
                    <span>{{ $client['next_action']['at'] }}</span>
                @endif
            </dd>
        </div>
        <div>
            <dt>Responsible</dt>
            <dd>{{ $client['responsible_user'] }}</dd>
        </div>
        <div>
            <dt>Last activity</dt>
            <dd>{{ $client['last_activity'] ?? 'No activity yet' }}</dd>
        </div>
    </dl>

    <div class="notify-client-card__actions">
        @if($client['call_href'])
            <x-notify.button :href="$client['call_href']" variant="ghost" icon="activity" aria-label="Call {{ $client['business_name'] }}">Call</x-notify.button>
        @endif
        @if($client['whatsapp_url'])
            <x-notify.button :href="$client['whatsapp_url']" variant="secondary" icon="bell" target="_blank" rel="noopener">WhatsApp</x-notify.button>
        @endif
        <x-notify.button :href="$client['href']" variant="primary" icon="building">Open</x-notify.button>
    </div>
</article>
