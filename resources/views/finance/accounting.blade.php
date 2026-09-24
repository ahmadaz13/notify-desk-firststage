@extends('layouts.app')

@php
    use App\Support\Permissions;

    $user = auth()->user();
    $accountName = fn ($account) => $account
        ? (app()->getLocale() === 'en' ? ($account->name_en ?: $account->name_ar) : ($account->name_ar ?: $account->name_en))
        : '';
    $humanKey = fn (string $key) => \Illuminate\Support\Facades\Lang::has('notify.finance_hub.accounting.checks.'.$key)
        ? __('notify.finance_hub.accounting.checks.'.$key)
        : ucfirst(str_replace('_', ' ', $key));
    $tools = [
        ['permission' => Permissions::RUN_ACCOUNTING_BACKFILL, 'route' => 'accounting.backfill', 'key' => 'accounting_backfill'],
        ['permission' => Permissions::RUN_REVENUE_RECOGNITION, 'route' => 'accounting.revenue-schedules.backfill', 'key' => 'schedules_backfill'],
        ['permission' => Permissions::RUN_REVENUE_RECOGNITION, 'route' => 'accounting.revenue-recognition.run', 'key' => 'recognition_run', 'through' => true],
        ['permission' => Permissions::RUN_SUBSCRIPTION_BILLING, 'route' => 'subscription-billing.generate-renewals', 'key' => 'renewals_generate', 'through' => true],
        ['permission' => Permissions::RESOLVE_SUBSCRIPTION_BILLING_REVIEWS, 'route' => 'subscription-billing.backfill-periods', 'key' => 'periods_backfill'],
    ];
    $tools = array_values(array_filter($tools, fn (array $tool) => Permissions::allows($user, $tool['permission'])));
@endphp

