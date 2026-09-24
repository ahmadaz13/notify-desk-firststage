@extends('layouts.app')

@php
    $accountName = fn ($account) => app()->getLocale() === 'en' ? ($account->name_en ?: $account->name_ar) : ($account->name_ar ?: $account->name_en);
    $eventLabel = fn (string $event) => \Illuminate\Support\Facades\Lang::has('notify.finance_hub.accounts.events.'.$event)
        ? __('notify.finance_hub.accounts.events.'.$event)
        : __('notify.finance_hub.accounts.events.other');
@endphp

@section('content')
<div class="notify-fin" data-finance-page="accounts">
    @include('finance.partials.header', [
        'active' => 'accounts',
        'title' => __('notify.finance_hub.sections.accounts'),
        'subtitle' => __('notify.finance_hub.accounts.subtitle'),
    ])

    {{-- Cash Box and CliQ: derived balances (§10.4) --}}
    <section class="notify-fin-accounts" aria-label="{{ __('notify.finance_hub.sections.accounts') }}">
        @foreach($accountCards as $card)
            <a href="{{ route('finance.accounts', ['account' => $card['account']->code] + request()->except(['account', 'page'])) }}"
               class="notify-fin-account @if($selected?->is($card['account'])) is-active @endif"
               data-company-account="{{ $card['account']->code }}"
               @if($selected?->is($card['account'])) aria-current="true" @endif>
                <span class="notify-fin-account__name">{{ $accountName($card['account']) }}</span>
                <x-notify.money class="notify-fin-account__balance" :minor="$card['balance_minor']" />
                <span class="notify-fin-account__meta">
                    {{ __('notify.finance_hub.accounts.last_movement') }}
                    <span dir="ltr">{{ $card['last_movement_at'] ? \Illuminate\Support\Carbon::parse($card['last_movement_at'])->format('Y-m-d') : __('notify.finance_hub.accounts.never') }}</span>
                </span>
            </a>
        @endforeach
    </section>

    <div class="notify-fin-grid notify-fin-grid--wide-first">
        {{-- Movements of the selected account --}}
        <section class="notify-fin-card" aria-labelledby="fin-movements-title">
            <div class="notify-fin-card__head">
                <h2 id="fin-movements-title" class="notify-fin-card__title">
                    {{ __('notify.finance_hub.accounts.movements_title', ['account' => $selected ? $accountName($selected) : '']) }}
                </h2>
            </div>
            @include('finance.partials.period-bar', ['action' => route('finance.accounts'), 'keep' => array_filter(['account' => $selected?->code])])

            <p class="notify-fin-opening">{{ __('notify.finance_hub.accounts.opening_balance') }} <x-notify.money :minor="$openingMinor" /></p>
            <div class="notify-fin-list notify-fin-list--compact" data-account-movements>
                @forelse($movements as $row)
                    @php($movement = $row['movement'])
                    <article class="notify-fin-movement">
                        <div class="notify-fin-movement__main">
                            <strong>{{ $eventLabel($movement->event_type) }}</strong>
                            <small dir="ltr">{{ $movement->occurred_at?->format('Y-m-d H:i') }}</small>
                        </div>
                        <x-notify.money class="notify-fin-movement__amount {{ $row['signed_minor'] >= 0 ? 'is-in' : 'is-out' }}" :minor="$row['signed_minor']" :signed="true" />
                        <span class="notify-fin-movement__running">{{ __('notify.finance_hub.accounts.running') }} <x-notify.money :minor="$row['running_minor']" /></span>
                    </article>
                @empty
                    <p class="notify-fin-empty">{{ __('notify.finance_hub.accounts.empty_movements') }}</p>
                @endforelse
            </div>
            @if($movements->hasPages())
                <div class="notify-fin-pagination">{{ $movements->links() }}</div>
            @endif
        </section>

        <div class="notify-fin-stack">
            {{-- Internal transfer (Owner): neither revenue nor expense --}}
            @if($canTransfer && $v1Accounts->count() === 2)
                <section class="notify-fin-card" aria-labelledby="fin-transfer-title">
                    <div class="notify-fin-card__head">
                        <h2 id="fin-transfer-title" class="notify-fin-card__title">{{ __('notify.finance_hub.accounts.transfer_title') }}</h2>
                    </div>
                    <p class="notify-fin-hint">{{ __('notify.finance_hub.accounts.transfer_hint') }}</p>
                    <form method="POST" action="{{ route('financial-transfers.store') }}" class="notify-fin-form" data-transfer-form>
                        @csrf
                        <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <fieldset class="notify-fin-choice">
                            <legend>{{ __('notify.finance_hub.accounts.direction') }}</legend>
                            @foreach(['cash_to_cliq', 'cliq_to_cash'] as $direction)
                                <label class="notify-fin-choice__option">
                                    <input type="radio" name="direction" value="{{ $direction }}" required @checked(old('direction', 'cash_to_cliq') === $direction)>
                                    <span>{{ __('notify.finance_hub.accounts.'.$direction) }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                        <div class="notify-fin-field">
                            <label for="transfer-amount">{{ __('notify.finance_hub.accounts.amount') }}</label>
                            <input id="transfer-amount" name="amount" inputmode="decimal" dir="ltr" required value="{{ old('amount') }}" placeholder="0.000">
                        </div>
                        <div class="notify-fin-field">
                            <label for="transfer-date">{{ __('notify.finance_hub.accounts.date') }}</label>
                            <input id="transfer-date" type="date" name="transferred_at" required value="{{ old('transferred_at', now()->toDateString()) }}">
                        </div>
                        <div class="notify-fin-field">
                            <label for="transfer-note">{{ __('notify.finance_hub.accounts.note') }} <span class="notify-label-note">({{ __('notify.common.optional') }})</span></label>
                            <input id="transfer-note" name="notes" maxlength="1000" value="{{ old('notes') }}">
                        </div>
                        <button type="submit" class="notify-button notify-button--primary">{{ __('notify.finance_hub.accounts.transfer_submit') }}</button>
                    </form>
                </section>
            @endif

            @if($transfers->isNotEmpty())
                <section class="notify-fin-card" aria-labelledby="fin-transfers-title">
                    <div class="notify-fin-card__head">
                        <h2 id="fin-transfers-title" class="notify-fin-card__title">{{ __('notify.finance_hub.accounts.recent_transfers') }}</h2>
                    </div>
                    <div class="notify-fin-list notify-fin-list--compact">
                        @foreach($transfers as $transfer)
                            <article class="notify-fin-movement @if($transfer->reversal) is-muted @endif">
                                <div class="notify-fin-movement__main">
                                    <strong>{{ $transfer->fromAccount ? $accountName($transfer->fromAccount) : '' }} → {{ $transfer->toAccount ? $accountName($transfer->toAccount) : '' }}</strong>
                                    <small dir="ltr">{{ $transfer->transferred_at?->format('Y-m-d') }}</small>
                                    @if($transfer->notes)<small>{{ $transfer->notes }}</small>@endif
                                </div>
                                <x-notify.money class="notify-fin-movement__amount" :minor="$transfer->amount_minor" />
                                @if($transfer->reversal)
                                    <span class="notify-fin-chip notify-fin-chip--danger">{{ __('notify.finance_hub.accounts.reversed') }}</span>
                                @elseif($canTransfer)
                                    <details class="notify-fin-more">
                                        <summary>{{ __('notify.finance_hub.accounts.reverse') }}</summary>
                                        <form method="POST" action="{{ route('financial-transfers.reverse', $transfer) }}" class="notify-fin-inline-form">
                                            @csrf
                                            <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                            <label for="reverse-transfer-{{ $transfer->id }}">{{ __('notify.finance_hub.accounts.reverse_reason') }}</label>
                                            <input id="reverse-transfer-{{ $transfer->id }}" name="reason" required maxlength="1000">
                                            <button type="submit" class="notify-button notify-button--ghost">{{ __('notify.finance_hub.accounts.reverse') }}</button>
                                        </form>
                                    </details>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>

    {{-- Historical unassigned cash events: only when any exist (§10.4) --}}
    @if($unassignedCount > 0 && $canAssignHistorical)
        <section class="notify-fin-card" aria-labelledby="fin-unassigned-title" data-unassigned-events>
            <div class="notify-fin-card__head">
                <h2 id="fin-unassigned-title" class="notify-fin-card__title">{{ __('notify.finance_hub.accounts.unassigned_title') }}</h2>
                <span class="notify-fin-chip notify-fin-chip--warning" dir="ltr">{{ $unassignedCount }}</span>
            </div>
            <div class="notify-fin-list notify-fin-list--compact">
                @foreach([['payment', $unassignedPayments], ['refund', $unassignedRefunds]] as [$type, $events])
                    @foreach($events as $event)
                        <article class="notify-fin-movement">
                            <div class="notify-fin-movement__main">
                                <strong>{{ $event->client?->business_name }}</strong>
                                <small>{{ __('notify.finance_hub.accounts.unassigned_'.$type) }} · <span dir="ltr">{{ ($type === 'payment' ? $event->received_at : $event->refunded_at)?->format('Y-m-d') }}</span></small>
                            </div>
                            <x-notify.money class="notify-fin-movement__amount" :minor="(int) $event->amount_minor" />
                            <form method="POST" action="{{ route('cash-events.assign-account') }}" class="notify-fin-inline-form notify-fin-inline-form--row">
                                @csrf
                                <input type="hidden" name="event_type" value="{{ $type }}">
                                <input type="hidden" name="event_id" value="{{ $event->id }}">
                                <select name="financial_account_id" aria-label="{{ __('notify.finance_hub.accounts.assign') }}">
                                    @foreach($v1Accounts as $account)
                                        <option value="{{ $account->id }}">{{ $accountName($account) }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="notify-button notify-button--soft">{{ __('notify.finance_hub.accounts.assign') }}</button>
                            </form>
                        </article>
                    @endforeach
                @endforeach
            </div>
        </section>
    @endif

    @if($otherAccounts->isNotEmpty())
        <details class="notify-fin-card notify-fin-disclosure" data-other-accounts>
            <summary>{{ __('notify.finance_hub.accounts.other_accounts') }}</summary>
            <div class="notify-fin-list notify-fin-list--compact">
                @foreach($otherAccounts as $row)
                    <article class="notify-fin-movement">
                        <div class="notify-fin-movement__main">
                            <strong>{{ $accountName($row['account']) }}</strong>
                            @unless($row['account']->is_active)<small>{{ __('notify.finance_hub.accounts.archived') }}</small>@endunless
                        </div>
                        <x-notify.money class="notify-fin-movement__amount" :minor="$row['balance_minor']" />
                    </article>
                @endforeach
            </div>
        </details>
    @endif
</div>
@endsection
