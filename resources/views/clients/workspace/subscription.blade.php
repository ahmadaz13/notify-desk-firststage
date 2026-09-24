{{-- D. Subscription (§19.6, §14, §15): business terms only, no billing-engine fields. --}}
@php($sub = $workspace->subscription)
@if($sub['active'] || $sub['past'])
<section class="notify-card notify-ws-card" aria-labelledby="client-subscription-title" id="sec-subscriptions" data-client-subscription>
    <header class="notify-ws-card__head">
        <h2 class="notify-ws-card__title" id="client-subscription-title"><x-notify.icon name="check-circle" :size="18" /> {{ __('notify.client_hub.subscription.title') }}</h2>
    </header>

    @forelse($sub['active'] as $item)
        <article class="notify-subscription" data-subscription="{{ $item['id'] }}">
            <div class="notify-subscription__top">
                <h3 class="notify-subscription__systems">{{ $item['systems'] }}</h3>
                <span class="notify-status notify-status--{{ $item['ending'] ? 'warning' : 'success' }}">
                    <x-notify.icon :name="$item['ending'] ? 'alert-circle' : 'check'" :size="14" />
                    {{ $item['ending'] ? __('notify.client_hub.subscription.status_ending') : __('notify.client_hub.subscription.status_active') }}
                </span>
            </div>
            <dl class="notify-facts">
                <div><dt>{{ __('notify.client_hub.subscription.agreed_value') }}</dt><dd><x-notify.money :minor="$item['value_minor']" /></dd></div>
                <div><dt>{{ __('notify.client_hub.subscription.cycle') }}</dt><dd>{{ $item['cycle'] }}@if($item['is_annual']) · {{ $item['installments'] ? __('notify.client_hub.subscription.installments', ['count' => $item['installments']]) : __('notify.client_hub.subscription.full_payment') }}@endif</dd></div>
                <div><dt>{{ __('notify.client_hub.subscription.started') }}</dt><dd dir="ltr">{{ $item['start'] ?? '—' }}</dd></div>
                <div><dt>{{ $item['ending'] ? __('notify.client_hub.subscription.ends') : __('notify.client_hub.subscription.renews') }}</dt><dd dir="ltr">{{ $item['period_end'] ?? '—' }}</dd></div>
            </dl>
            @if($item['can_cancel'] || $item['can_undo'])
                <div class="notify-subscription__actions">
                    @if($item['can_cancel'])
                        <button type="button" class="notify-button notify-button--ghost notify-button--sm" data-open-sheet="modal-cancel-subscription-{{ $item['id'] }}" aria-haspopup="dialog" data-subscription-cancel>
                            <x-notify.icon name="x" :size="16" /><span>{{ __('notify.client_hub.actions.cancel_subscription') }}</span>
                        </button>
                    @else
                        <form method="POST" action="{{ route('subscriptions.cancel.undo', $item['id']) }}" data-subscription-undo>
                            @csrf
                            <button type="submit" class="notify-button notify-button--secondary notify-button--sm" title="{{ __('notify.client_hub.subscription.undo_hint') }}">
                                <x-notify.icon name="activity" :size="16" /><span>{{ __('notify.client_hub.actions.undo_cancellation') }}</span>
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </article>

        @if($item['can_cancel'])
            <div class="notify-modal-backdrop" id="modal-cancel-subscription-{{ $item['id'] }}" hidden>
                <div class="notify-modal-card notify-action-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-cancel-subscription-{{ $item['id'] }}-title">
                    <div class="notify-modal-header">
                        <h3 id="modal-cancel-subscription-{{ $item['id'] }}-title">{{ __('notify.client_hub.subscription.cancel_title') }} · {{ $item['systems'] }}</h3>
                        <button type="button" class="notify-icon-button" data-close-action-modal aria-label="{{ __('notify.client_hub.actions.cancel') }}"><x-notify.icon name="x" /></button>
                    </div>
                    <form method="POST" action="{{ route('subscriptions.cancel', $item['id']) }}" class="notify-action-form">
                        @csrf
                        <p class="notify-sheet-hint">{{ __('notify.client_hub.subscription.cancel_hint') }}</p>
                        <label class="notify-field">
                            <span>{{ __('notify.client_hub.subscription.cancel_reason') }}</span>
                            <textarea name="cancellation_reason" rows="2" maxlength="1000"></textarea>
                        </label>
                        <div class="notify-modal-footer">
                            <button type="button" class="notify-button notify-button--ghost" data-close-action-modal>{{ __('notify.client_hub.actions.cancel') }}</button>
                            <button type="submit" class="notify-button notify-button--danger">{{ __('notify.client_hub.subscription.confirm_cancel') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @empty
        <p class="notify-ws-card__empty">{{ __('notify.client_hub.subscription.none') }}</p>
    @endforelse

    @if($sub['past'])
        <details class="notify-disclosure" data-past-subscriptions>
            <summary>{{ __('notify.client_hub.subscription.past', ['count' => count($sub['past'])]) }}</summary>
            <ul class="notify-plain-list">
                @foreach($sub['past'] as $item)
                    <li>
                        <strong>{{ $item['systems'] }}</strong>
                        <span>{{ $item['cycle'] }} · <x-notify.money :minor="$item['value_minor']" /></span>
                        <small>{{ __('notify.client_hub.subscription.ended') }} <span dir="ltr">{{ $item['ended'] ?? $item['period_end'] ?? '—' }}</span></small>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</section>
@endif
