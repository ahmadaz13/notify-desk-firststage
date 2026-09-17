@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('partners.index') }}">الشركاء</a> / التفاصيل</div>
        <h1 class="page-title">{{ $partner->company_name }}</h1>
        <p class="muted">{{ $partner->contact_name ?: 'لا توجد جهة اتصال محددة' }} · {{ $partner->isActive() ? 'نشط' : 'مؤرشف' }}</p>
    </div>
    <a href="{{ route('partners.edit', $partner->id) }}" class="btn btn-primary touch-btn">تعديل</a>
</div>

<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card"><span class="muted">العملاء المحالون</span><strong>{{ $partnerSummary['total_clients'] }}</strong></div>
    <div class="stat-card"><span class="muted">المشتركون النشطون</span><strong>{{ $partnerSummary['active_subscribers'] }}</strong></div>
    <div class="stat-card"><span class="muted">صافي المحصل</span><strong>{{ $partnerSummary['net_collected'] }} د.أ</strong></div>
    <div class="stat-card"><span class="muted">العمولة المحسوبة</span><strong>{{ $partnerSummary['commission'] }} د.أ</strong></div>
</div>

@if($partnerSummary['has_legacy_attributions'])
    <div class="card" style="padding:14px;margin-bottom:16px">
        <p class="muted" style="margin:0">توجد إحالات قديمة محفوظة عبر الحقل السابق ولا تملك لقطة عمولة؛ تظهر مبالغها المحصلة دون احتساب عمولة افتراضية بأثر رجعي.</p>
    </div>
@endif

<div class="card" style="padding:0;overflow:hidden">
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;text-align:right">
            <thead>
                <tr style="background:#f8fafc;border-bottom:1px solid var(--nd-border)">
                    <th style="padding:14px">العميل</th>
                    <th style="padding:14px">حالة الاشتراك</th>
                    <th style="padding:14px">لقطة العمولة</th>
                    <th style="padding:14px">صافي المحصل</th>
                    <th style="padding:14px">العمولة</th>
                </tr>
            </thead>
            <tbody>
                @forelse($partnerSummary['clients'] as $row)
                    <tr style="border-bottom:1px solid var(--nd-border)">
                        <td style="padding:14px"><a href="{{ route('clients.show', $row['client']->id) }}">{{ $row['client']->business_name }}</a></td>
                        <td style="padding:14px">{{ $row['is_active_subscriber'] ? 'مشترك نشط' : 'غير نشط' }}</td>
                        <td style="padding:14px">{{ $row['commission_percentage'] ?? 'غير متاحة (إحالة قديمة)' }}</td>
                        <td style="padding:14px">{{ $row['net_collected'] }} د.أ</td>
                        <td style="padding:14px">{{ $row['commission'] === null ? 'غير محتسبة' : $row['commission'].' د.أ' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding:36px;text-align:center" class="muted">لا توجد إحالات مرتبطة بهذا الشريك.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
