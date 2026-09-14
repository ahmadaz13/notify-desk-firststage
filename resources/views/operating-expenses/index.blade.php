@extends('layouts.app')

@section('content')
@php
    $money = fn (?int $minor) => \App\Support\Money::fromMinorUnits((int) ($minor ?? 0))->format();
@endphp

<div class="page-head">
    <div>
        <p class="eyebrow">Finance D2A</p>
        <h1>المصاريف التشغيلية</h1>
    </div>
    <form method="POST" action="{{ route('recurring-expense-obligations.generate') }}" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
        @csrf
        <label>تاريخ التشغيل
            <input type="date" name="business_date" value="{{ now()->toDateString() }}">
        </label>
        <button class="btn" type="submit">توليد الالتزامات</button>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card"><span>مصروفات اليوم</span><strong>{{ $money($totals['today_minor']) }} JOD</strong></div>
    <div class="stat-card"><span>مصروفات الشهر</span><strong>{{ $money($totals['month_minor']) }} JOD</strong></div>
    <div class="stat-card"><span>مدفوع من حساب الشركة</span><strong>{{ $money($totals['company_minor']) }} JOD</strong></div>
    <div class="stat-card"><span>مدفوع شخصياً</span><strong>{{ $money($totals['personal_minor']) }} JOD</strong></div>
    <div class="stat-card"><span>التزامات قادمة</span><strong>{{ $upcomingObligations->count() }}</strong></div>
    <div class="stat-card"><span>التزامات متأخرة</span><strong>{{ $overdueObligations->count() }}</strong></div>
</div>