@section('content')
<div class="notify-fin" data-finance-page="accounting">
    @include('finance.partials.header', [
        'active' => 'accounting',
        'title' => __('notify.finance_hub.sections.accounting'),
        'subtitle' => __('notify.finance_hub.accounting.subtitle'),
    ])

    <nav class="notify-fin-tabs" aria-label="{{ __('notify.finance_hub.sections.accounting') }}">
        @foreach(\App\Http\Controllers\AccountingController::TABS as $key)
            <a href="{{ route('finance.accounting', ['tab' => $key]) }}" class="notify-fin-tabs__item @if($tab === $key) is-active @endif" data-accounting-tab="{{ $key }}" @if($tab === $key) aria-current="page" @endif>{{ __('notify.finance_hub.accounting.tabs.'.$key) }}</a>
        @endforeach
    </nav>

    <section class="notify-fin-card" data-accounting-panel="{{ $tab }}">
        @if($tab === 'accounts')
            <div class="notify-fin-card__head">
                <h2 class="notify-fin-card__title">{{ __('notify.finance_hub.accounting.tabs.accounts') }}</h2>
                <span class="notify-fin-chip {{ $trialBalance['difference_minor'] === 0 ? 'notify-fin-chip--success' : 'notify-fin-chip--danger' }}">
                    {{ $trialBalance['difference_minor'] === 0 ? __('notify.finance_hub.accounting.balanced') : __('notify.finance_hub.accounting.unbalanced') }}
                </span>
            </div>
            @if($selectedAccount)
                <div class="notify-fin-subpanel" data-account-ledger>
                    <div class="notify-fin-card__head">
                        <h3 class="notify-fin-card__subtitle">{{ __('notify.finance_hub.accounting.ledger_for', ['account' => $accountName($selectedAccount)]) }} <span dir="ltr">({{ $selectedAccount->code }})</span></h3>
                        <a class="notify-fin-link" href="{{ route('finance.accounting', ['tab' => 'accounts']) }}">{{ __('notify.finance_hub.accounting.close') }}</a>
                    </div>
                    <div class="notify-fin-table-wrap">
                        <table class="notify-fin-table">
                            <thead><tr><th scope="col">{{ __('notify.common.date') }}</th><th scope="col">{{ __('notify.common.description') }}</th><th scope="col">{{ __('notify.finance_hub.accounting.debit') }}</th><th scope="col">{{ __('notify.finance_hub.accounting.credit') }}</th><th scope="col">{{ __('notify.finance_hub.accounting.balance') }}</th></tr></thead>
                            <tbody>
                                @forelse($ledger as $row)
                                    <tr>
                                        <td dir="ltr">{{ $row['line']->entry?->entry_date?->format('Y-m-d') }}</td>
                                        <td>{{ $row['line']->entry?->description }}</td>
                                        <td><x-notify.money :minor="(int) $row['line']->debit_minor" /></td>
                                        <td><x-notify.money :minor="(int) $row['line']->credit_minor" /></td>
                                        <td><x-notify.money :minor="(int) ($row['running_balance_minor'] ?? 0)" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5">{{ __('notify.finance_hub.accounting.no_lines') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
            <div class="notify-fin-table-wrap">
                <table class="notify-fin-table" data-chart-of-accounts>
                    <thead><tr><th scope="col">{{ __('notify.finance_hub.accounting.account') }}</th><th scope="col">{{ __('notify.finance_hub.accounting.debit') }}</th><th scope="col">{{ __('notify.finance_hub.accounting.credit') }}</th><th scope="col">{{ __('notify.finance_hub.accounting.balance') }}</th></tr></thead>
                    <tbody>
                        @foreach($trialBalance['rows'] as $row)
                            <tr @if(! $row['account']->allow_direct_posting) class="is-group" @endif>
                                <td>
                                    @if($row['account']->allow_direct_posting)
                                        <a href="{{ route('finance.accounting', ['tab' => 'accounts', 'account_id' => $row['account']->id]) }}">{{ $accountName($row['account']) }}</a>
                                    @else
                                        {{ $accountName($row['account']) }}
                                    @endif
                                    <small class="notify-fin-code" dir="ltr">{{ $row['account']->code }}</small>
                                </td>
                                <td><x-notify.money :minor="$row['debit_minor']" /></td>
                                <td><x-notify.money :minor="$row['credit_minor']" /></td>
                                <td><x-notify.money :minor="$row['net_minor']" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @elseif($tab === 'journal')
            <div class="notify-fin-card__head">
                <h2 class="notify-fin-card__title">{{ __('notify.finance_hub.accounting.tabs.journal') }}</h2>
                <span class="notify-fin-hint">{{ __('notify.finance_hub.accounting.journal_read_only') }}</span>
            </div>
            @if($selectedEntry)
                <div class="notify-fin-subpanel" data-journal-entry>
                    <div class="notify-fin-card__head">
                        <h3 class="notify-fin-card__subtitle"><span dir="ltr">{{ $selectedEntry->journal_number }}</span> · {{ $selectedEntry->description }}</h3>
                        <a class="notify-fin-link" href="{{ route('finance.accounting', ['tab' => 'journal'] + request()->only('page')) }}">{{ __('notify.finance_hub.accounting.close') }}</a>
                    </div>
                    <div class="notify-fin-table-wrap">
                        <table class="notify-fin-table">
                            <thead><tr><th scope="col">{{ __('notify.finance_hub.accounting.account') }}</th><th scope="col">{{ __('notify.finance_hub.accounting.debit') }}</th><th scope="col">{{ __('notify.finance_hub.accounting.credit') }}</th></tr></thead>
                            <tbody>
                                @foreach($selectedEntry->lines as $line)
                                    <tr>
                                        <td>{{ $accountName($line->chartAccount) }} <small class="notify-fin-code" dir="ltr">{{ $line->chartAccount?->code }}</small>@if($line->client)<small> · {{ $line->client->business_name }}</small>@endif</td>
                                        <td>@if($line->debit_minor)<x-notify.money :minor="(int) $line->debit_minor" />@endif</td>
                                        <td>@if($line->credit_minor)<x-notify.money :minor="(int) $line->credit_minor" />@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
            <div class="notify-fin-list notify-fin-list--compact" data-journal-list>
                @forelse($entries as $entry)
                    <a class="notify-fin-movement" href="{{ route('finance.accounting', ['tab' => 'journal', 'entry' => $entry->id] + request()->only('page')) }}">
                        <div class="notify-fin-movement__main">
                            <strong>{{ $entry->description }}</strong>
                            <small><span dir="ltr">{{ $entry->entry_date?->format('Y-m-d') }} · {{ $entry->journal_number }}</span></small>
                        </div>
                        <x-notify.money class="notify-fin-movement__amount" :minor="(int) $entry->total_debit_minor" />
                    </a>
                @empty
                    <p class="notify-fin-empty">{{ __('notify.finance_hub.accounting.no_entries') }}</p>
                @endforelse
            </div>
            @if($entries->hasPages())
                <div class="notify-fin-pagination">{{ $entries->links() }}</div>
            @endif

        @elseif($tab === 'reconciliation')
            <div class="notify-fin-card__head">
                <h2 class="notify-fin-card__title">{{ __('notify.finance_hub.accounting.tabs.reconciliation') }}</h2>
                <span class="notify-fin-chip {{ $reconciliationResult['ok'] ? 'notify-fin-chip--success' : 'notify-fin-chip--danger' }}" data-reconciliation-status>
                    {{ $reconciliationResult['ok'] ? __('notify.statuses.reconciled') : __('notify.statuses.needs_review') }}
                </span>
            </div>
            @if($reconciliationResult['failures'])
                <ul class="notify-fin-failures">
                    @foreach($reconciliationResult['failures'] as $failure)
                        <li>{{ $humanKey($failure) }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="notify-fin-table-wrap">
                <table class="notify-fin-table">
                    <thead><tr><th scope="col">{{ __('notify.finance_hub.accounting.check') }}</th><th scope="col">{{ __('notify.finance_hub.accounting.operational') }}</th><th scope="col">{{ __('notify.finance_hub.accounting.general_ledger') }}</th><th scope="col">{{ __('notify.finance_hub.accounting.difference') }}</th></tr></thead>
                    <tbody>
                        @foreach($reconciliationResult['cash_accounts'] as $row)
                            <tr @if($row['difference_minor'] !== 0) class="is-alert" @endif>
                                <td>{{ $accountName($row['financial_account']) }}</td>
                                <td><x-notify.money :minor="$row['cash_subledger_minor']" /></td>
                                <td><x-notify.money :minor="$row['general_ledger_minor']" /></td>
                                <td><x-notify.money :minor="$row['difference_minor']" /></td>
                            </tr>
                        @endforeach
                        @foreach(['billing', 'recognized_revenue'] as $group)
                            @foreach($reconciliationResult[$group] ?? [] as $key => $row)
                                <tr @if(($row['difference_minor'] ?? 0) !== 0) class="is-alert" @endif>
                                    <td>{{ $humanKey($key) }}@if($group === 'recognized_revenue') <small>· {{ __('notify.finance_hub.accounting.checks.recognized_group') }}</small>@endif</td>
                                    <td><x-notify.money :minor="(int) ($row['operational_minor'] ?? 0)" /></td>
                                    <td><x-notify.money :minor="(int) ($row['general_ledger_minor'] ?? 0)" /></td>
                                    <td><x-notify.money :minor="(int) ($row['difference_minor'] ?? 0)" /></td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

        @elseif($tab === 'periods')
            <div class="notify-fin-card__head">
                <h2 class="notify-fin-card__title">{{ __('notify.finance_hub.accounting.tabs.periods') }}</h2>
            </div>
            <div class="notify-fin-list notify-fin-list--compact">
                @forelse($periods as $accountingPeriod)
                    <article class="notify-fin-movement">
                        <div class="notify-fin-movement__main">
                            <strong dir="ltr">{{ $accountingPeriod->period_key }}</strong>
                            <small dir="ltr">{{ $accountingPeriod->start_date?->format('Y-m-d') }} → {{ $accountingPeriod->end_date?->format('Y-m-d') }}</small>
                        </div>
                        <span class="notify-fin-chip {{ $accountingPeriod->status === 'open' ? 'notify-fin-chip--success' : '' }}">{{ $accountingPeriod->status === 'open' ? __('notify.statuses.open') : __('notify.statuses.closed') }}</span>
                        @if(Permissions::allows($user, Permissions::MANAGE_ACCOUNTING_PERIODS))
                            <details class="notify-fin-more">
                                <summary>{{ $accountingPeriod->status === 'open' ? __('notify.finance_hub.accounting.close_period') : __('notify.finance_hub.accounting.reopen_period') }}</summary>
                                @if($accountingPeriod->status === 'open')
                                    <form method="POST" action="{{ route('accounting.periods.close', $accountingPeriod) }}" class="notify-fin-inline-form" onsubmit="return confirm(@js(__('notify.finance_hub.accounting.close_confirm')))">
                                        @csrf
                                        <label for="period-notes-{{ $accountingPeriod->id }}">{{ __('notify.common.notes') }}</label>
                                        <input id="period-notes-{{ $accountingPeriod->id }}" name="notes" maxlength="1000">
                                        <button type="submit" class="notify-button notify-button--ghost">{{ __('notify.finance_hub.accounting.close_period') }}</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('accounting.periods.reopen', $accountingPeriod) }}" class="notify-fin-inline-form">
                                        @csrf
                                        <label for="period-reason-{{ $accountingPeriod->id }}">{{ __('notify.finance_hub.accounting.reopen_reason') }}</label>
                                        <input id="period-reason-{{ $accountingPeriod->id }}" name="reason" required maxlength="1000">
                                        <button type="submit" class="notify-button notify-button--ghost">{{ __('notify.finance_hub.accounting.reopen_period') }}</button>
                                    </form>
                                @endif
                            </details>
                        @endif
                    </article>
                @empty
                    <p class="notify-fin-empty">{{ __('notify.finance_hub.accounting.no_periods') }}</p>
                @endforelse
            </div>

        @else
            @php($recognition = $revenueRecognitionDashboard)
            <div class="notify-fin-card__head">
                <h2 class="notify-fin-card__title">{{ __('notify.finance_hub.accounting.tabs.recognition') }}</h2>
            </div>
            <dl class="notify-fin-stats">
                <div><dt>{{ __('notify.finance_hub.accounting.due_periods') }}</dt><dd dir="ltr">{{ $recognition['due_period_count'] }}</dd></div>
                <div><dt>{{ __('notify.finance_hub.accounting.needs_review') }}</dt><dd dir="ltr">{{ $recognition['needs_review_count'] }}</dd></div>
                <div><dt>{{ __('notify.finance_hub.accounting.recognized_mtd') }}</dt><dd><x-notify.money :minor="$recognition['recognized_saas_mtd_minor'] + $recognition['recognized_one_time_mtd_minor']" /></dd></div>
                <div><dt>{{ __('notify.finance_hub.accounting.deferred_remaining') }}</dt><dd><x-notify.money :minor="$recognition['deferred_remaining_minor']" /></dd></div>
            </dl>
            <h3 class="notify-fin-card__subtitle">{{ __('notify.finance_hub.accounting.review_queue') }}</h3>
            <div class="notify-fin-list notify-fin-list--compact" data-recognition-review>
                @forelse($recognition['review_queue'] as $schedule)
                    <article class="notify-fin-movement">
                        <div class="notify-fin-movement__main">
                            <strong dir="ltr">{{ $schedule->invoice?->invoice_number }}</strong>
                            <small>{{ $schedule->invoiceLine?->description_snapshot }}</small>
                        </div>
                        <x-notify.money class="notify-fin-movement__amount" :minor="(int) $schedule->original_recognizable_minor" />
                        @if(Permissions::allows($user, Permissions::RESOLVE_REVENUE_RECOGNITION_REVIEWS))
                            <form method="POST" action="{{ route('accounting.revenue-recognition.confirm', $schedule) }}" class="notify-fin-inline-form notify-fin-inline-form--row">
                                @csrf
                                <label class="visually-hidden" for="recognition-date-{{ $schedule->id }}">{{ __('notify.finance_hub.accounting.recognition_date') }}</label>
                                <input id="recognition-date-{{ $schedule->id }}" type="date" name="recognition_date" required value="{{ now()->toDateString() }}">
                                <button type="submit" class="notify-button notify-button--soft">{{ __('notify.finance_hub.accounting.confirm_recognition') }}</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="notify-fin-empty">{{ __('notify.finance_hub.accounting.review_empty') }}</p>
                @endforelse
            </div>
        @endif
    </section>

    {{-- Advanced tools: collapsed, Owner-only, each run confirmed (§12.5) --}}
    @if($tools !== [])
        <details class="notify-fin-card notify-fin-disclosure notify-fin-tools" data-advanced-tools @if($toolsOpen) open @endif>
            <summary>{{ __('notify.finance_hub.accounting.tools_title') }}</summary>
            <p class="notify-fin-hint">{{ __('notify.finance_hub.accounting.tools_hint') }}</p>
            <div class="notify-fin-list notify-fin-list--compact">
                @foreach($tools as $tool)
                    <form method="POST" action="{{ route($tool['route']) }}" class="notify-fin-tool" data-tool="{{ $tool['key'] }}">
                        @csrf
                        <div class="notify-fin-movement__main">
                            <strong>{{ __('notify.finance_hub.accounting.tools.'.$tool['key']) }}</strong>
                            @if($tool['through'] ?? false)
                                <label class="notify-fin-tool__through">{{ __('notify.finance_hub.accounting.through') }} <input type="date" name="through" value="{{ now()->toDateString() }}"></label>
                            @endif
                        </div>
                        <div class="notify-fin-tool__actions">
                            <button type="submit" name="dry_run" value="1" class="notify-button notify-button--soft">{{ __('notify.finance_hub.accounting.preview') }}</button>
                            <button type="submit" name="dry_run" value="0" class="notify-button notify-button--ghost" onclick="return confirm(@js(__('notify.finance_hub.accounting.run_confirm')))">{{ __('notify.finance_hub.accounting.run') }}</button>
                        </div>
                    </form>
                @endforeach
            </div>
        </details>
    @endif
</div>
@endsection
