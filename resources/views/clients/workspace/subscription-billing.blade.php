@php($canManageBilling = \App\Support\FinancialPermissions::allows(auth()->user(), \App\Support\FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING))

<section id="sec-system-access" class="p3-card">
    <div class="p3-section-heading">
        <h3>{{ __('notify.system_access.title') }}</h3>
        <span class="p5-badge p5-badge-neutral">{{ $client->systems->whereNull('pivot.revoked_at')->count() }}</span>
    </div>
    @forelse($client->systems->whereNull('pivot.revoked_at') as $system)
        <div class="p3-list-row">
            <strong>{{ $system->name_ar }}</strong>
            <span class="p5-badge {{ $system->pivot->access_type === 'paid' ? 'p5-badge-success' : 'p5-badge-neutral' }}">
                {{ $system->pivot->access_type === 'paid' ? __('notify.system_access.paid') : __('notify.system_access.free') }}
            </span>
        </div>
    @empty
        <p class="muted">{{ __('notify.system_access.none') }}</p>
    @endforelse

    @if($canManageBilling)
        <form method="POST" action="{{ route('clients.system-access.store', $client) }}" class="notify-action-form" style="margin-top:12px">
            @csrf
            <label><strong>{{ __('notify.system_access.grant_free') }}</strong></label>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin:8px 0">
                @foreach($sellableProducts as $system)
                    <label><input type="checkbox" name="system_ids[]" value="{{ $system->id }}"> {{ $system->name_ar }}</label>
                @endforeach
            </div>
            <button type="submit" class="p5-btn p5-btn-secondary p5-btn-sm">{{ __('notify.system_access.grant') }}</button>
        </form>
    @endif
</section>

<section id="sec-paid-subscriptions" class="p3-card">
    <div class="p3-section-heading">
        <h3>{{ __('notify.subscriptions.title') }}</h3>
        @if($canManageBilling)
            <button type="button" id="sec-start-subscription" class="p5-btn p5-btn-primary p5-btn-sm" data-trigger-start-subscription>{{ __('notify.subscriptions.convert_to_subscriber') }}</button>
        @endif
    </div>
    @forelse($subscriptions as $subscription)
        <article class="p3-list-row">
            <div>
                <strong>{{ $subscription->systems->pluck('pivot.system_name_ar_snapshot')->filter()->join('، ') ?: $subscription->plan_name_snapshot }}</strong>
                <div class="muted">
                    {{ $subscription->billing_interval_v2 === 'annual' ? __('notify.subscriptions.annual') : __('notify.subscriptions.monthly') }} ·
                    {{ \App\Support\Money::fromMinorUnits((int) ($subscription->agreed_value_minor ?? $subscription->total_minor))->format() }} JOD
                </div>
            </div>
            @if($subscription->contract)
                <div>
                    <a href="{{ route('contracts.preview', $subscription->contract) }}">{{ __('notify.contracts.view') }}</a>
                    <a href="{{ route('contracts.download-pdf', $subscription->contract) }}">{{ __('notify.contracts.download_pdf') }}</a>
                </div>
            @endif
        </article>
    @empty
        <p class="muted">{{ __('notify.subscriptions.none') }}</p>
    @endforelse
</section>