<section class="panel">
    <h2>تسجيل مصروف V2</h2>
    <form method="POST" action="{{ route('operating-expenses.store') }}" class="form-grid">
        @csrf
        <label>القيمة JOD
            <input name="amount" required placeholder="0.001">
        </label>
        <label>التصنيف
            <select name="category_id" required>
                @foreach($activeCategories as $category)
                    <option value="{{ $category->id }}">{{ $category->displayName() }}</option>
                @endforeach
            </select>
        </label>
        <label>المورد
            <select name="vendor_id">
                <option value="">بدون مورد محفوظ</option>
                @foreach($activeVendors as $vendor)
                    <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                @endforeach
            </select>
        </label>
        <label>اسم المستفيد
            <input name="payee_name" placeholder="اسم حر عند عدم اختيار مورد">
        </label>
        <label>مصدر التمويل
            <select name="funding_source" required>
                <option value="{{ \App\Models\Expense::FUNDING_COMPANY_ACCOUNT }}">حساب الشركة</option>
                <option value="{{ \App\Models\Expense::FUNDING_PERSONAL }}">دفع شخصي</option>
            </select>
        </label>
        <label>الحساب المالي
            <select name="financial_account_id">
                <option value="">اختر عند الدفع من الشركة</option>
                @foreach($activeFinancialAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                @endforeach
            </select>
        </label>
        <label>الدافع الشخصي
            <select name="paid_by_user_id">
                <option value="">اختر عند الدفع الشخصي</option>
                @foreach($internalUsers as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </label>
        <label>تاريخ الاستحقاق
            <input type="date" name="incurred_on" value="{{ now()->toDateString() }}" required>
        </label>
        <label>وقت الدفع
            <input type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\TH:i') }}" required>
        </label>
        <label>مرجع
            <input name="reference">
        </label>
        <label class="wide">وصف
            <input name="description">
        </label>
        <label class="wide">ملاحظات
            <textarea name="notes" rows="2"></textarea>
        </label>
        <button class="btn" type="submit">حفظ المصروف</button>
    </form>
</section>

<section class="panel">
    <h2>المصاريف الأخيرة</h2>
    <div class="list-stack">
        @forelse($recentExpenses as $expense)
            <article class="list-card">
                <div>
                    <strong>{{ $money($expense->amount_minor) }} JOD</strong>
                    <span>{{ $expense->category_name_snapshot }} · {{ $expense->payee_name_snapshot ?: 'بدون مستفيد' }}</span>
                    <small>{{ $expense->paid_at?->format('Y-m-d H:i') }} · {{ $expense->funding_source === \App\Models\Expense::FUNDING_PERSONAL ? 'دفع شخصي' : ($expense->financialAccount?->name_ar ?? 'حساب شركة') }}</small>
                </div>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    @if($expense->recurringObligation)
                        <span class="badge">متكرر</span>
                    @endif
                    @if($expense->reversal)
                        <span class="badge danger">معكوس</span>
                    @else
                        <form method="POST" action="{{ route('operating-expenses.reverse', $expense) }}" style="display:flex;gap:6px">
                            @csrf
                            <input name="reason" placeholder="سبب العكس" required style="max-width:160px">
                            <button class="btn btn-ghost" type="submit">عكس</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <p class="muted">لا توجد مصاريف V2 بعد.</p>
        @endforelse
    </div>
</section>

<section class="panel">
    <h2>قوالب المصاريف المتكررة</h2>
    <form method="POST" action="{{ route('recurring-expense-templates.store') }}" class="form-grid">
        @csrf
        <label>الاسم <input name="name" required></label>
        <label>القيمة JOD <input name="amount" required placeholder="25.000"></label>
        <label>التصنيف
            <select name="category_id" required>
                @foreach($activeCategories as $category)
                    <option value="{{ $category->id }}">{{ $category->displayName() }}</option>
                @endforeach
            </select>
        </label>
        <label>المورد
            <select name="vendor_id">
                <option value="">بدون</option>
                @foreach($activeVendors as $vendor)
                    <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                @endforeach
            </select>
        </label>
        <label>المستفيد <input name="payee_name"></label>
        <label>التكرار
            <select name="frequency" required>
                <option value="weekly">أسبوعي</option>
                <option value="monthly" selected>شهري</option>
                <option value="quarterly">ربع سنوي</option>
                <option value="annual">سنوي</option>
            </select>
        </label>
        <label>كل
            <input type="number" name="interval_count" value="1" min="1">
        </label>
        <label>البداية <input type="date" name="start_date" value="{{ now()->toDateString() }}" required></label>
        <label>نهاية اختيارية <input type="date" name="end_date"></label>
        <label>مصدر التمويل الافتراضي
            <select name="default_funding_source" required>
                <option value="{{ \App\Models\Expense::FUNDING_COMPANY_ACCOUNT }}">حساب الشركة</option>
                <option value="{{ \App\Models\Expense::FUNDING_PERSONAL }}">دفع شخصي</option>
            </select>
        </label>
        <label>حساب افتراضي
            <select name="default_financial_account_id">
                <option value="">بدون</option>
                @foreach($activeFinancialAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                @endforeach
            </select>
        </label>
        <label>دافع شخصي افتراضي
            <select name="default_paid_by_user_id">
                <option value="">بدون</option>
                @foreach($internalUsers as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn" type="submit">حفظ القالب</button>
    </form>

    <div class="list-stack">
        @foreach($templates as $template)
            <article class="list-card">
                <div>
                    <strong>{{ $template->name }}</strong>
                    <span>{{ $money($template->amount_minor) }} JOD · {{ $template->category?->displayName() }}</span>
                    <small>الاستحقاق القادم: {{ $template->next_due_date?->toDateString() }} · {{ $template->is_active ? 'نشط' : 'متوقف' }}</small>
                </div>
            </article>
        @endforeach
    </div>
</section>

<section class="panel">
    <h2>الالتزامات المعلقة</h2>
    <div class="list-stack">
        @forelse($pendingObligations as $obligation)
            <article class="list-card">
                <div>
                    <strong>{{ $obligation->due_date->toDateString() }} · {{ $money($obligation->expected_amount_minor) }} JOD</strong>
                    <span>{{ $obligation->category_name_snapshot }} · {{ $obligation->payee_name_snapshot ?: $obligation->template?->name }}</span>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <form method="POST" action="{{ route('recurring-expense-obligations.pay', $obligation) }}" style="display:flex;gap:6px;flex-wrap:wrap">
                        @csrf
                        <input name="amount" value="{{ $money($obligation->expected_amount_minor) }}" style="max-width:90px">
                        <select name="funding_source">
                            <option value="{{ \App\Models\Expense::FUNDING_COMPANY_ACCOUNT }}" @selected($obligation->default_funding_source === \App\Models\Expense::FUNDING_COMPANY_ACCOUNT)>شركة</option>
                            <option value="{{ \App\Models\Expense::FUNDING_PERSONAL }}" @selected($obligation->default_funding_source === \App\Models\Expense::FUNDING_PERSONAL)>شخصي</option>
                        </select>
                        <select name="financial_account_id">
                            <option value="">حساب</option>
                            @foreach($activeFinancialAccounts as $account)
                                <option value="{{ $account->id }}" @selected($obligation->default_financial_account_id === $account->id)>{{ $account->name_ar }}</option>
                            @endforeach
                        </select>
                        <select name="paid_by_user_id">
                            <option value="">دافع شخصي</option>
                            @foreach($internalUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <button class="btn" type="submit">دفع</button>
                    </form>
                    <form method="POST" action="{{ route('recurring-expense-obligations.skip', $obligation) }}">@csrf<button class="btn btn-ghost" type="submit">تخطي</button></form>
                    <form method="POST" action="{{ route('recurring-expense-obligations.cancel', $obligation) }}">@csrf<button class="btn btn-ghost" type="submit">إلغاء</button></form>
                </div>
            </article>
        @empty
            <p class="muted">لا توجد التزامات معلقة.</p>
        @endforelse
    </div>
</section>

<div class="two-col">
    <section class="panel">
        <h2>الموردون</h2>
        <form method="POST" action="{{ route('vendors.store') }}" class="form-grid compact">
            @csrf
            <label>الاسم <input name="name" required></label>
            <label>الهاتف <input name="phone"></label>
            <label>البريد <input name="email"></label>
            <button class="btn" type="submit">إضافة مورد</button>
        </form>
        <div class="list-stack">
            @foreach($vendors as $vendor)
                <article class="list-card">
                    <div><strong>{{ $vendor->name }}</strong><small>{{ $vendor->is_active ? 'نشط' : 'مؤرشف' }}</small></div>
                    @if($vendor->archived_at === null)
                        <form method="POST" action="{{ route('vendors.archive', $vendor) }}">@csrf<button class="btn btn-ghost" type="submit">أرشفة</button></form>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <section class="panel">
        <h2>تصنيفات المصاريف</h2>
        <form method="POST" action="{{ route('expense-categories.store') }}" class="form-grid compact">
            @csrf
            <label>الكود <input name="key" required></label>
            <label>الاسم العربي <input name="name_ar" required></label>
            <label>الاسم الإنجليزي <input name="name_en"></label>
            <button class="btn" type="submit">إضافة تصنيف</button>
        </form>
        <div class="list-stack">
            @foreach($categories as $category)
                <article class="list-card">
                    <div><strong>{{ $category->displayName() }}</strong><small>{{ $category->key }} · {{ $category->is_active ? 'نشط' : 'مؤرشف' }}</small></div>
                    @if($category->archived_at === null)
                        <form method="POST" action="{{ route('expense-categories.archive', $category) }}">@csrf<button class="btn btn-ghost" type="submit">أرشفة</button></form>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
</div>

<style>
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin:16px 0}
    .stat-card,.panel,.list-card{background:#fff;border:1px solid var(--nd-border);border-radius:8px;padding:14px}
    .stat-card span,.list-card small,.muted{color:var(--nd-muted);display:block}
    .stat-card strong{font-size:22px}
    .panel{margin:16px 0}
    .panel h2{font-size:20px;margin:0 0 12px}
    .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;align-items:end}
    .form-grid.compact{grid-template-columns:repeat(auto-fit,minmax(150px,1fr))}
    .form-grid label{display:grid;gap:6px;font-weight:700}
    .wide{grid-column:1/-1}
    .list-stack{display:grid;gap:10px;margin-top:12px}
    .list-card{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
    .list-card strong,.list-card span{display:block}
    .badge{display:inline-flex;border-radius:999px;background:#eef2ff;color:#3730a3;padding:4px 8px;font-size:12px;font-weight:800}
    .badge.danger{background:#fff0f1;color:#c84c54}
    .two-col{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
    input,select,textarea{width:100%}
</style>
@endsection
