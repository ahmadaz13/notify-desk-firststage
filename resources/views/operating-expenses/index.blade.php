@extends('layouts.app')

@php
    use App\Services\PaymentFinancialAccountResolver;
    use App\Support\PaymentMethods;
    use App\Support\Permissions;

    $user = auth()->user();
    $canRecord = Permissions::allows($user, Permissions::MANAGE_EXPENSES);
    $canRecurring = Permissions::allows($user, Permissions::MANAGE_RECURRING_EXPENSES);
    $canCategories = Permissions::allows($user, Permissions::MANAGE_EXPENSE_CATEGORIES);
    $categoryName = fn ($category) => app()->getLocale() === 'en' ? ($category?->name_en ?: $category?->displayName()) : $category?->displayName();
    $methodLabel = fn ($account) => ($method = PaymentFinancialAccountResolver::methodForAccount($account))
        ? PaymentMethods::label($method)
        : __('notify.expenses.method_other');
    $money = fn (int $minor) => \App\Support\Money::fromMinorUnits($minor)->format();
    $tabs = ['expenses' => null, 'recurring' => $dueCount, 'categories' => null];
@endphp

@section('content')
<div class="notify-fin" data-finance-page="expenses">
    @include('finance.partials.header', [
        'active' => 'expenses',
        'title' => __('notify.finance_hub.sections.expenses'),
        'subtitle' => __('notify.expenses.subtitle'),
    ])

    <nav class="notify-fin-tabs" aria-label="{{ __('notify.finance_hub.sections.expenses') }}">
        @foreach($tabs as $key => $count)
            <a href="{{ route('finance.expenses', $key === 'expenses' ? [] : ['tab' => $key]) }}" class="notify-fin-tabs__item @if($tab === $key) is-active @endif" data-expenses-tab="{{ $key }}" @if($tab === $key) aria-current="page" @endif>
                {{ __('notify.expenses.tabs.'.$key) }}
                @if($count)<span class="notify-fin-tabs__count is-warning" dir="ltr">{{ $count }}</span>@endif
            </a>
        @endforeach
    </nav>

    @if($tab === 'expenses')
        {{-- One-time company expense (§12.3): Cash → CASH-BOX, CliQ → CLIQ, resolved server-side. --}}
        @if($canRecord)
            <section class="notify-fin-card" aria-labelledby="expense-form-title">
                <div class="notify-fin-card__head">
                    <h2 id="expense-form-title" class="notify-fin-card__title">{{ __('notify.expenses.one_time_title') }}</h2>
                    <span class="notify-fin-kpi__meta">{{ __('notify.expenses.this_month') }} <x-notify.money :minor="$monthMinor" /></span>
                </div>
                <p class="notify-fin-hint">{{ __('notify.expenses.one_time_hint') }}</p>
                @if($activeCategories->isEmpty())
                    <p class="notify-fin-empty">{{ __('notify.expenses.no_categories') }} <a href="{{ route('finance.expenses', ['tab' => 'categories']) }}">{{ __('notify.expenses.tabs.categories') }}</a></p>
                @else
                    <form method="POST" action="{{ route('operating-expenses.store') }}" class="notify-fin-form notify-fin-form--grid" data-expense-form="one-time">
                        @csrf
                        <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <div class="notify-fin-field">
                            <label for="expense-amount">{{ __('notify.expenses.amount') }}</label>
                            <input id="expense-amount" name="amount" inputmode="decimal" dir="ltr" required value="{{ old('amount') }}" placeholder="0.000">
                        </div>
                        <fieldset class="notify-fin-choice">
                            <legend>{{ __('notify.expenses.method') }}</legend>
                            @foreach($methods as $method => $label)
                                <label class="notify-fin-choice__option">
                                    <input type="radio" name="payment_method" value="{{ $method }}" required @checked(old('payment_method', 'cash') === $method)>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                        <div class="notify-fin-field">
                            <label for="expense-category">{{ __('notify.expenses.category') }}</label>
                            <select id="expense-category" name="category_id" required>
                                @foreach($activeCategories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $categoryName($category) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="notify-fin-field">
                            <label for="expense-date">{{ __('notify.expenses.expense_date') }}</label>
                            <input id="expense-date" type="date" name="expense_date" required max="{{ today()->toDateString() }}" value="{{ old('expense_date', today()->toDateString()) }}">
                        </div>
                        <div class="notify-fin-field notify-fin-field--wide">
                            <label for="expense-description">{{ __('notify.expenses.description') }} <span class="notify-label-note">({{ __('notify.common.optional') }})</span></label>
                            <input id="expense-description" name="description" maxlength="255" value="{{ old('description') }}">
                        </div>
                        <div class="notify-fin-form__submit">
                            <button type="submit" class="notify-button notify-button--primary">{{ __('notify.expenses.save') }}</button>
                        </div>
                    </form>
                @endif
            </section>
        @endif

        <section class="notify-fin-card" aria-labelledby="expense-list-title">
            <div class="notify-fin-card__head">
                <h2 id="expense-list-title" class="notify-fin-card__title">{{ __('notify.expenses.recent_expenses') }}</h2>
            </div>
            <div class="notify-fin-rows" data-expense-list>
                @if($recentExpenses->isNotEmpty())
                    <div class="notify-fin-rows__head" aria-hidden="true">
                        <span>{{ __('notify.expenses.col_date') }}</span>
                        <span>{{ __('notify.expenses.col_category') }}</span>
                        <span>{{ __('notify.expenses.col_description') }}</span>
                        <span>{{ __('notify.expenses.col_method') }}</span>
                        <span>{{ __('notify.expenses.col_amount') }}</span>
                        <span>{{ __('notify.expenses.col_status') }}</span>
                        <span></span>
                    </div>
                @endif
                @forelse($recentExpenses as $expense)
                    @php($isMonthly = $expense->recurring_expense_obligation_id !== null)
                    <article class="notify-fin-row @if($expense->reversal) is-muted @endif" data-expense-row data-expense-type="{{ $isMonthly ? 'monthly' : 'one-time' }}">
                        <span class="notify-fin-row__date"><span dir="ltr">{{ $expense->paid_at?->format('Y-m-d') }}</span></span>
                        <span class="notify-fin-row__title">{{ $expense->categoryModel ? $categoryName($expense->categoryModel) : $expense->category_name_snapshot }}</span>
                        <span class="notify-fin-row__desc @unless($expense->description) is-empty @endunless">{{ $expense->description ?: '—' }}</span>
                        <span class="notify-fin-row__method">{{ $methodLabel($expense->financialAccount) }}</span>
                        <x-notify.money class="notify-fin-row__amount" :minor="(int) $expense->amount_minor" />
                        <span class="notify-fin-row__status">
                            <span class="notify-fin-chip @if($isMonthly) notify-fin-chip--info @endif">{{ $isMonthly ? __('notify.expenses.type_monthly') : __('notify.expenses.type_one_time') }}</span>
                            @if($expense->reversal)
                                <span class="notify-fin-chip notify-fin-chip--danger">{{ __('notify.expenses.status_reversed') }}</span>
                            @endif
                        </span>
                        <span class="notify-fin-row__actions">
                            @if($canRecord && ! $expense->reversal)
                                <details class="notify-fin-menu">
                                    <summary aria-label="{{ __('notify.expenses.more') }}">…</summary>
                                    <form method="POST" action="{{ route('operating-expenses.reverse', $expense) }}" class="notify-fin-menu__panel notify-fin-inline-form">
                                        @csrf
                                        <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <label for="reverse-expense-{{ $expense->id }}">{{ __('notify.expenses.reverse_reason') }}</label>
                                        <input id="reverse-expense-{{ $expense->id }}" name="reason" required minlength="3" maxlength="1000">
                                        <button type="submit" class="notify-button notify-button--ghost">{{ __('notify.expenses.reverse') }}</button>
                                    </form>
                                </details>
                            @endif
                        </span>
                    </article>
                @empty
                    <p class="notify-fin-empty">{{ __('notify.expenses.empty_expenses') }}</p>
                @endforelse
            </div>
            @if($recentExpenses->hasPages())
                <div class="notify-fin-pagination">{{ $recentExpenses->links() }}</div>
            @endif
        </section>

    @elseif($tab === 'recurring')
        {{-- Obligations generated by the existing scheduler; paying one posts through the normal expense path. --}}
        @if($dueObligations->isNotEmpty())
            <section class="notify-fin-card" aria-labelledby="due-title" data-due-obligations>
                <div class="notify-fin-card__head">
                    <h2 id="due-title" class="notify-fin-card__title">{{ __('notify.expenses.due_title') }}</h2>
                </div>
                <div class="notify-fin-list">
                    @foreach($dueObligations as $obligation)
                        @php($isOverdue = $obligation->due_date->lt(today()))
                        @php($defaultMethod = PaymentFinancialAccountResolver::methodForAccount($obligation->defaultFinancialAccount) ?? 'cash')
                        <article class="notify-fin-item @if($isOverdue) is-overdue @endif" data-obligation>
                            <div class="notify-fin-item__main">
                                <div class="notify-fin-item__title">
                                    <span>{{ $obligation->template?->name ?? $obligation->category_name_snapshot }}</span>
                                    <span class="notify-fin-chip {{ $isOverdue ? 'notify-fin-chip--danger' : 'notify-fin-chip--warning' }}">{{ $isOverdue ? __('notify.expenses.overdue') : __('notify.expenses.due') }}</span>
                                </div>
                                <x-notify.money class="notify-fin-item__amount" :minor="(int) $obligation->expected_amount_minor" />
                                <p class="notify-fin-item__meta">
                                    {{ __('notify.expenses.due_on') }} <span dir="ltr">{{ $obligation->due_date->format('Y-m-d') }}</span>
                                    · {{ $obligation->category_name_snapshot }}
                                </p>
                            </div>
                            @if($canRecord)
                                <div class="notify-fin-item__actions">
                                    <details class="notify-fin-pay">
                                        <summary class="notify-button notify-button--primary">{{ __('notify.expenses.mark_paid') }}</summary>
                                        <form method="POST" action="{{ route('recurring-expense-obligations.pay', $obligation) }}" class="notify-fin-form" data-pay-obligation>
                                            @csrf
                                            <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                            <div class="notify-fin-field">
                                                <label for="pay-amount-{{ $obligation->id }}">{{ __('notify.expenses.amount') }}</label>
                                                <input id="pay-amount-{{ $obligation->id }}" name="amount" inputmode="decimal" dir="ltr" required value="{{ $money((int) $obligation->expected_amount_minor) }}">
                                            </div>
                                            <fieldset class="notify-fin-choice">
                                                <legend>{{ __('notify.expenses.method') }}</legend>
                                                @foreach($methods as $method => $label)
                                                    <label class="notify-fin-choice__option">
                                                        <input type="radio" name="payment_method" value="{{ $method }}" required @checked($defaultMethod === $method)>
                                                        <span>{{ $label }}</span>
                                                    </label>
                                                @endforeach
                                            </fieldset>
                                            <div class="notify-fin-field">
                                                <label for="pay-date-{{ $obligation->id }}">{{ __('notify.expenses.paid_on') }}</label>
                                                <input id="pay-date-{{ $obligation->id }}" type="date" name="paid_on" required max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}">
                                            </div>
                                            <button type="submit" class="notify-button notify-button--primary">{{ __('notify.expenses.confirm_paid') }}</button>
                                        </form>
                                    </details>
                                    @if($canRecurring)
                                        <details class="notify-fin-menu">
                                            <summary aria-label="{{ __('notify.expenses.more') }}">…</summary>
                                            <form method="POST" action="{{ route('recurring-expense-obligations.skip', $obligation) }}" class="notify-fin-menu__panel notify-fin-inline-form">
                                                @csrf
                                                <button type="submit" class="notify-button notify-button--ghost">{{ __('notify.expenses.skip') }}</button>
                                            </form>
                                        </details>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if($canRecurring)
            <section class="notify-fin-card" aria-labelledby="recurring-form-title">
                <div class="notify-fin-card__head">
                    <h2 id="recurring-form-title" class="notify-fin-card__title">{{ __('notify.expenses.recurring_title') }}</h2>
                </div>
                <p class="notify-fin-hint">{{ __('notify.expenses.recurring_hint') }}</p>
                @if($activeCategories->isEmpty())
                    <p class="notify-fin-empty">{{ __('notify.expenses.no_categories') }} <a href="{{ route('finance.expenses', ['tab' => 'categories']) }}">{{ __('notify.expenses.tabs.categories') }}</a></p>
                @else
                    <form method="POST" action="{{ route('recurring-expense-templates.store') }}" class="notify-fin-form notify-fin-form--grid" data-expense-form="monthly">
                        @csrf
                        <div class="notify-fin-field">
                            <label for="recurring-amount">{{ __('notify.expenses.amount') }}</label>
                            <input id="recurring-amount" name="amount" inputmode="decimal" dir="ltr" required value="{{ old('amount') }}" placeholder="0.000">
                        </div>
                        <fieldset class="notify-fin-choice">
                            <legend>{{ __('notify.expenses.method') }}</legend>
                            @foreach($methods as $method => $label)
                                <label class="notify-fin-choice__option">
                                    <input type="radio" name="payment_method" value="{{ $method }}" required @checked(old('payment_method', 'cash') === $method)>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                        <div class="notify-fin-field">
                            <label for="recurring-category">{{ __('notify.expenses.category') }}</label>
                            <select id="recurring-category" name="category_id" required>
                                @foreach($activeCategories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $categoryName($category) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="notify-fin-field">
                            <label for="recurring-start">{{ __('notify.expenses.start_date') }}</label>
                            <input id="recurring-start" type="date" name="start_date" required value="{{ old('start_date', today()->toDateString()) }}">
                        </div>
                        <div class="notify-fin-field">
                            <label for="recurring-day">{{ __('notify.expenses.due_day') }}</label>
                            <input id="recurring-day" type="number" name="due_day" inputmode="numeric" dir="ltr" required min="1" max="28" step="1" value="{{ old('due_day', min(28, today()->day)) }}">
                            <small class="notify-fin-hint">{{ __('notify.expenses.due_day_hint') }}</small>
                        </div>
                        <div class="notify-fin-field notify-fin-field--wide">
                            <label for="recurring-description">{{ __('notify.expenses.description') }} <span class="notify-label-note">({{ __('notify.common.optional') }})</span></label>
                            <input id="recurring-description" name="description" maxlength="255" value="{{ old('description') }}" placeholder="{{ __('notify.expenses.recurring_description_placeholder') }}">
                        </div>
                        <div class="notify-fin-form__submit">
                            <button type="submit" class="notify-button notify-button--primary">{{ __('notify.expenses.recurring_save') }}</button>
                        </div>
                    </form>
                @endif
            </section>
        @endif

        <section class="notify-fin-card" aria-labelledby="templates-title">
            <div class="notify-fin-card__head">
                <h2 id="templates-title" class="notify-fin-card__title">{{ __('notify.expenses.templates_title') }}</h2>
            </div>
            <div class="notify-fin-rows notify-fin-rows--recurring" data-recurring-list>
                @if($templates->isNotEmpty())
                    <div class="notify-fin-rows__head" aria-hidden="true">
                        <span>{{ __('notify.expenses.next_due') }}</span>
                        <span>{{ __('notify.expenses.col_description') }}</span>
                        <span>{{ __('notify.expenses.due_day_short') }}</span>
                        <span>{{ __('notify.expenses.col_method') }}</span>
                        <span>{{ __('notify.expenses.col_amount') }}</span>
                        <span>{{ __('notify.expenses.col_status') }}</span>
                        <span></span>
                    </div>
                @endif
                @forelse($templates as $template)
                    <article class="notify-fin-row @unless($template->is_active) is-muted @endunless" data-recurring-template>
                        <span class="notify-fin-row__date"><span dir="ltr">{{ $template->is_active ? $template->next_due_date?->format('Y-m-d') : '—' }}</span></span>
                        <span class="notify-fin-row__title">{{ $template->name }} <small>{{ $template->category ? $categoryName($template->category) : '' }}</small></span>
                        <span class="notify-fin-row__desc">{{ __('notify.expenses.day_of_month', ['day' => $template->next_due_date?->day ?? $template->start_date?->day]) }}</span>
                        <span class="notify-fin-row__method">{{ $methodLabel($template->defaultFinancialAccount) }}</span>
                        <x-notify.money class="notify-fin-row__amount" :minor="(int) $template->amount_minor" />
                        <span class="notify-fin-row__status">
                            <span class="notify-fin-chip @if($template->is_active) notify-fin-chip--success @endif">{{ $template->is_active ? __('notify.expenses.status_repeating') : __('notify.expenses.status_stopped') }}</span>
                        </span>
                        <span class="notify-fin-row__actions">
                            @if($canRecurring && $template->is_active)
                                <details class="notify-fin-menu">
                                    <summary aria-label="{{ __('notify.expenses.more') }}">…</summary>
                                    <div class="notify-fin-menu__panel">
                                        <form method="POST" action="{{ route('recurring-expense-templates.update', $template) }}" class="notify-fin-inline-form">
                                            @csrf
                                            @method('PATCH')
                                            <label for="template-amount-{{ $template->id }}">{{ __('notify.expenses.change_amount') }}</label>
                                            <input id="template-amount-{{ $template->id }}" name="amount" inputmode="decimal" dir="ltr" required value="{{ $template->amountJod() }}">
                                            <button type="submit" class="notify-button notify-button--soft">{{ __('notify.expenses.update_amount') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('recurring-expense-templates.update', $template) }}" class="notify-fin-inline-form" data-stop-recurring>
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="is_active" value="0">
                                            <small class="notify-fin-hint">{{ __('notify.expenses.stop_hint') }}</small>
                                            <button type="submit" class="notify-button notify-button--ghost">{{ __('notify.expenses.stop') }}</button>
                                        </form>
                                    </div>
                                </details>
                            @endif
                        </span>
                    </article>
                @empty
                    <p class="notify-fin-empty">{{ __('notify.expenses.templates_empty') }}</p>
                @endforelse
            </div>
        </section>

    @else
        {{-- Categories (§12.3 sub-page): the existing expense-category authority. --}}
        @if($canCategories)
            <section class="notify-fin-card" aria-labelledby="category-form-title">
                <div class="notify-fin-card__head">
                    <h2 id="category-form-title" class="notify-fin-card__title">{{ __('notify.expenses.add_category') }}</h2>
                </div>
                <form method="POST" action="{{ route('expense-categories.store') }}" class="notify-fin-form notify-fin-form--grid" data-category-form>
                    @csrf
                    <div class="notify-fin-field">
                        <label for="category-name-ar">{{ __('notify.expenses.category_name_ar') }}</label>
                        <input id="category-name-ar" name="name_ar" required maxlength="255" dir="rtl" value="{{ old('name_ar') }}">
                    </div>
                    <div class="notify-fin-field">
                        <label for="category-name-en">{{ __('notify.expenses.category_name_en') }} <span class="notify-label-note">({{ __('notify.common.optional') }})</span></label>
                        <input id="category-name-en" name="name_en" maxlength="255" dir="ltr" value="{{ old('name_en') }}">
                    </div>
                    <div class="notify-fin-form__submit">
                        <button type="submit" class="notify-button notify-button--primary">{{ __('notify.expenses.add_category') }}</button>
                    </div>
                </form>
            </section>
        @endif

        <section class="notify-fin-card" aria-labelledby="category-list-title">
            <div class="notify-fin-card__head">
                <h2 id="category-list-title" class="notify-fin-card__title">{{ __('notify.expenses.categories') }}</h2>
            </div>
            <p class="notify-fin-hint">{{ __('notify.expenses.categories_hint') }}</p>
            <div class="notify-fin-list notify-fin-list--compact" data-category-list>
                @forelse($categories as $category)
                    <article class="notify-fin-movement">
                        <div class="notify-fin-movement__main">
                            <strong>{{ $categoryName($category) }}</strong>
                            @if(app()->getLocale() !== 'en' && $category->name_en)<small dir="ltr">{{ $category->name_en }}</small>@endif
                        </div>
                        @if($canCategories)
                            <form method="POST" action="{{ route('expense-categories.archive', $category) }}">
                                @csrf
                                <button type="submit" class="notify-button notify-button--ghost">{{ __('notify.expenses.archive') }}</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="notify-fin-empty">{{ __('notify.expenses.categories_empty') }}</p>
                @endforelse
            </div>
        </section>
    @endif
</div>
@endsection
