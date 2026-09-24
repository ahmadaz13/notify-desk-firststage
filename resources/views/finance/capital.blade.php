@extends('layouts.app')

{{-- Capital & Financing (§13, feature-gated). Funding lands in Cash Box or CliQ via the V1 resolver; no fixed assets. --}}
@section('content')
<div class="notify-fin" data-finance-page="capital">
    @include('finance.partials.header', [
        'active' => 'capital',
        'title' => __('notify.finance_hub.sections.capital'),
        'subtitle' => __('notify.finance_hub.capital.subtitle'),
    ])

    <section class="notify-fin-kpis notify-fin-kpis--single">
        <div class="notify-fin-kpi">
            <span class="notify-fin-kpi__label">{{ __('notify.finance_hub.capital.active_funding') }}</span>
            <x-notify.money class="notify-fin-kpi__value" :minor="(int) $totals['funding_minor']" />
            <span class="notify-fin-kpi__meta">{{ __('notify.finance_hub.capital.not_revenue') }}</span>
        </div>
    </section>

    <div class="notify-fin-grid">
        <section class="notify-fin-card" aria-labelledby="capital-form-title">
            <div class="notify-fin-card__head">
                <h2 id="capital-form-title" class="notify-fin-card__title">{{ __('notify.finance_hub.capital.record_title') }}</h2>
            </div>
            <form method="POST" action="{{ route('capital-funding-transactions.store') }}" class="notify-fin-form" data-capital-form>
                @csrf
                <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <div class="notify-fin-field">
                    <label for="capital-source">{{ __('notify.finance_hub.capital.saved_source') }}</label>
                    <select id="capital-source" name="funding_source_id">
                        <option value="">{{ __('notify.finance_hub.capital.no_saved_source') }}</option>
                        @foreach($activeFundingSources as $source)
                            <option value="{{ $source->id }}" @selected((string) old('funding_source_id') === (string) $source->id)>{{ $source->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="notify-fin-field">
                    <label for="capital-source-name">{{ __('notify.finance_hub.capital.source_name') }}</label>
                    <input id="capital-source-name" name="source_name" value="{{ old('source_name') }}" maxlength="255">
                </div>
                <div class="notify-fin-field">
                    <label for="capital-type">{{ __('notify.finance_hub.capital.funding_type') }}</label>
                    <select id="capital-type" name="funding_type" required>
                        @foreach(\App\Models\CapitalFundingTransaction::TYPES as $type)
                            <option value="{{ $type }}" @selected(old('funding_type') === $type)>{{ __('notify.finance_hub.capital.types.'.$type) }}</option>
                        @endforeach
                    </select>
                </div>
                <fieldset class="notify-fin-choice">
                    <legend>{{ __('notify.finance_hub.capital.received_by') }}</legend>
                    @foreach($paymentMethodOptions as $method => $methodLabel)
                        <label class="notify-fin-choice__option">
                            <input type="radio" name="payment_method" value="{{ $method }}" required @checked(old('payment_method', 'cash') === $method)>
                            <span>{{ $methodLabel }}</span>
                        </label>
                    @endforeach
                </fieldset>
                <div class="notify-fin-field">
                    <label for="capital-amount">{{ __('notify.finance_hub.accounts.amount') }}</label>
                    <input id="capital-amount" name="amount" inputmode="decimal" dir="ltr" required value="{{ old('amount') }}" placeholder="0.000">
                </div>
                <div class="notify-fin-field">
                    <label for="capital-date">{{ __('notify.finance_hub.accounts.date') }}</label>
                    <input id="capital-date" type="datetime-local" name="received_at" required value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="notify-fin-field">
                    <label for="capital-reference">{{ __('notify.common.reference') }}</label>
                    <input id="capital-reference" name="reference" maxlength="255" value="{{ old('reference') }}">
                </div>
                <div class="notify-fin-field">
                    <label for="capital-notes">{{ __('notify.common.notes') }}</label>
                    <textarea id="capital-notes" name="notes" rows="2" maxlength="2000">{{ old('notes') }}</textarea>
                </div>
                <button type="submit" class="notify-button notify-button--primary">{{ __('notify.finance_hub.capital.record_submit') }}</button>
            </form>
        </section>

        <div class="notify-fin-stack">
            <section class="notify-fin-card" aria-labelledby="capital-history-title">
                <div class="notify-fin-card__head">
                    <h2 id="capital-history-title" class="notify-fin-card__title">{{ __('notify.finance_hub.capital.history_title') }}</h2>
                </div>
                <div class="notify-fin-list notify-fin-list--compact">
                    @forelse($fundingTransactions as $transaction)
                        <article class="notify-fin-movement @if($transaction->reversal) is-muted @endif">
                            <div class="notify-fin-movement__main">
                                <strong>{{ $transaction->source_name_snapshot }}</strong>
                                <small><span dir="ltr">{{ $transaction->received_at?->format('Y-m-d') }}</span> · {{ $transaction->financialAccount ? (app()->getLocale() === 'en' ? ($transaction->financialAccount->name_en ?: $transaction->financialAccount->name_ar) : $transaction->financialAccount->name_ar) : '' }}</small>
                            </div>
                            <x-notify.money class="notify-fin-movement__amount" :minor="(int) $transaction->amount_minor" />
                            @if($transaction->reversal)
                                <span class="notify-fin-chip notify-fin-chip--danger">{{ __('notify.finance_hub.accounts.reversed') }}</span>
                            @else
                                <details class="notify-fin-more">
                                    <summary>{{ __('notify.finance_hub.accounts.reverse') }}</summary>
                                    <form method="POST" action="{{ route('capital-funding-transactions.reverse', $transaction) }}" class="notify-fin-inline-form">
                                        @csrf
                                        <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <label for="capital-reverse-{{ $transaction->id }}">{{ __('notify.finance_hub.accounts.reverse_reason') }}</label>
                                        <input id="capital-reverse-{{ $transaction->id }}" name="reason" required minlength="3" maxlength="1000">
                                        <button type="submit" class="notify-button notify-button--ghost">{{ __('notify.finance_hub.accounts.reverse') }}</button>
                                    </form>
                                </details>
                            @endif
                        </article>
                    @empty
                        <p class="notify-fin-empty">{{ __('notify.assets.empty_funding') }}</p>
                    @endforelse
                </div>
            </section>

            <details class="notify-fin-card notify-fin-disclosure">
                <summary>{{ __('notify.finance_hub.capital.sources_title') }} <span dir="ltr">({{ $fundingSources->count() }})</span></summary>
                <form method="POST" action="{{ route('funding-sources.store') }}" class="notify-fin-form">
                    @csrf
                    <div class="notify-fin-field">
                        <label for="source-name">{{ __('notify.finance_hub.capital.source_name') }}</label>
                        <input id="source-name" name="name" required maxlength="255">
                    </div>
                    <div class="notify-fin-field">
                        <label for="source-type">{{ __('notify.finance_hub.capital.source_type') }}</label>
                        <select id="source-type" name="type" required>
                            @foreach(\App\Models\FundingSource::TYPES as $type)
                                <option value="{{ $type }}">{{ __('notify.finance_hub.capital.source_types.'.$type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="notify-button notify-button--soft">{{ __('notify.finance_hub.capital.add_source') }}</button>
                </form>
                <div class="notify-fin-list notify-fin-list--compact">
                    @foreach($fundingSources as $source)
                        <article class="notify-fin-movement">
                            <div class="notify-fin-movement__main">
                                <strong>{{ $source->name }}</strong>
                                <small>{{ __('notify.finance_hub.capital.source_types.'.$source->type) }}@unless($source->is_active) · {{ __('notify.finance_hub.accounts.archived') }}@endunless</small>
                            </div>
                            @if($source->archived_at === null)
                                <form method="POST" action="{{ route('funding-sources.archive', $source) }}">
                                    @csrf
                                    <button type="submit" class="notify-button notify-button--ghost">{{ __('notify.finance_hub.capital.archive_source') }}</button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
            </details>
        </div>
    </div>
</div>
@endsection
