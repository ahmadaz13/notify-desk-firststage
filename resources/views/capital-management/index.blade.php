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
    </div>

    {{-- 1. Capital Funding (§13: fixed assets are not part of V1) --}}
    <x-notify.collapsible-section id="sec-capital-forms" title="تسجيل التمويل" subtitle="نموذج تسجيل التمويل الرأسمالي الجديد" :open="true">
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

        </div>
    </x-notify.collapsible-section>

    {{-- 2. Funding Sources --}}
    <x-notify.collapsible-section id="sec-sources-categories" title="مصادر التمويل" subtitle="إدارة سجل مصادر التمويل المعتمدة" :badge="$fundingSources->count()" :open="false">
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

        </div>
    </x-notify.collapsible-section>

    {{-- 3. Recent Funding Transactions --}}
    <x-notify.collapsible-section id="sec-transactions-register" title="حركات التمويل" subtitle="سجل حركات التمويل الرأسمالي" :badge="$fundingTransactions->count()" :open="true">
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

        </div>
    </x-notify.collapsible-section>

</div>
@endsection
