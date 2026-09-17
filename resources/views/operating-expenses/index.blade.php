@extends('layouts.app')

@php
    $money = fn (?int $minor) => \App\Support\Money::fromMinorUnits((int) ($minor ?? 0))->format();
@endphp

@section('content')
<div class="p4-wrap">
    {{-- Header --}}
    <header class="p4-header">
        <div class="p4-header-main">
            <div class="p4-eyebrow">{{ __('notify.navigation.finance') }} / {{ __('notify.expenses.title') }}</div>
            <h1 class="p4-title">{{ __('notify.expenses.title') }}</h1>
            <p class="p4-subtitle">{{ __('notify.expenses.subtitle') }}</p>
            <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
                <a href="{{ route('finance.index') }}" class="p4-btn p4-btn-soft p4-btn-sm">تقارير الإدارة المالية</a>
                <a href="{{ route('financial-accounts.index') }}" class="p4-btn p4-btn-soft p4-btn-sm">الحسابات المالية والنقد</a>
                <a href="{{ route('capital-management.index') }}" class="p4-btn p4-btn-soft p4-btn-sm">التمويل والأصول</a>
            </div>
        </div>
        <div class="p4-header-actions">
            <form method="POST" action="{{ route('recurring-expense-obligations.generate') }}" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
                @csrf
                <div class="p4-field">
                    <label>{{ __('notify.expenses.business_date') }}</label>
                    <input type="date" name="business_date" class="p4-input" value="{{ now()->toDateString() }}">
                </div>
                <button class="p4-btn p4-btn-soft" type="submit">{{ __('notify.expenses.generate_obligations') }}</button>
            </form>
        </div>
    </header>

    {{-- KPIs --}}
    <div class="p4-kpis">
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">{{ __('notify.expenses.today_expenses') }}</span>
            <span class="p4-kpi-value is-primary">{{ $money($totals['today_minor']) }} د.أ</span>
            <span class="p4-kpi-meta">{{ __('notify.expenses.today_meta') }}</span>
        </div>
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">{{ __('notify.expenses.month_expenses') }}</span>
            <span class="p4-kpi-value">{{ $money($totals['month_minor']) }} د.أ</span>
            <span class="p4-kpi-meta">{{ __('notify.expenses.month_meta') }}</span>
        </div>
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">{{ __('notify.expenses.company_account') }}</span>
            <span class="p4-kpi-value is-success">{{ $money($totals['company_minor']) }} د.أ</span>
            <span class="p4-kpi-meta">{{ __('notify.expenses.company_meta') }}</span>
        </div>
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">{{ __('notify.expenses.personal_funding') }}</span>
            <span class="p4-kpi-value is-warning">{{ $money($totals['personal_minor']) }} د.أ</span>
            <span class="p4-kpi-meta">مدفوع من الشركاء أو الموظفين</span>
        </div>
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">التزامات قادمة</span>
            <span class="p4-kpi-value">{{ $upcomingObligations->count() }}</span>
            <span class="p4-kpi-meta">مستحقة قريباً</span>
        </div>
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">التزامات متأخرة</span>
            <span class="p4-kpi-value is-danger">{{ $overdueObligations->count() }}</span>
            <span class="p4-kpi-meta">تجاوزت تاريخ الاستحقاق</span>
        </div>
    </div>

    {{-- 1. Add Expense Form --}}
    <x-notify.collapsible-section id="sec-add-expense" :title="__('notify.expenses.add')" subtitle="قيد تشغيلي فوري مرتبط بالحساب المالي أو بالدافع الشخصي" :open="true">
        <form method="POST" action="{{ route('operating-expenses.store') }}">
            @csrf
            <div class="p4-form-grid">
                <div class="p4-field">
                    <label>القيمة (د.أ) *</label>
                    <input name="amount" required placeholder="0.001" class="p4-input">
                </div>
                <div class="p4-field">
                    <label>التصنيف *</label>
                    <select name="category_id" required class="p4-select">
                        @foreach($activeCategories as $category)
                            <option value="{{ $category->id }}">{{ $category->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="p4-field">
                    <label>المورد (اختياري)</label>
                    <select name="vendor_id" class="p4-select">
                        <option value="">بدون مورد محفوظ</option>
                        @foreach($activeVendors as $vendor)
                            <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="p4-field">
                    <label>اسم المستفيد</label>
                    <input name="payee_name" placeholder="اسم حر عند عدم اختيار مورد" class="p4-input">
                </div>
                <div class="p4-field">
                    <label>مصدر التمويل *</label>
                    <select name="funding_source" required class="p4-select">
                        <option value="{{ \App\Models\Expense::FUNDING_COMPANY_ACCOUNT }}">حساب الشركة</option>
                        <option value="{{ \App\Models\Expense::FUNDING_PERSONAL }}">دفع شخصي</option>
                    </select>
                </div>
                <div class="p4-field">
                    <label>الحساب المالي (للشركة)</label>
                    <select name="financial_account_id" class="p4-select">
                        <option value="">اختر عند الدفع من الشركة</option>
                        @foreach($activeFinancialAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="p4-field">
                    <label>الدافع الشخصي</label>
                    <select name="paid_by_user_id" class="p4-select">
                        <option value="">اختر عند الدفع الشخصي</option>
                        @foreach($internalUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="p4-field">
                    <label>تاريخ الاستحقاق *</label>
                    <input type="date" name="incurred_on" value="{{ now()->toDateString() }}" required class="p4-input">
                </div>
                <div class="p4-field">
                    <label>وقت الدفع *</label>
                    <input type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\TH:i') }}" required class="p4-input">
                </div>
                <div class="p4-field">
                    <label>المرجع</label>
                    <input name="reference" class="p4-input" placeholder="رقم فاتورة أو إيصال">
                </div>
                <div class="p4-field" style="grid-column: 1 / -1">
                    <label>الوصف</label>
                    <input name="description" class="p4-input" placeholder="وصف المصروف">
                </div>
                <div class="p4-field" style="grid-column: 1 / -1">
                    <label>ملاحظات إضافية</label>
                    <textarea name="notes" rows="2" class="p4-textarea"></textarea>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:14px">
                <button class="p4-btn p4-btn-primary" type="submit">حفظ المصروف</button>
            </div>
        </form>
    </x-notify.collapsible-section>

    {{-- 2. Recent Expenses & Pending Obligations --}}
    <x-notify.collapsible-section id="sec-recent-pending" title="المصاريف المسجلة والالتزامات المعلقة" subtitle="سجل الحركات الأخيرة والالتزامات المستحقة" :badge="$recentExpenses->count() + $pendingObligations->count()" :open="true">
        <div class="p4-grid-2">
            {{-- Recent Expenses --}}
            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">{{ __('notify.expenses.recent_expenses') }}</h2>
                    <span class="p4-kpi-meta">{{ $recentExpenses->count() }} مسجل</span>
                </div>
                <div class="p4-list">
                    @forelse($recentExpenses as $expense)
                        <div class="p4-list-item">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap">
                                <div>
                                    <strong style="font-size:15px;color:#0A1128">{{ $money($expense->amount_minor) }} د.أ</strong>
                                    <span style="font-size:13px;color:#475569;display:block;margin-top:2px">{{ $expense->category_name_snapshot }} · {{ $expense->payee_name_snapshot ?: 'بدون مستفيد' }}</span>
                                    <span class="p4-kpi-meta">{{ $expense->paid_at?->format('Y-m-d H:i') }} · {{ $expense->funding_source === \App\Models\Expense::FUNDING_PERSONAL ? 'دفع شخصي' : ($expense->financialAccount?->name_ar ?? 'حساب شركة') }}</span>
                                </div>
                                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                                    @if($expense->recurringObligation)
                                        <span class="p4-badge p4-badge-primary">متكرر</span>
                                    @endif
                                    @if($expense->reversal)
                                        <span class="p4-badge p4-badge-danger">معكوس</span>
                                    @else
                                        <form method="POST" action="{{ route('operating-expenses.reverse', $expense) }}" style="display:flex;gap:6px">
                                            @csrf
                                            <input name="reason" placeholder="سبب العكس" required class="p4-input" style="max-width:130px;padding:4px 8px;font-size:12px">
                                            <button class="p4-btn p4-btn-danger p4-btn-sm" type="submit">عكس</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p4-kpi-meta" style="padding:14px;text-align:center">لا توجد مصاريف مسجلة بعد.</div>
                    @endforelse
                </div>
            </div>

            {{-- Pending Obligations --}}
            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">{{ __('notify.expenses.pending_obligations') }}</h2>
                    <span class="p4-kpi-meta">{{ $pendingObligations->count() }} التزام</span>
                </div>
                <div class="p4-list">
                    @forelse($pendingObligations as $obligation)
                        <div class="p4-list-item">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
                                <div>
                                    <strong style="font-size:14px;color:#0A1128">{{ $obligation->due_date->toDateString() }} · {{ $money($obligation->expected_amount_minor) }} د.أ</strong>
                                    <span style="font-size:12px;color:#475569;display:block">{{ $obligation->category_name_snapshot }} · {{ $obligation->payee_name_snapshot ?: $obligation->template?->name }}</span>
                                </div>
                            </div>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:8px">
                                <form method="POST" action="{{ route('recurring-expense-obligations.pay', $obligation) }}" style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">
                                    @csrf
                                    <input name="amount" value="{{ $money($obligation->expected_amount_minor) }}" class="p4-input" style="max-width:80px;padding:4px 6px;font-size:12px">
                                    <select name="funding_source" class="p4-select" style="font-size:12px;padding:4px 6px">
                                        <option value="{{ \App\Models\Expense::FUNDING_COMPANY_ACCOUNT }}" @selected($obligation->default_funding_source === \App\Models\Expense::FUNDING_COMPANY_ACCOUNT)>شركة</option>
                                        <option value="{{ \App\Models\Expense::FUNDING_PERSONAL }}" @selected($obligation->default_funding_source === \App\Models\Expense::FUNDING_PERSONAL)>شخصي</option>
                                    </select>
                                    <select name="financial_account_id" class="p4-select" style="font-size:12px;padding:4px 6px">
                                        <option value="">حساب</option>
                                        @foreach($activeFinancialAccounts as $account)
                                            <option value="{{ $account->id }}" @selected($obligation->default_financial_account_id === $account->id)>{{ $account->name_ar }}</option>
                                        @endforeach
                                    </select>
                                    <select name="paid_by_user_id" class="p4-select" style="font-size:12px;padding:4px 6px">
                                        <option value="">دافع</option>
                                        @foreach($internalUsers as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                    <button class="p4-btn p4-btn-primary p4-btn-sm" type="submit">دفع</button>
                                </form>
                                <form method="POST" action="{{ route('recurring-expense-obligations.skip', $obligation) }}">
                                    @csrf
                                    <button class="p4-btn p4-btn-ghost p4-btn-sm" type="submit">تخطي</button>
                                </form>
                                <form method="POST" action="{{ route('recurring-expense-obligations.cancel', $obligation) }}">
                                    @csrf
                                    <button class="p4-btn p4-btn-danger p4-btn-sm" type="submit">إلغاء</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="p4-kpi-meta" style="padding:14px;text-align:center">لا توجد التزامات معلقة.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </x-notify.collapsible-section>

    {{-- 3. Recurring Expense Templates --}}
    <x-notify.collapsible-section id="sec-templates" :title="__('notify.expenses.recurring_templates')" subtitle="إعداد قوالب المصاريف الدورية وجدولتها" :badge="$templates->count()" :open="false">
        <form method="POST" action="{{ route('recurring-expense-templates.store') }}" style="margin-bottom:18px">
            @csrf
            <div class="p4-form-grid">
                <div class="p4-field">
                    <label>اسم القالب *</label>
                    <input name="name" required class="p4-input" placeholder="اشتراك سيرفر">
                </div>
                <div class="p4-field">
                    <label>القيمة (د.أ) *</label>
                    <input name="amount" required placeholder="25.000" class="p4-input">
                </div>
                <div class="p4-field">
                    <label>التصنيف *</label>
                    <select name="category_id" required class="p4-select">
                        @foreach($activeCategories as $category)
                            <option value="{{ $category->id }}">{{ $category->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="p4-field">
                    <label>المورد</label>
                    <select name="vendor_id" class="p4-select">
                        <option value="">بدون مورد</option>
                        @foreach($activeVendors as $vendor)
                            <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="p4-field">
                    <label>اسم المستفيد</label>
                    <input name="payee_name" class="p4-input">
                </div>
                <div class="p4-field">
                    <label>دورية التكرار *</label>
                    <select name="frequency" required class="p4-select">
                        <option value="weekly">أسبوعي</option>
                        <option value="monthly" selected>شهري</option>
                        <option value="quarterly">ربع سنوي</option>
                        <option value="annual">سنوي</option>
                    </select>
                </div>
                <div class="p4-field">
                    <label>كل (فاصل زمني)</label>
                    <input type="number" name="interval_count" value="1" min="1" class="p4-input">
                </div>
                <div class="p4-field">
                    <label>تاريخ البدء *</label>
                    <input type="date" name="start_date" value="{{ now()->toDateString() }}" required class="p4-input">
                </div>
                <div class="p4-field">
                    <label>نهاية اختيارية</label>
                    <input type="date" name="end_date" class="p4-input">
                </div>
                <div class="p4-field">
                    <label>مصدر التمويل الافتراضي *</label>
                    <select name="default_funding_source" required class="p4-select">
                        <option value="{{ \App\Models\Expense::FUNDING_COMPANY_ACCOUNT }}">حساب الشركة</option>
                        <option value="{{ \App\Models\Expense::FUNDING_PERSONAL }}">دفع شخصي</option>
                    </select>
                </div>
                <div class="p4-field">
                    <label>حساب افتراضي</label>
                    <select name="default_financial_account_id" class="p4-select">
                        <option value="">بدون</option>
                        @foreach($activeFinancialAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="p4-field">
                    <label>دافع شخصي افتراضي</label>
                    <select name="default_paid_by_user_id" class="p4-select">
                        <option value="">بدون</option>
                        @foreach($internalUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:12px">
                <button class="p4-btn p4-btn-primary" type="submit">حفظ القالب</button>
            </div>
        </form>

        <div class="p4-grid-3">
            @foreach($templates as $template)
                <div class="p4-list-item">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <strong style="font-size:14px;color:#0A1128">{{ $template->name }}</strong>
                        <span class="p4-badge {{ $template->is_active ? 'p4-badge-success' : 'p4-badge-neutral' }}">{{ $template->is_active ? 'نشط' : 'متوقف' }}</span>
                    </div>
                    <span style="font-size:13px;color:#0055CC;font-weight:700">{{ $money($template->amount_minor) }} د.أ · {{ $template->category?->displayName() }}</span>
                    <span class="p4-kpi-meta">الاستحقاق القادم: {{ $template->next_due_date?->toDateString() ?? '—' }}</span>
                </div>
            @endforeach
        </div>
    </x-notify.collapsible-section>

    {{-- 4. Vendors & Categories --}}
    <x-notify.collapsible-section id="sec-vendors-categories" title="الموردون والتصنيفات" subtitle="إدارة سجل الموردين وتصنيفات المصاريف التشغيلية" :badge="$vendors->count() + $categories->count()" :open="false">
        <div class="p4-grid-2">
            {{-- Vendors --}}
            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">{{ __('notify.expenses.vendors') }}</h2>
                    <span class="p4-kpi-meta">{{ $vendors->count() }} مورد</span>
                </div>
                <form method="POST" action="{{ route('vendors.store') }}" style="margin-bottom:14px">
                    @csrf
                    <div class="p4-form-grid">
                        <div class="p4-field">
                            <label>اسم المورد *</label>
                            <input name="name" required class="p4-input">
                        </div>
                        <div class="p4-field">
                            <label>الهاتف</label>
                            <input name="phone" class="p4-input">
                        </div>
                        <div class="p4-field">
                            <label>البريد الإلكتروني</label>
                            <input name="email" class="p4-input">
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;margin-top:10px">
                        <button class="p4-btn p4-btn-primary p4-btn-sm" type="submit">إضافة مورد</button>
                    </div>
                </form>
                <div class="p4-list">
                    @foreach($vendors as $vendor)
                        <div class="p4-list-item" style="display:flex;justify-content:space-between;align-items:center">
                            <div>
                                <strong style="font-size:13px;color:#0A1128">{{ $vendor->name }}</strong>
                                <span class="p4-kpi-meta">{{ $vendor->is_active ? 'نشط' : 'مؤرشف' }} @if($vendor->phone) · {{ $vendor->phone }} @endif</span>
                            </div>
                            @if($vendor->archived_at === null)
                                <form method="POST" action="{{ route('vendors.archive', $vendor) }}">
                                    @csrf
                                    <button class="p4-btn p4-btn-ghost p4-btn-sm" type="submit">أرشفة</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Categories --}}
            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">{{ __('notify.expenses.categories') }}</h2>
                    <span class="p4-kpi-meta">{{ $categories->count() }} تصنيف</span>
                </div>
                <form method="POST" action="{{ route('expense-categories.store') }}" style="margin-bottom:14px">
                    @csrf
                    <div class="p4-form-grid">
                        <div class="p4-field">
                            <label>الكود *</label>
                            <input name="key" required class="p4-input" placeholder="hosting">
                        </div>
                        <div class="p4-field">
                            <label>الاسم بالعربية *</label>
                            <input name="name_ar" required class="p4-input" placeholder="استضافة وسيرفرات">
                        </div>
                        <div class="p4-field">
                            <label>الاسم بالإنجليزية</label>
                            <input name="name_en" class="p4-input" placeholder="Hosting & Servers">
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;margin-top:10px">
                        <button class="p4-btn p4-btn-primary p4-btn-sm" type="submit">إضافة تصنيف</button>
                    </div>
                </form>
                <div class="p4-list">
                    @foreach($categories as $category)
                        <div class="p4-list-item" style="display:flex;justify-content:space-between;align-items:center">
                            <div>
                                <strong style="font-size:13px;color:#0A1128">{{ $category->displayName() }}</strong>
                                <span class="p4-kpi-meta">{{ $category->key }} · {{ $category->is_active ? 'نشط' : 'مؤرشف' }}</span>
                            </div>
                            @if($category->archived_at === null)
                                <form method="POST" action="{{ route('expense-categories.archive', $category) }}">
                                    @csrf
                                    <button class="p4-btn p4-btn-ghost p4-btn-sm" type="submit">أرشفة</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </x-notify.collapsible-section>
</div>
@endsection
