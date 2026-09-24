{{-- B + C. Current state / next step and the action hierarchy (§11, §12): one primary, up to two secondary, the rest under "…". --}}
@php($state = $workspace->state)
<section class="notify-card notify-state notify-state--{{ $state['tone'] }}" aria-labelledby="client-state-title" data-client-state="{{ $state['key'] }}" id="client-next-step">
    <div class="notify-state__body">
        <span class="notify-state__icon" aria-hidden="true"><x-notify.icon :name="$state['icon']" :size="22" /></span>
        <div class="notify-state__text">
            <p class="notify-state__eyebrow">{{ __('notify.client_hub.state.eyebrow') }}</p>
            <h2 class="notify-state__title" id="client-state-title">{{ $state['title'] }}</h2>
            @isset($state['params']['money_minor'])
                <p class="notify-state__amount"><x-notify.money :minor="$state['params']['money_minor']" /></p>
            @endisset
            <p class="notify-state__detail">{{ $state['detail'] }}</p>
            @if(! empty($state['params']['reason']))
                <p class="notify-state__meta">{{ $state['params']['reason'] }}</p>
            @endif
            @if(! empty($state['params']['systems']))
                <p class="notify-state__meta"><x-notify.icon name="package" :size="14" /> {{ $state['params']['systems'] }}</p>
            @endif
        </div>
    </div>

    @if($workspace->primaryAction || $workspace->secondaryActions || $workspace->moreActions)
        <div class="notify-state__actions" data-client-actions>
            @if($workspace->primaryAction)
                <x-notify.workspace-action :action="$workspace->primaryAction" variant="primary" class="notify-state__primary" data-primary-action />
            @endif
            @foreach($workspace->secondaryActions as $action)
                <x-notify.workspace-action :action="$action" variant="secondary" data-secondary-action />
            @endforeach
            @if($workspace->moreActions)
                @php([$dangerActions, $routineActions] = collect($workspace->moreActions)->partition(fn ($action) => ($action['tone'] ?? null) === 'danger'))
                <x-notify.menu :label="__('notify.client_hub.actions.more')" :show-label="true" data-client-more>
                    @foreach($routineActions as $action)
                        <x-notify.workspace-action :action="$action" :menu="true" role="menuitem" />
                    @endforeach
                    <x-slot:danger>
                        @foreach($dangerActions as $action)
                            <x-notify.workspace-action :action="$action" :menu="true" role="menuitem" />
                        @endforeach
                    </x-slot:danger>
                </x-notify.menu>
            @endif
        </div>
    @endif
</section>

@if($workspace->review)
    <section class="notify-card notify-review" aria-labelledby="client-review-title" id="sec-review-item-{{ $workspace->review['id'] }}" data-client-review>
        <div>
            <h2 class="notify-review__title" id="client-review-title"><x-notify.icon name="alert-circle" :size="18" /> {{ $workspace->review['title'] }}</h2>
            <p class="notify-review__detail">{{ collect([$workspace->review['type'], $workspace->review['note']])->filter()->join(' · ') ?: __('notify.client_hub.review.hint') }}</p>
        </div>
        @if($workspace->review['can_resolve'])
            <div class="notify-review__actions">
                <form method="POST" action="{{ route('client-review-items.resolve', $workspace->review['id']) }}">
                    @csrf
                    <button type="submit" class="notify-button notify-button--secondary notify-button--sm">{{ __('notify.client_hub.actions.resolve') }}</button>
                </form>
                <form method="POST" action="{{ route('client-review-items.dismiss', $workspace->review['id']) }}">
                    @csrf
                    <button type="submit" class="notify-button notify-button--ghost notify-button--sm">{{ __('notify.client_hub.actions.dismiss') }}</button>
                </form>
            </div>
        @endif
    </section>
@endif
