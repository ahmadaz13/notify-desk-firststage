{{-- G. Business & primary contact (§19.1–2, §28.1) and referral (§19.9). A contact name is never required. --}}
@php($d = $workspace->details)
<section class="notify-card notify-ws-card" aria-labelledby="client-details-title" id="sec-details" data-client-details>
    <header class="notify-ws-card__head">
        <h2 class="notify-ws-card__title" id="client-details-title"><x-notify.icon name="building" :size="18" /> {{ __('notify.client_hub.details.title') }}</h2>
        @if($workspace->can['update'])
            <a class="notify-button notify-button--ghost notify-button--sm" href="{{ $d['edit_url'] }}" data-client-edit>{{ __('notify.client_hub.details.edit') }}</a>
        @endif
    </header>

    <div class="notify-details-grid">
        <div class="notify-details-block" data-business-details>
            <h3 class="notify-details-block__title">{{ __('notify.client_hub.details.business') }}</h3>
            <dl class="notify-facts notify-facts--stacked">
                <div><dt>{{ __('notify.client_hub.details.business_phone') }}</dt><dd>@if($d['business']['phone'])<a href="{{ $d['business']['tel'] }}" dir="ltr">{{ $d['business']['phone'] }}</a>@else<span class="notify-muted">{{ __('notify.client_hub.details.not_set') }}</span>@endif</dd></div>
                <div><dt>{{ __('notify.client_hub.details.phone_owner') }}</dt><dd>{{ $d['business']['phone_owner'] }}</dd></div>
                <div><dt>{{ __('notify.client_hub.details.category') }}</dt><dd>{{ $d['business']['category'] ?: __('notify.client_hub.details.not_set') }}</dd></div>
                <div><dt>{{ __('notify.client_hub.details.area') }}</dt><dd>{{ $d['business']['area'] ?: __('notify.client_hub.details.not_set') }}</dd></div>
                @if($d['business']['location'] || $d['business']['maps_url'])
                    <div><dt>{{ __('notify.client_hub.details.location') }}</dt><dd>{{ $d['business']['location'] }}@if($d['business']['maps_url']) <a href="{{ $d['business']['maps_url'] }}" target="_blank" rel="noopener noreferrer">{{ __('notify.client_hub.details.open_map') }}</a>@endif</dd></div>
                @endif
                @if($d['business']['lead_source'])
                    <div><dt>{{ __('notify.client_hub.details.lead_source') }}</dt><dd>{{ $d['business']['lead_source'] }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="notify-details-block" data-contact-details>
            <h3 class="notify-details-block__title">{{ __('notify.client_hub.details.contact') }}</h3>
            @if($d['contact'])
                <dl class="notify-facts notify-facts--stacked">
                    <div><dt>{{ __('notify.client_hub.details.contact') }}</dt><dd>{{ $d['contact']['name'] ?? __('notify.clients.contact_model.unnamed_contact') }}@if($d['contact']['role']) · {{ $d['contact']['role'] }}@endif</dd></div>
                    @if($d['contact']['phone'])
                        <div><dt>{{ __('notify.client_hub.details.phone') }}</dt><dd><a href="{{ $d['contact']['tel'] }}" dir="ltr">{{ $d['contact']['phone'] }}</a></dd></div>
                    @endif
                    @if($d['contact']['whatsapp'])
                        <div><dt>{{ __('notify.client_hub.details.whatsapp') }}</dt><dd><a href="{{ $d['contact']['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer" dir="ltr">{{ $d['contact']['whatsapp'] }}</a></dd></div>
                    @endif
                    @if($d['contact']['email'])
                        <div><dt>{{ __('notify.client_hub.details.email') }}</dt><dd dir="ltr">{{ $d['contact']['email'] }}</dd></div>
                    @endif
                </dl>
                @if($d['other_contacts'] > 0)
                    <p class="notify-muted">{{ __('notify.client_hub.details.other_contacts', ['count' => $d['other_contacts']]) }}</p>
                @endif
            @else
                <div class="notify-inline-empty" data-no-contact>
                    <p><strong>{{ __('notify.client_hub.details.no_contact_title') }}</strong></p>
                    <p class="notify-muted">{{ __('notify.client_hub.details.no_contact_hint') }}</p>
                    @if($workspace->can['update'])
                        <a class="notify-button notify-button--secondary notify-button--sm" href="{{ $d['edit_url'] }}#contact">{{ __('notify.client_hub.details.add_contact') }}</a>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if($d['notes'])
        <div class="notify-details-block">
            <h3 class="notify-details-block__title">{{ __('notify.client_hub.details.notes') }}</h3>
            <p class="notify-details-notes">{{ $d['notes'] }}</p>
        </div>
    @endif

    @if($workspace->referral)
        <div class="notify-details-block" data-client-referral>
            <h3 class="notify-details-block__title">{{ __('notify.client_hub.referral.title') }}</h3>
            <dl class="notify-facts notify-facts--stacked">
                @if($workspace->referral['name'])
                    <div><dt>{{ __('notify.client_hub.referral.referred_by') }}</dt><dd>{{ $workspace->referral['name'] }}</dd></div>
                @endif
                @if($workspace->referral['note'])
                    <div><dt>{{ __('notify.client_hub.referral.note') }}</dt><dd>{{ $workspace->referral['note'] }}</dd></div>
                @endif
                @if($workspace->referral['commission'])
                    <div data-referral-commission><dt>{{ __('notify.client_hub.referral.commission') }}</dt><dd dir="ltr">{{ $workspace->referral['commission'] }}</dd></div>
                @endif
            </dl>
        </div>
    @endif
</section>
