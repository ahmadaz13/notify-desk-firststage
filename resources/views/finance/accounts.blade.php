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
            <x-notify.list data-account-movements :label="__('notify.finance_hub.accounts.movements_title', ['account' => $selected ? $accountName($selected) : ''])" :empty="$movements->isEmpty()"
                :columns="[__('notify.finance_hub.accounts.columns.movement'), ['label' => __('notify.finance_hub.accounts.columns.amount'), 'class' => 'is-num'], __('notify.finance_hub.accounts.columns.date'), ['label' => __('notify.finance_hub.accounts.columns.balance'), 'class' => 'is-num']]">
                @foreach($movements as $row)
                    <tr>
                        <td class="notify-list__primary"><span class="notify-list__title">{{ $eventLabel($row['movement']->event_type) }}</span></td>
                        <td class="notify-list__end notify-list__amount">
                            <x-notify.money class="notify-fin-movement__amount {{ $row['signed_minor'] >= 0 ? 'is-in' : 'is-out' }}" :minor="$row['signed_minor']" :signed="true" />
                        </td>
                        <td data-label="{{ __('notify.finance_hub.accounts.columns.date') }}"><span dir="ltr">{{ $row['movement']->occurred_at?->format('Y-m-d H:i') }}</span></td>
                        <td class="notify-list__amount" data-label="{{ __('notify.finance_hub.accounts.running') }}"><x-notify.money :minor="$row['running_minor']" /></td>
                    </tr>
                @endforeach
                <x-slot:emptyState>
                    <x-notify.empty-state :compact="true" icon="landmark" :title="__('notify.finance_hub.accounts.empty_movements')" />
                </x-slot:emptyState>
                @if($movements->hasPages())
                    <x-slot:footer>{{ $movements->links() }}</x-slot:footer>
                @endif
            </x-notify.list>
        </section>

        <div class="notify-fin-stack">
            {{-- Internal transfer (Owner): neither revenue nor expense --}}
            @if($canTransfer && $v1Accounts->count() === 2)
                <section class="notify-fin-card" aria-labelledby="fin-transfer-title">
                    <div class="notify-fin-card__head">
                        <h2 id="fin-transfer-title" class="notify-fin-card__title">{{ __('notify.finance_hub.accounts.transfer_title') }}</h2>
                    </div>
                    <p class="notify-fin-hint">{{ __('notify.finance_hub.accounts.transfer_hint') }}</p>
                    @php $old = \App\Support\FormState::oldFor('account-transfer'); @endphp
                    @formscope('account-transfer')
                    <form method="POST" action="{{ route('financial-transfers.store') }}" class="notify-form-row" data-transfer-form>
                        @csrf
                        <input type="hidden" name="_form" value="account-transfer">
                        <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <x-notify.form-field :label="__('notify.finance_hub.accounts.direction')" name="direction" :required="true" :group="true" class="notify-form-row__full">
                            <div class="notify-choices notify-choices--segmented">
                                @foreach(['cash_to_cliq', 'cliq_to_cash'] as $direction)
                                    <label class="notify-choice-chip">
                                        <input type="radio" name="direction" value="{{ $direction }}" required @checked($old('direction', 'cash_to_cliq') === $direction)>
                                        <span>{{ __('notify.finance_hub.accounts.'.$direction) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </x-notify.form-field>
                        <x-notify.form-field :label="__('notify.finance_hub.accounts.amount')" for="transfer-amount" name="amount" :required="true" class="notify-form-row__full">
                            <x-notify.money-input id="transfer-amount" :required="true" :value="$old('amount')" />
                        </x-notify.form-field>
                        <x-notify.form-field :label="__('notify.finance_hub.accounts.date')" for="transfer-date" name="transferred_at" :required="true" class="notify-form-row__full">
                            <input id="transfer-date" class="notify-input" type="date" name="transferred_at" required value="{{ $old('transferred_at', now()->toDateString()) }}" @invalid('transferred_at', 'transfer-date')>
                        </x-notify.form-field>
                        <x-notify.form-field :label="__('notify.finance_hub.accounts.note')" for="transfer-note" name="notes" :optional="true" class="notify-form-row__full">
                            <input id="transfer-note" class="notify-input" name="notes" maxlength="1000" value="{{ $old('notes') }}" @invalid('notes', 'transfer-note')>
                        </x-notify.form-field>
                        <div class="notify-form-row__full notify-form-actions">
                            <button type="submit" class="notify-button notify-button--primary">{{ __('notify.finance_hub.accounts.transfer_submit') }}</button>
                        </div>
                    </form>
                    @endformscope
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
                                    <span class="notify-status notify-status--danger">{{ __('notify.finance_hub.accounts.reversed') }}</span>
                                @elseif($canTransfer)
                                    <x-notify.menu>
                                        <x-slot:danger>
                                            <button type="button" class="notify-menu__item notify-menu__item--danger" role="menuitem" data-open-sheet="reverse-transfer-{{ $transfer->id }}" aria-haspopup="dialog">
                                                <x-notify.icon name="x" :size="18" /><span>{{ __('notify.finance_hub.accounts.reverse') }}</span>
                                            </button>
                                        </x-slot:danger>
                                    </x-notify.menu>
                                @endif
                            </article>
                        @endforeach
                    </div>

                    @if($canTransfer)
                        @foreach($transfers as $transfer)
                            @continue($transfer->reversal)
                            @include('finance.partials.reverse-sheet', [
                                'sheetId' => 'reverse-transfer-'.$transfer->id,
                                'title' => __('notify.finance_hub.accounts.reverse'),
                                'subtitle' => ($transfer->fromAccount ? $accountName($transfer->fromAccount) : '').' → '.($transfer->toAccount ? $accountName($transfer->toAccount) : ''),
                                'action' => route('financial-transfers.reverse', $transfer),
                                'label' => __('notify.finance_hub.accounts.reverse_reason'),
                                'minlength' => null,
                            ])
                        @endforeach
                    @endif
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
