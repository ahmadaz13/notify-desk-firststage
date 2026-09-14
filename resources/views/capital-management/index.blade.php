@extends('layouts.app')

@section('content')
@php
    $money = fn (?int $minor) => \App\Support\Money::fromMinorUnits((int) ($minor ?? 0))->format();
@endphp

<div class="page-head">
    <div>
        <p class="eyebrow">Finance D2B</p>
        <h1>إدارة التمويل والأصول</h1>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card"><span>تمويل V2 نشط</span><strong>{{ $money($totals['funding_minor']) }} JOD</strong></div>
    <div class="stat-card"><span>أصول ممولة من الشركة</span><strong>{{ $money($totals['company_asset_minor']) }} JOD</strong></div>
    <div class="stat-card"><span>أصول ممولة شخصياً</span><strong>{{ $money($totals['personal_asset_minor']) }} JOD</strong></div>
    <div class="stat-card"><span>عدد الأصول النشطة</span><strong>{{ $totals['active_asset_count'] }}</strong></div>
</div>

<section class="panel">
    <h2>تسجيل تمويل رأسمالي</h2>
    <form method="POST" action="{{ route('capital-funding-transactions.store') }}" class="form-grid">
        @csrf
        <label>مصدر محفوظ
            <select name="funding_source_id">
                <option value="">بدون مصدر محفوظ</option>
                @foreach($activeFundingSources as $source)
                    <option value="{{ $source->id }}">{{ $source->name }}</option>
                @endforeach
            </select>
        </label>
        <label>اسم المصدر
            <input name="source_name" placeholder="مطلوب عند عدم اختيار مصدر محفوظ">
        </label>
        <label>نوع التمويل
            <select name="funding_type" required>
                <option value="founder_contribution">مساهمة مؤسس</option>
                <option value="owner_contribution">مساهمة مالك</option>
                <option value="external_investment">استثمار خارجي</option>
                <option value="loan_funding">تمويل قرض</option>
                <option value="other_funding">تمويل آخر</option>
            </select>
        </label>
        <label>الحساب المالي
            <select name="financial_account_id" required>
                @foreach($activeFinancialAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                @endforeach
            </select>
        </label>
        <label>القيمة JOD <input name="amount" required placeholder="0.001"></label>
        <label>تاريخ الاستلام <input type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\TH:i') }}" required></label>
        <label>مرجع <input name="reference"></label>
        <label class="wide">ملاحظات <textarea name="notes" rows="2"></textarea></label>
        <button class="btn" type="submit">تسجيل التمويل</button>
    </form>
</section>

<section class="panel">
    <h2>اقتناء أصل ثابت</h2>
    <form method="POST" action="{{ route('fixed-assets.store') }}" class="form-grid">
        @csrf
        <label>اسم الأصل <input name="name" required></label>
        <label>التصنيف
            <select name="asset_category_id" required>
                @foreach($activeAssetCategories as $category)
                    <option value="{{ $category->id }}">{{ $category->displayName() }}</option>
                @endforeach
            </select>
        </label>
        <label>القيمة JOD <input name="acquisition_cost" required placeholder="100.000"></label>
        <label>مصدر التمويل
            <select name="funding_source" required>
                <option value="{{ \App\Models\FixedAsset::FUNDING_COMPANY_ACCOUNT }}">حساب الشركة</option>
                <option value="{{ \App\Models\FixedAsset::FUNDING_PERSONAL }}">دفع شخصي</option>
            </select>
        </label>
        <label>الحساب المالي
            <select name="financial_account_id">
                <option value="">اختر عند التمويل من الشركة</option>
                @foreach($activeFinancialAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                @endforeach
            </select>
        </label>
        <label>الدافع الشخصي
            <select name="paid_by_user_id">
                <option value="">اختر عند التمويل الشخصي</option>
                @foreach($internalUsers as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
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
        <label>المستفيد <input name="payee_name"></label>
        <label>الرقم التسلسلي <input name="serial_number"></label>
        <label>الكمية <input type="number" name="quantity" value="1" min="1"></label>
        <label>تاريخ الاقتناء <input type="date" name="acquired_at" value="{{ now()->toDateString() }}" required></label>
        <label>تاريخ التشغيل <input type="date" name="in_service_at"></label>
        <label>الموقع <input name="location"></label>
        <label>العمر المفيد بالشهور <input type="number" name="useful_life_months" min="1"></label>
        <label>قيمة متبقية اختيارية <input name="residual_value"></label>
        <label class="wide">وصف <input name="description"></label>
        <button class="btn" type="submit">حفظ الأصل</button>
    </form>
</section>

<div class="two-col">
    <section class="panel">
        <h2>مصادر التمويل</h2>
        <form method="POST" action="{{ route('funding-sources.store') }}" class="form-grid compact">
            @csrf
            <label>الاسم <input name="name" required></label>
            <label>النوع
                <select name="type" required>
                    <option value="founder">مؤسس</option>
                    <option value="owner">مالك</option>
                    <option value="investor">مستثمر</option>
                    <option value="lender">مقرض</option>
                    <option value="other">آخر</option>
                </select>
            </label>
            <button class="btn" type="submit">إضافة مصدر</button>
        </form>
        <div class="list-stack">
            @foreach($fundingSources as $source)
                <article class="list-card">
                    <div><strong>{{ $source->name }}</strong><small>{{ $source->type }} · {{ $source->is_active ? 'نشط' : 'مؤرشف' }}</small></div>
                    @if($source->archived_at === null)
                        <form method="POST" action="{{ route('funding-sources.archive', $source) }}">@csrf<button class="btn btn-ghost" type="submit">أرشفة</button></form>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <section class="panel">
        <h2>تصنيفات الأصول</h2>
        <form method="POST" action="{{ route('asset-categories.store') }}" class="form-grid compact">
            @csrf
            <label>الكود <input name="code" required></label>
            <label>الاسم العربي <input name="name_ar" required></label>
            <label>الاسم الإنجليزي <input name="name_en"></label>
            <button class="btn" type="submit">إضافة تصنيف</button>
        </form>
        <div class="list-stack">
            @foreach($assetCategories as $category)
                <article class="list-card">
                    <div><strong>{{ $category->displayName() }}</strong><small>{{ $category->code }} · {{ $category->is_active ? 'نشط' : 'مؤرشف' }}</small></div>
                    @if($category->archived_at === null)
                        <form method="POST" action="{{ route('asset-categories.archive', $category) }}">@csrf<button class="btn btn-ghost" type="submit">أرشفة</button></form>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
</div>

<section class="panel">
    <h2>أحداث التمويل الأخيرة</h2>
    <div class="list-stack">
        @foreach($fundingTransactions as $transaction)
            <article class="list-card">
                <div>
                    <strong>{{ $transaction->funding_number }} · {{ $money($transaction->amount_minor) }} JOD</strong>
                    <span>{{ $transaction->source_name_snapshot }} · {{ $transaction->financialAccount?->name_ar }}</span>
                    <small>{{ $transaction->received_at?->format('Y-m-d H:i') }} · {{ $transaction->reversal ? 'معكوس' : 'نشط' }}</small>
                </div>
                @if(!$transaction->reversal)
                    <form method="POST" action="{{ route('capital-funding-transactions.reverse', $transaction) }}" style="display:flex;gap:6px">
                        @csrf
                        <input name="reason" placeholder="سبب العكس" required>
                        <button class="btn btn-ghost" type="submit">عكس</button>
                    </form>
                @endif
            </article>
        @endforeach
    </div>
</section>

<section class="panel">
    <h2>سجل الأصول</h2>
    <div class="list-stack">
        @foreach($fixedAssets as $asset)
            <article class="list-card">
                <div>
                    <strong>{{ $asset->asset_number }} · {{ $asset->name }}</strong>
                    <span>{{ $money($asset->acquisition_cost_minor) }} JOD · {{ $asset->category_name_snapshot }} · {{ $asset->payee_name_snapshot ?: 'بدون مستفيد' }}</span>
                    <small>{{ $asset->acquired_at?->toDateString() }} · {{ $asset->location ?: 'بدون موقع' }} · {{ $asset->funding_source === \App\Models\FixedAsset::FUNDING_PERSONAL ? 'تمويل شخصي' : ($asset->financialAccount?->name_ar ?? 'حساب شركة') }} · {{ $asset->acquisitionReversal ? 'معكوس' : $asset->status }}</small>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    @if(!$asset->acquisitionReversal)
                        <form method="POST" action="{{ route('fixed-assets.status', $asset) }}">
                            @csrf @method('PATCH')
                            <select name="status">
                                <option value="active" @selected($asset->status === 'active')>نشط</option>
                                <option value="out_of_service" @selected($asset->status === 'out_of_service')>خارج الخدمة</option>
                            </select>
                            <button class="btn btn-ghost" type="submit">تحديث</button>
                        </form>
                        <form method="POST" action="{{ route('fixed-assets.reverse', $asset) }}" style="display:flex;gap:6px">
                            @csrf
                            <input name="reason" placeholder="سبب العكس" required>
                            <button class="btn btn-ghost" type="submit">عكس</button>
                        </form>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>

<section class="panel">
    <h2>سجلات قديمة غير مرحلة</h2>
    <div class="two-col">
        <div>
            <h3>استثمارات قديمة</h3>
            <div class="list-stack">
                @foreach($legacyInvestments as $investment)
                    <article class="list-card"><div><strong>{{ $investment->investor_name }}</strong><small>Legacy · {{ $investment->amount }} JOD · {{ $investment->entry_date }}</small></div></article>
                @endforeach
            </div>
        </div>
        <div>
            <h3>مصاريف رأسمالية قديمة</h3>
            <div class="list-stack">
                @foreach($legacyCapitalExpenses as $expense)
                    <article class="list-card"><div><strong>{{ $expense->description }}</strong><small>Legacy · {{ $expense->amount }} JOD · {{ $expense->expense_date }}</small></div></article>
                @endforeach
            </div>
        </div>
    </div>
</section>

<style>
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:16px 0}
    .stat-card,.panel,.list-card{background:#fff;border:1px solid var(--nd-border);border-radius:8px;padding:14px}
    .stat-card span,.list-card small{color:var(--nd-muted);display:block}
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
    .two-col{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
    input,select,textarea{width:100%}
</style>
@endsection
