@extends('layouts.app')

@php
    $money = fn (?int $minor) => \App\Support\Money::fromMinorUnits((int) ($minor ?? 0))->format();
@endphp

@section('content')
<div class="p4-wrap">
    {{-- Header --}}
    <header class="p4-header">
        <div class="p4-header-main">
            <div class="p4-eyebrow">{{ __('notify.navigation.finance') }} / {{ __('notify.assets.title') }}</div>
            <h1 class="p4-title">إدارة التمويل والأصول</h1>
            <p class="p4-subtitle">إدارة مساهمات المؤسسين والتمويل الرأسمالي، اقتناء الأصول الثابتة، وتصنيفات الأصول المسجلة.</p>
            <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
                <a href="{{ route('finance.index') }}" class="p4-btn p4-btn-soft p4-btn-sm">تقارير الإدارة المالية</a>
                <a href="{{ route('operating-expenses.index') }}" class="p4-btn p4-btn-soft p4-btn-sm">المصاريف التشغيلية</a>
            </div>
        </div>
    </header>

    {{-- KPIs --}}
    <div class="p4-kpis">
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">تمويل رأسمالي نشط</span>
            <span class="p4-kpi-value is-primary">{{ $money($totals['funding_minor']) }} د.أ</span>
            <span class="p4-kpi-meta">تمويل V2 نشط بدون عكس</span>
        </div>
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">أصول ممولة من الشركة</span>
            <span class="p4-kpi-value is-success">{{ $money($totals['company_asset_minor']) }} د.أ</span>
            <span class="p4-kpi-meta">تكلفة اقتناء تاريخية مسجلة</span>
        </div>
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">أصول ممولة شخصياً</span>
            <span class="p4-kpi-value is-warning">{{ $money($totals['personal_asset_minor']) }} د.أ</span>
            <span class="p4-kpi-meta">تمويل عبر الشركاء أو الملاك</span>
        </div>
        <div class="p4-kpi-card">
            <span class="p4-kpi-label">عدد الأصول النشطة</span>
            <span class="p4-kpi-value">{{ $totals['active_asset_count'] }}</span>
            <span class="p4-kpi-meta">أصل ثابت في الخدمة</span>
        </div>
    </div>

    {{-- 1. Forms: Capital Funding & Fixed Asset Acquisition --}}
    <x-notify.collapsible-section id="sec-capital-forms" title="تسجيل التمويل واقتناء الأصول" subtitle="نماذج تسجيل التمويل الرأسمالي الجديد واقتناء الأصول الثابتة" :open="true">
        <div class="p4-grid-2">
            {{-- Capital Funding Transaction Form --}}
            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">تسجيل تمويل رأسمالي</h2>
                    <span class="p4-kpi-meta">مساهمة شريك أو استثمار مباشر</span>
                </div>
                <form method="POST" action="{{ route('capital-funding-transactions.store') }}">
                    @csrf
                    <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <div class="p4-form-grid">
                        <div class="p4-field">
                            <label>مصدر محفوظ</label>
                            <select name="funding_source_id" class="p4-select">
                                <option value="">بدون مصدر محفوظ</option>
                                @foreach($activeFundingSources as $source)
                                    <option value="{{ $source->id }}">{{ $source->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="p4-field">
                            <label>اسم المصدر</label>
                            <input name="source_name" placeholder="مطلوب عند عدم اختيار مصدر محفوظ" class="p4-input">
                        </div>
                        <div class="p4-field">
                            <label>نوع التمويل *</label>
                            <select name="funding_type" required class="p4-select">
                                <option value="founder_contribution">مساهمة مؤسس</option>
                                <option value="owner_contribution">مساهمة مالك</option>
                                <option value="external_investment">استثمار خارجي</option>
                                <option value="loan_funding">تمويل قرض</option>
                                <option value="other_funding">تمويل آخر</option>
                            </select>
                        </div>
                        <div class="p4-field">
                            <label>الحساب المالي المستلم *</label>
                            <select name="financial_account_id" required class="p4-select">
                                @foreach($activeFinancialAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="p4-field">
                            <label>القيمة (د.أ) *</label>
                            <input name="amount" required placeholder="0.001" class="p4-input">
                        </div>
                        <div class="p4-field">
                            <label>تاريخ الاستلام *</label>
                            <input type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\TH:i') }}" required class="p4-input">
                        </div>
                        <div class="p4-field" style="grid-column: 1 / -1">
                            <label>المرجع</label>
                            <input name="reference" class="p4-input" placeholder="مرجع أو إيداع بنكي">
                        </div>
                        <div class="p4-field" style="grid-column: 1 / -1">
                            <label>ملاحظات</label>
                            <textarea name="notes" rows="2" class="p4-textarea"></textarea>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;margin-top:12px">
                        <button class="p4-btn p4-btn-primary" type="submit">تسجيل التمويل</button>
                    </div>
                </form>
            </div>

            {{-- Fixed Asset Acquisition Form --}}
            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">اقتناء أصل ثابت</h2>
                    <span class="p4-kpi-meta">تسجيل معدات، أجهزة، أو ممتلكات</span>
                </div>
                <form method="POST" action="{{ route('fixed-assets.store') }}">
                    @csrf
                    <div class="p4-form-grid">
                        <div class="p4-field">
                            <label>اسم الأصل *</label>
                            <input name="name" required placeholder="جهاز حاسوب ماك بوك" class="p4-input">
                        </div>
                        <div class="p4-field">
                            <label>التصنيف *</label>
                            <select name="asset_category_id" required class="p4-select">
                                @foreach($activeAssetCategories as $category)
                                    <option value="{{ $category->id }}">{{ $category->displayName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="p4-field">
                            <label>تكلفة الاقتناء (د.أ) *</label>
                            <input name="acquisition_cost" required placeholder="100.000" class="p4-input">
                        </div>
                        <div class="p4-field">
                            <label>مصدر التمويل *</label>
                            <select name="funding_source" required class="p4-select">
                                <option value="{{ \App\Models\FixedAsset::FUNDING_COMPANY_ACCOUNT }}">حساب الشركة</option>
                                <option value="{{ \App\Models\FixedAsset::FUNDING_PERSONAL }}">دفع شخصي</option>
                            </select>
                        </div>
                        <div class="p4-field">
                            <label>الحساب المالي (للشركة)</label>
                            <select name="financial_account_id" class="p4-select">
                                <option value="">اختر عند التمويل من الشركة</option>
                                @foreach($activeFinancialAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="p4-field">
                            <label>الدافع الشخصي</label>
                            <select name="paid_by_user_id" class="p4-select">
                                <option value="">اختر عند التمويل الشخصي</option>
                                @foreach($internalUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="p4-field">
                            <label>المورد</label>
                            <select name="vendor_id" class="p4-select">
                                <option value="">بدون مورد محفوظ</option>
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
                            <label>الرقم التسلسلي</label>
                            <input name="serial_number" class="p4-input">
                        </div>
                        <div class="p4-field">
                            <label>الكمية</label>
                            <input type="number" name="quantity" value="1" min="1" class="p4-input">
                        </div>
                        <div class="p4-field">
                            <label>تاريخ الاقتناء *</label>
                            <input type="date" name="acquired_at" value="{{ now()->toDateString() }}" required class="p4-input">
                        </div>
                        <div class="p4-field">
                            <label>تاريخ بدء الخدمة</label>
                            <input type="date" name="in_service_at" class="p4-input">
                        </div>
                        <div class="p4-field">
                            <label>الموقع</label>
                            <input name="location" class="p4-input" placeholder="المكتب الرئيسي">
                        </div>
                        <div class="p4-field">
                            <label>العمر بالشهور</label>
                            <input type="number" name="useful_life_months" min="1" class="p4-input" placeholder="36">
                        </div>
                        <div class="p4-field">
                            <label>قيمة تخريدية</label>
                            <input name="residual_value" class="p4-input" placeholder="0.000">
                        </div>
                        <div class="p4-field" style="grid-column: 1 / -1">
                            <label>الوصف</label>
                            <input name="description" class="p4-input">
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;margin-top:12px">
                        <button class="p4-btn p4-btn-primary" type="submit">حفظ الأصل</button>
                    </div>
                </form>
            </div>
        </div>
    </x-notify.collapsible-section>

    {{-- 2. Lists: Funding Sources & Asset Categories --}}
    <x-notify.collapsible-section id="sec-sources-categories" title="مصادر التمويل وتصنيفات الأصول" subtitle="إدارة سجل مصادر التمويل المعتمدة وتصنيفات الأصول" :badge="$fundingSources->count() + $assetCategories->count()" :open="false">
        <div class="p4-grid-2">
            {{-- Funding Sources --}}
            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">مصادر التمويل</h2>
                    <span class="p4-kpi-meta">{{ $fundingSources->count() }} مصدر</span>
                </div>
                <form method="POST" action="{{ route('funding-sources.store') }}" style="margin-bottom:14px">
                    @csrf
                    <div class="p4-form-grid">
                        <div class="p4-field">
                            <label>الاسم *</label>
                            <input name="name" required class="p4-input" placeholder="أحمد - مؤسس">
                        </div>
                        <div class="p4-field">
                            <label>النوع *</label>
                            <select name="type" required class="p4-select">
                                <option value="founder">مؤسس</option>
                                <option value="owner">مالك</option>
                                <option value="investor">مستثمر</option>
                                <option value="lender">مقرض</option>
                                <option value="other">آخر</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;margin-top:10px">
                        <button class="p4-btn p4-btn-primary p4-btn-sm" type="submit">إضافة مصدر</button>
                    </div>
                </form>
                <div class="p4-list">
                    @foreach($fundingSources as $source)
                        <div class="p4-list-item" style="display:flex;justify-content:space-between;align-items:center">
                            <div>
                                <strong style="font-size:13px;color:#0A1128">{{ $source->name }}</strong>
                                <span class="p4-kpi-meta">{{ $source->type }} · {{ $source->is_active ? 'نشط' : 'مؤرشف' }}</span>
                            </div>
                            @if($source->archived_at === null)
                                <form method="POST" action="{{ route('funding-sources.archive', $source) }}">
                                    @csrf
                                    <button class="p4-btn p4-btn-ghost p4-btn-sm" type="submit">أرشفة</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Asset Categories --}}
            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">تصنيفات الأصول</h2>
                    <span class="p4-kpi-meta">{{ $assetCategories->count() }} تصنيف</span>
                </div>
                <form method="POST" action="{{ route('asset-categories.store') }}" style="margin-bottom:14px">
                    @csrf
                    <div class="p4-form-grid">
                        <div class="p4-field">
                            <label>الكود *</label>
                            <input name="code" required class="p4-input" placeholder="COMPUTERS">
                        </div>
                        <div class="p4-field">
                            <label>الاسم بالعربية *</label>
                            <input name="name_ar" required class="p4-input" placeholder="أجهزة حاسوب">
                        </div>
                        <div class="p4-field">
                            <label>الاسم بالإنجليزية</label>
                            <input name="name_en" class="p4-input" placeholder="Computers">
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;margin-top:10px">
                        <button class="p4-btn p4-btn-primary p4-btn-sm" type="submit">إضافة تصنيف</button>
                    </div>
                </form>
                <div class="p4-list">
                    @foreach($assetCategories as $category)
                        <div class="p4-list-item" style="display:flex;justify-content:space-between;align-items:center">
                            <div>
                                <strong style="font-size:13px;color:#0A1128">{{ $category->displayName() }}</strong>
                                <span class="p4-kpi-meta">{{ $category->code }} · {{ $category->is_active ? 'نشط' : 'مؤرشف' }}</span>
                            </div>
                            @if($category->archived_at === null)
                                <form method="POST" action="{{ route('asset-categories.archive', $category) }}">
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

    {{-- 3. Recent Funding Transactions & Fixed Assets Register --}}
    <x-notify.collapsible-section id="sec-transactions-register" title="حركات التمويل وسجل الأصول الثابتة" subtitle="سجل حركات التمويل الرأسمالي وأصول الشركة الثابتة في الخدمة" :badge="$fundingTransactions->count() + $fixedAssets->count()" :open="true">
        <div class="p4-grid-2">
            {{-- Recent Funding Transactions --}}
            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">أحداث التمويل الأخيرة</h2>
                    <span class="p4-kpi-meta">{{ $fundingTransactions->count() }} حركة</span>
                </div>
                <div class="p4-list">
                    @forelse($fundingTransactions as $transaction)
                        <div class="p4-list-item">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap">
                                <div>
                                    <div style="display:flex;gap:6px;align-items:center">
                                        <strong style="font-size:14px;color:#0A1128" dir="ltr">{{ $transaction->funding_number }}</strong>
                                        <span class="p4-badge {{ $transaction->reversal ? 'p4-badge-danger' : 'p4-badge-primary' }}">{{ $transaction->reversal ? 'معكوس' : 'نشط' }}</span>
                                    </div>
                                    <span style="font-size:14px;color:#0055CC;font-weight:700;display:block;margin-top:2px">
                                        {{ $money($transaction->amount_minor) }} د.أ · {{ $transaction->source_name_snapshot }}
                                    </span>
                                    <span class="p4-kpi-meta">{{ $transaction->received_at?->format('Y-m-d H:i') }} · {{ $transaction->financialAccount?->name_ar }}</span>
                                </div>
                                @if(!$transaction->reversal)
                                    <form method="POST" action="{{ route('capital-funding-transactions.reverse', $transaction) }}" style="display:flex;gap:4px">
                                        @csrf
                                        <input name="reason" placeholder="سبب العكس" required class="p4-input" style="max-width:120px;padding:4px 6px;font-size:12px">
                                        <button class="p4-btn p4-btn-danger p4-btn-sm" type="submit">عكس</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p4-kpi-meta" style="padding:14px;text-align:center">{{ __('notify.assets.empty_funding') }}</div>
                    @endforelse
                </div>
            </div>

            {{-- Fixed Assets Register --}}
            <div class="p4-card">
                <div class="p4-card-head">
                    <h2 class="p4-card-title">{{ __('notify.assets.assets_register') }}</h2>
                    <span class="p4-kpi-meta">{{ $fixedAssets->count() }} أصل</span>
                </div>
                <div class="p4-list">
                    @forelse($fixedAssets as $asset)
                        <div class="p4-list-item">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap">
                                <div>
                                    <div style="display:flex;gap:6px;align-items:center">
                                        <strong style="font-size:14px;color:#0A1128" dir="ltr">{{ $asset->asset_number }}</strong>
                                        <span style="font-size:14px;color:#0A1128;font-weight:700">{{ $asset->name }}</span>
                                        <span class="p4-badge {{ $asset->acquisitionReversal ? 'p4-badge-danger' : ($asset->status === 'active' ? 'p4-badge-success' : 'p4-badge-neutral') }}">
                                            {{ $asset->acquisitionReversal ? 'معكوس' : ($asset->status === 'active' ? 'نشط' : 'خارج الخدمة') }}
                                        </span>
                                    </div>
                                    <span style="font-size:13px;color:#0055CC;font-weight:700;display:block;margin-top:2px">
                                        {{ $money($asset->acquisition_cost_minor) }} د.أ · {{ $asset->category_name_snapshot }}
                                    </span>
                                    <span class="p4-kpi-meta">
                                        {{ $asset->acquired_at?->toDateString() }} · {{ $asset->location ?: 'بدون موقع' }} · {{ $asset->funding_source === \App\Models\FixedAsset::FUNDING_PERSONAL ? 'تمويل شخصي' : ($asset->financialAccount?->name_ar ?? 'حساب شركة') }}
                                    </span>
                                </div>
                                @if(!$asset->acquisitionReversal)
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                                        <form method="POST" action="{{ route('fixed-assets.status', $asset) }}" style="display:flex;gap:4px">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" class="p4-select" style="padding:4px 6px;font-size:12px">
                                                <option value="active" @selected($asset->status === 'active')>نشط</option>
                                                <option value="out_of_service" @selected($asset->status === 'out_of_service')>خارج الخدمة</option>
                                            </select>
                                            <button class="p4-btn p4-btn-soft p4-btn-sm" type="submit">تحديث</button>
                                        </form>
                                        <form method="POST" action="{{ route('fixed-assets.reverse', $asset) }}" style="display:flex;gap:4px">
                                            @csrf
                                            <input name="reason" placeholder="سبب العكس" required class="p4-input" style="max-width:110px;padding:4px 6px;font-size:12px">
                                            <button class="p4-btn p4-btn-danger p4-btn-sm" type="submit">عكس</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p4-kpi-meta" style="padding:14px;text-align:center">{{ __('notify.assets.empty_assets') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </x-notify.collapsible-section>

</div>
@endsection
