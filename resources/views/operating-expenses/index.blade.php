@extends('layouts.app')

@php
    use App\Services\PaymentFinancialAccountResolver;
    use App\Support\FormState;
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
    $actionsColumn = ['label' => __('notify.expenses.more'), 'hidden' => true, 'class' => 'is-actions'];
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
                    <x-notify.empty-state :compact="true" icon="settings" :title="__('notify.expenses.no_categories')"
                        :action-href="route('finance.expenses', ['tab' => 'categories'])" :action-label="__('notify.expenses.tabs.categories')" action-icon="settings" action-variant="secondary" />
                @else
                    @php $old = FormState::oldFor('expense-one-time'); @endphp
                    @formscope('expense-one-time')
                    <form method="POST" action="{{ route('operating-expenses.store') }}" class="notify-form-row" data-expense-form="one-time">
                        @csrf
                        <input type="hidden" name="_form" value="expense-one-time">
                        <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <x-notify.form-field :label="__('notify.expenses.amount')" for="expense-amount" name="amount" :required="true">
                            <x-notify.money-input id="expense-amount" :required="true" :value="$old('amount')" />
                        </x-notify.form-field>
                        <x-notify.form-field :label="__('notify.expenses.method')" name="payment_method" :required="true" :group="true">
                            <div class="notify-choices notify-choices--segmented">
                                @foreach($methods as $method => $label)
                                    <label class="notify-choice-chip">
                                        <input type="radio" name="payment_method" value="{{ $method }}" required @checked($old('payment_method', 'cash') === $method)>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </x-notify.form-field>
                        <x-notify.form-field :label="__('notify.expenses.category')" for="expense-category" name="category_id" :required="true">
                            <select id="expense-category" class="notify-input" name="category_id" required @invalid('category_id', 'expense-category')>
                                @foreach($activeCategories as $category)
                                    <option value="{{ $category->id }}" @selected((string) $old('category_id') === (string) $category->id)>{{ $categoryName($category) }}</option>
                                @endforeach
                            </select>
                        </x-notify.form-field>
                        <x-notify.form-field :label="__('notify.expenses.expense_date')" for="expense-date" name="expense_date" :required="true">
                            <input id="expense-date" class="notify-input" type="date" name="expense_date" required max="{{ today()->toDateString() }}" value="{{ $old('expense_date', today()->toDateString()) }}" @invalid('expense_date', 'expense-date')>
                        </x-notify.form-field>
                        <x-notify.form-field :label="__('notify.expenses.description')" for="expense-description" name="description" :optional="true" class="notify-form-row__full">
                            <input id="expense-description" class="notify-input" name="description" maxlength="255" value="{{ $old('description') }}" @invalid('description', 'expense-description')>
                        </x-notify.form-field>
                        <div class="notify-form-row__full notify-form-actions">
                            <button type="submit" class="notify-button notify-button--primary">{{ __('notify.expenses.save') }}</button>
                        </div>
                    </form>
                    @endformscope
                @endif
            </section>
        @endif

        <section class="notify-fin-card" aria-labelledby="expense-list-title">
            <div class="notify-fin-card__head">
                <h2 id="expense-list-title" class="notify-fin-card__title">{{ __('notify.expenses.recent_expenses') }}</h2>
            </div>
            <x-notify.list data-expense-list :label="__('notify.expenses.recent_expenses')" :empty="$recentExpenses->isEmpty()"
                :columns="[__('notify.expenses.col_category'), ['label' => __('notify.expenses.col_amount'), 'class' => 'is-num'], __('notify.expenses.col_date'), __('notify.expenses.col_method'), __('notify.expenses.col_status'), $actionsColumn]">
                @foreach($recentExpenses as $expense)
                    @php $isMonthly = $expense->recurring_expense_obligation_id !== null; @endphp
                    <tr @class(['is-muted' => $expense->reversal]) data-expense-row data-expense-type="{{ $isMonthly ? 'monthly' : 'one-time' }}">
                        <td class="notify-list__primary">
                            <span class="notify-list__title">{{ $expense->categoryModel ? $categoryName($expense->categoryModel) : $expense->category_name_snapshot }}</span>
                            @if($expense->description)<span class="notify-list__sub">{{ $expense->description }}</span>@endif
                        </td>
                        <td class="notify-list__end notify-list__amount"><x-notify.money :minor="(int) $expense->amount_minor" /></td>
                        <td data-label="{{ __('notify.expenses.col_date') }}"><span dir="ltr">{{ $expense->paid_at?->format('Y-m-d') }}</span></td>
                        <td data-label="{{ __('notify.expenses.col_method') }}">{{ $methodLabel($expense->financialAccount) }}</td>
                        <td>
                            <span class="notify-status {{ $isMonthly ? 'notify-status--info' : 'notify-status--neutral' }}">{{ $isMonthly ? __('notify.expenses.type_monthly') : __('notify.expenses.type_one_time') }}</span>
                            @if($expense->reversal)
                                <span class="notify-status notify-status--danger">{{ __('notify.expenses.status_reversed') }}</span>
                            @endif
                        </td>
                        <td class="notify-list__actions">
                            @if($canRecord && ! $expense->reversal)
                                <x-notify.menu>
                                    <x-slot:danger>
                                        <button type="button" class="notify-menu__item notify-menu__item--danger" role="menuitem" data-open-sheet="reverse-expense-{{ $expense->id }}" aria-haspopup="dialog">
                                            <x-notify.icon name="x" :size="18" /><span>{{ __('notify.expenses.reverse') }}</span>
                                        </button>
                                    </x-slot:danger>
                                </x-notify.menu>
                            @endif
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState>
                    <x-notify.empty-state :compact="true" icon="receipt" :title="__('notify.expenses.empty_expenses')" />
                </x-slot:emptyState>
                @if($recentExpenses->hasPages())
                    <x-slot:footer>{{ $recentExpenses->links() }}</x-slot:footer>
                @endif
            </x-notify.list>

            @if($canRecord)
                @foreach($recentExpenses as $expense)
                    @continue($expense->reversal)
                    @include('finance.partials.reverse-sheet', [
                        'sheetId' => 'reverse-expense-'.$expense->id,
                        'title' => __('notify.expenses.reverse'),
                        'subtitle' => ($expense->categoryModel ? $categoryName($expense->categoryModel) : $expense->category_name_snapshot).' · '.$money((int) $expense->amount_minor).' '.__('notify.common.currency_jod'),
                        'action' => route('operating-expenses.reverse', $expense),
                        'label' => __('notify.expenses.reverse_reason'),
                        'minlength' => 3,
                    ])
                @endforeach
            @endif
        </section>

    @elseif($tab === 'recurring')
        {{-- Obligations generated by the existing scheduler; paying one posts through the normal expense path. --}}
        @if($dueObligations->isNotEmpty())
            <section class="notify-fin-card" aria-labelledby="due-title" data-due-obligations>
                <div class="notify-fin-card__head">
                    <h2 id="due-title" class="notify-fin-card__title">{{ __('notify.expenses.due_title') }}</h2>
                </div>
                <x-notify.list :label="__('notify.expenses.due_title')"
                    :columns="[__('notify.expenses.col_description'), ['label' => __('notify.expenses.col_amount'), 'class' => 'is-num'], __('notify.expenses.col_date'), __('notify.expenses.col_status'), $actionsColumn]">
                    @foreach($dueObligations as $obligation)
                        @php $isOverdue = $obligation->due_date->lt(today()); @endphp
                        <tr @class(['is-overdue' => $isOverdue]) data-obligation>
                            <td class="notify-list__primary">
                                <span class="notify-list__title">{{ $obligation->template?->name ?? $obligation->category_name_snapshot }}</span>
                                <span class="notify-list__sub">{{ $obligation->category_name_snapshot }}</span>
                            </td>
                            <td class="notify-list__end notify-list__amount"><x-notify.money :minor="(int) $obligation->expected_amount_minor" /></td>
                            <td data-label="{{ __('notify.expenses.due_on') }}"><span dir="ltr">{{ $obligation->due_date->format('Y-m-d') }}</span></td>
                            <td>
                                <span class="notify-status {{ $isOverdue ? 'notify-status--danger' : 'notify-status--warning' }}">
                                    @if($isOverdue)<x-notify.icon name="alert-circle" :size="14" />@endif
                                    {{ $isOverdue ? __('notify.expenses.overdue') : __('notify.expenses.due') }}
                                </span>
                            </td>
                            <td class="notify-list__actions">
                                @if($canRecord)
                                    <span class="notify-list__actions-inner">
                                        <button type="button" class="notify-button notify-button--primary notify-button--sm" data-open-sheet="pay-obligation-{{ $obligation->id }}" aria-haspopup="dialog">{{ __('notify.expenses.mark_paid') }}</button>
                                        @if($canRecurring)
                                            <x-notify.menu>
                                                <form method="POST" action="{{ route('recurring-expense-obligations.skip', $obligation) }}"
                                                      data-confirm="{{ __('notify.expenses.skip') }}: {{ $obligation->template?->name ?? $obligation->category_name_snapshot }}" data-confirm-tone="primary" data-confirm-label="{{ __('notify.expenses.skip') }}">
                                                    @csrf
                                                    <button type="submit" class="notify-menu__item" role="menuitem"><x-notify.icon name="chevron-left" :size="18" class="notify-icon--directional" /><span>{{ __('notify.expenses.skip') }}</span></button>
                                                </form>
                                            </x-notify.menu>
                                        @endif
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-notify.list>

                @if($canRecord)
                    @foreach($dueObligations as $obligation)
                        @php
                            $sheetId = 'pay-obligation-'.$obligation->id;
                            $payOld = FormState::oldFor($sheetId);
                            $defaultMethod = PaymentFinancialAccountResolver::methodForAccount($obligation->defaultFinancialAccount) ?? 'cash';
                        @endphp
                        @formscope($sheetId)
                        <x-notify.sheet :id="$sheetId" :title="__('notify.expenses.mark_paid')" :subtitle="$obligation->template?->name ?? $obligation->category_name_snapshot"
                            :action="route('recurring-expense-obligations.pay', $obligation)" :form-attributes="['data-pay-obligation' => true]">
                            <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <x-notify.form-field :label="__('notify.expenses.amount')" :for="$sheetId.'-amount'" name="amount" :required="true">
                                <x-notify.money-input :id="$sheetId.'-amount'" :required="true" :value="$payOld('amount', $money((int) $obligation->expected_amount_minor))" />
                            </x-notify.form-field>
                            <x-notify.form-field :label="__('notify.expenses.method')" name="payment_method" :required="true" :group="true">
                                <div class="notify-choices notify-choices--segmented">
                                    @foreach($methods as $method => $label)
                                        <label class="notify-choice-chip">
                                            <input type="radio" name="payment_method" value="{{ $method }}" required @checked($payOld('payment_method', $defaultMethod) === $method)>
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </x-notify.form-field>
                            <x-notify.form-field :label="__('notify.expenses.paid_on')" :for="$sheetId.'-date'" name="paid_on" :required="true">
                                <input id="{{ $sheetId }}-date" class="notify-input" type="date" name="paid_on" required max="{{ today()->toDateString() }}" value="{{ $payOld('paid_on', today()->toDateString()) }}" @invalid('paid_on', $sheetId.'-date')>
                            </x-notify.form-field>
                            <x-slot:footer>
                                <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.ui.cancel') }}</button>
                                <button type="submit" class="notify-button notify-button--primary">{{ __('notify.expenses.confirm_paid') }}</button>
                            </x-slot:footer>
                        </x-notify.sheet>
                        @endformscope
                    @endforeach
                @endif
            </section>
        @endif

        @if($canRecurring)
            <section class="notify-fin-card" aria-labelledby="recurring-form-title">
                <div class="notify-fin-card__head">
                    <h2 id="recurring-form-title" class="notify-fin-card__title">{{ __('notify.expenses.recurring_title') }}</h2>
                </div>
                <p class="notify-fin-hint">{{ __('notify.expenses.recurring_hint') }}</p>
                @if($activeCategories->isEmpty())
                    <x-notify.empty-state :compact="true" icon="settings" :title="__('notify.expenses.no_categories')"
                        :action-href="route('finance.expenses', ['tab' => 'categories'])" :action-label="__('notify.expenses.tabs.categories')" action-icon="settings" action-variant="secondary" />
                @else
                    @php $old = FormState::oldFor('expense-monthly'); @endphp
                    @formscope('expense-monthly')
                    <form method="POST" action="{{ route('recurring-expense-templates.store') }}" class="notify-form-row" data-expense-form="monthly">
                        @csrf
                        <input type="hidden" name="_form" value="expense-monthly">
                        <x-notify.form-field :label="__('notify.expenses.amount')" for="recurring-amount" name="amount" :required="true">
                            <x-notify.money-input id="recurring-amount" :required="true" :value="$old('amount')" />
                        </x-notify.form-field>
                        <x-notify.form-field :label="__('notify.expenses.method')" name="payment_method" :required="true" :group="true">
                            <div class="notify-choices notify-choices--segmented">
                                @foreach($methods as $method => $label)
                                    <label class="notify-choice-chip">
                                        <input type="radio" name="payment_method" value="{{ $method }}" required @checked($old('payment_method', 'cash') === $method)>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </x-notify.form-field>
                        <x-notify.form-field :label="__('notify.expenses.category')" for="recurring-category" name="category_id" :required="true">
                            <select id="recurring-category" class="notify-input" name="category_id" required @invalid('category_id', 'recurring-category')>
                                @foreach($activeCategories as $category)
                                    <option value="{{ $category->id }}" @selected((string) $old('category_id') === (string) $category->id)>{{ $categoryName($category) }}</option>
                                @endforeach
                            </select>
                        </x-notify.form-field>
                        <x-notify.form-field :label="__('notify.expenses.start_date')" for="recurring-start" name="start_date" :required="true">
                            <input id="recurring-start" class="notify-input" type="date" name="start_date" required value="{{ $old('start_date', today()->toDateString()) }}" @invalid('start_date', 'recurring-start')>
                        </x-notify.form-field>
                        <x-notify.form-field :label="__('notify.expenses.due_day')" for="recurring-day" name="due_day" :required="true" :hint="__('notify.expenses.due_day_hint')">
                            <input id="recurring-day" class="notify-input" type="number" name="due_day" inputmode="numeric" dir="ltr" required min="1" max="28" step="1" value="{{ $old('due_day', min(28, today()->day)) }}" @invalid('due_day', 'recurring-day', 'default', true)>
                        </x-notify.form-field>
                        <x-notify.form-field :label="__('notify.expenses.description')" for="recurring-description" name="description" :optional="true">
                            <input id="recurring-description" class="notify-input" name="description" maxlength="255" value="{{ $old('description') }}" placeholder="{{ __('notify.expenses.recurring_description_placeholder') }}" @invalid('description', 'recurring-description')>
                        </x-notify.form-field>
                        <div class="notify-form-row__full notify-form-actions">
                            <button type="submit" class="notify-button notify-button--primary">{{ __('notify.expenses.recurring_save') }}</button>
                        </div>
                    </form>
                    @endformscope
                @endif
            </section>
        @endif

        <section class="notify-fin-card" aria-labelledby="templates-title">
            <div class="notify-fin-card__head">
                <h2 id="templates-title" class="notify-fin-card__title">{{ __('notify.expenses.templates_title') }}</h2>
            </div>
            <x-notify.list data-recurring-list :label="__('notify.expenses.templates_title')" :empty="$templates->isEmpty()"
                :columns="[__('notify.expenses.col_description'), ['label' => __('notify.expenses.col_amount'), 'class' => 'is-num'], __('notify.expenses.next_due'), __('notify.expenses.col_method'), __('notify.expenses.col_status'), $actionsColumn]">
                @foreach($templates as $template)
                    <tr @class(['is-muted' => ! $template->is_active]) data-recurring-template>
                        <td class="notify-list__primary">
                            <span class="notify-list__title">{{ $template->name }}</span>
                            <span class="notify-list__sub">{{ $template->category ? $categoryName($template->category) : '' }} · {{ __('notify.expenses.day_of_month', ['day' => $template->next_due_date?->day ?? $template->start_date?->day]) }}</span>
                        </td>
                        <td class="notify-list__end notify-list__amount"><x-notify.money :minor="(int) $template->amount_minor" /></td>
                        <td data-label="{{ __('notify.expenses.next_due') }}"><span dir="ltr">{{ $template->is_active ? $template->next_due_date?->format('Y-m-d') : '—' }}</span></td>
                        <td data-label="{{ __('notify.expenses.col_method') }}">{{ $methodLabel($template->defaultFinancialAccount) }}</td>
                        <td>
                            <span class="notify-status {{ $template->is_active ? 'notify-status--success' : 'notify-status--neutral' }}">{{ $template->is_active ? __('notify.expenses.status_repeating') : __('notify.expenses.status_stopped') }}</span>
                        </td>
                        <td class="notify-list__actions">
                            @if($canRecurring && $template->is_active)
                                <x-notify.menu>
                                    <button type="button" class="notify-menu__item" role="menuitem" data-open-sheet="template-amount-{{ $template->id }}" aria-haspopup="dialog">
                                        <x-notify.icon name="wallet" :size="18" /><span>{{ __('notify.expenses.change_amount') }}</span>
                                    </button>
                                    <x-slot:danger>
                                        <form method="POST" action="{{ route('recurring-expense-templates.update', $template) }}" data-stop-recurring
                                              data-confirm="{{ __('notify.expenses.stop_hint') }}" data-confirm-title="{{ __('notify.expenses.stop') }} · {{ $template->name }}" data-confirm-label="{{ __('notify.expenses.stop') }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="is_active" value="0">
                                            <button type="submit" class="notify-menu__item notify-menu__item--danger" role="menuitem"><x-notify.icon name="x" :size="18" /><span>{{ __('notify.expenses.stop') }}</span></button>
                                        </form>
                                    </x-slot:danger>
                                </x-notify.menu>
                            @endif
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState>
                    <x-notify.empty-state :compact="true" icon="calendar" :title="__('notify.expenses.templates_empty')" />
                </x-slot:emptyState>
            </x-notify.list>

            @if($canRecurring)
                @foreach($templates as $template)
                    @continue(! $template->is_active)
                    @php $sheetId = 'template-amount-'.$template->id; @endphp
                    @formscope($sheetId)
                    <x-notify.sheet :id="$sheetId" size="sm" :title="__('notify.expenses.change_amount')" :subtitle="$template->name"
                        :action="route('recurring-expense-templates.update', $template)" method="PATCH">
                        <x-notify.form-field :label="__('notify.expenses.amount')" :for="$sheetId.'-amount'" name="amount" :required="true">
                            <x-notify.money-input :id="$sheetId.'-amount'" :required="true" :value="FormState::oldFor($sheetId)('amount', $template->amountJod())" />
                        </x-notify.form-field>
                        <x-slot:footer>
                            <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.ui.cancel') }}</button>
                            <button type="submit" class="notify-button notify-button--primary">{{ __('notify.expenses.update_amount') }}</button>
                        </x-slot:footer>
                    </x-notify.sheet>
                    @endformscope
                @endforeach
            @endif
        </section>

    @else
        {{-- Categories (§12.3 sub-page): the existing expense-category authority. --}}
        @if($canCategories)
            <section class="notify-fin-card" aria-labelledby="category-form-title">
                <div class="notify-fin-card__head">
                    <h2 id="category-form-title" class="notify-fin-card__title">{{ __('notify.expenses.add_category') }}</h2>
                </div>
                @php $old = FormState::oldFor('expense-category'); @endphp
                @formscope('expense-category')
                <form method="POST" action="{{ route('expense-categories.store') }}" class="notify-form-row" data-category-form>
                    @csrf
                    <input type="hidden" name="_form" value="expense-category">
                    <x-notify.form-field :label="__('notify.expenses.category_name_ar')" for="category-name-ar" name="name_ar" :required="true">
                        <input id="category-name-ar" class="notify-input" name="name_ar" required maxlength="255" dir="rtl" value="{{ $old('name_ar') }}" @invalid('name_ar', 'category-name-ar')>
                    </x-notify.form-field>
                    <x-notify.form-field :label="__('notify.expenses.category_name_en')" for="category-name-en" name="name_en" :optional="true">
                        <input id="category-name-en" class="notify-input" name="name_en" maxlength="255" dir="ltr" value="{{ $old('name_en') }}" @invalid('name_en', 'category-name-en')>
                    </x-notify.form-field>
                    <div class="notify-form-row__full notify-form-actions">
                        <button type="submit" class="notify-button notify-button--primary">{{ __('notify.expenses.add_category') }}</button>
                    </div>
                </form>
                @endformscope
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
                            <form method="POST" action="{{ route('expense-categories.archive', $category) }}"
                                  data-confirm="{{ __('notify.expenses.archive') }}: {{ $categoryName($category) }}" data-confirm-label="{{ __('notify.expenses.archive') }}">
                                @csrf
                                <button type="submit" class="notify-button notify-button--ghost notify-button--sm">{{ __('notify.expenses.archive') }}</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <x-notify.empty-state :compact="true" icon="settings" :title="__('notify.expenses.categories_empty')" />
                @endforelse
            </div>
        </section>
    @endif
</div>
@endsection
