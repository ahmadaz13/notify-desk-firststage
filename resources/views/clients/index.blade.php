@extends('layouts.app')
@section('content')
<div class="page-head"><div><div class="eyebrow">دورة المبيعات</div><h1 class="page-title">العملاء</h1></div><a class="btn btn-primary" href="{{ route('dashboard') }}#quick-add">＋ عميل جديد</a></div>
<div class="card" style="margin-bottom:16px"><form method="GET" class="form-grid"><div class="field"><label>بحث</label><input name="q" value="{{ $query }}" placeholder="اسم النشاط، الهاتف، المنطقة"></div><div class="field"><label>مرحلة العميل</label><select name="status"><option value="all" @selected($status==='all')>كل المراحل</option>@foreach($lifecycleStages as $stageOption)<option value="{{ $stageOption }}" @selected($status===$stageOption)>{{ $lifecycleLabels[$stageOption] ?? $stageOption }}</option>@endforeach<option value="archived" @selected($status==='archived')>أرشيف قديم</option></select></div><div class="field full"><button class="btn btn-soft" type="submit">تطبيق البحث</button></div></form></div>
<div class="card list">
@forelse($clients as $client)
    @php
        $stage = \App\Support\ClientLifecycle::normalizeStage($client->stage ?? null, $client->status ?? null);
        $stageLabel = $lifecycleLabels[$stage] ?? $stage;
        $badgeClass = $stage === \App\Support\ClientLifecycle::SUBSCRIBER ? 'green' : ($stage === \App\Support\ClientLifecycle::CLOSED ? '' : 'gold');
        $businessPhone = $client->business_phone ?: $client->phone;
        $contactName = optional($client->primaryContact)->name ?: $client->contact_person;
        $contactPhone = optional($client->primaryContact)->primary_phone ?: $client->phone;
        $whatsappPhone = optional($client->primaryContact)->whatsapp_number ?: $contactPhone;
        $cleanPhone = preg_replace('/[^0-9]/', '', $whatsappPhone ?? '');
        $waPhone = str_starts_with($cleanPhone, '0') ? '962' . substr($cleanPhone, 1) : (str_starts_with($cleanPhone, '7') ? '962' . $cleanPhone : $cleanPhone);
    @endphp
    <div class="list-row" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px">
        <a href="{{ route('clients.show', $client->id) }}" style="display:flex;align-items:center;gap:12px;flex:1;text-decoration:none;color:inherit">
            <span class="avatar">{{ mb_substr($client->business_name,0,1) }}</span>
            <div class="list-main">
                <strong>{{ $client->business_name }}</strong>
                <small>{{ $client->business_type ?: $client->business_category }} · {{ $client->city ?: $client->city_area }}@if($client->area) / {{ $client->area }} @endif · هاتف النشاط <span style="direction:ltr;display:inline-block">{{ $businessPhone }}</span></small>
                @if($contactName || $contactPhone)
                    <small>جهة الاتصال: {{ $contactName ?: 'غير محدد' }}@if($contactPhone) · <span style="direction:ltr;display:inline-block">{{ $contactPhone }}</span>@endif</small>
                @endif
            </div>
            <span class="badge {{ $badgeClass }}">{{ $stageLabel }}</span>
        </a>
        <div style="display:flex;gap:6px;align-items:center">
            <a href="tel:{{ $contactPhone }}" class="btn btn-soft" style="min-width:48px;min-height:48px;display:inline-flex;align-items:center;justify-content:center;border-radius:12px;font-size:18px;text-decoration:none" title="اتصال هاتفي">📞</a>
            <a href="https://wa.me/{{ $waPhone }}" target="_blank" class="btn btn-soft" style="min-width:48px;min-height:48px;display:inline-flex;align-items:center;justify-content:center;border-radius:12px;font-size:18px;text-decoration:none;background:#dcfce7;color:#15803d;border-color:#bbf7d0" title="واتساب">💬</a>
        </div>
    </div>
@empty
    <div style="padding:48px 20px;text-align:center">
        <div style="font-size:48px;margin-bottom:12px;opacity:0.85">👥</div>
        <h3 style="margin:0 0 8px 0;font-size:18px;color:var(--nd-ink)">لا يوجد عملاء بعد</h3>
        <p class="muted" style="margin:0 0 20px 0;font-size:14px">ابدأ بإضافة أول عميل لمتابعة دورة المبيعات والاشتراكات والمواعيد.</p>
        <a href="{{ route('dashboard') }}#quick-add" class="btn btn-primary touch-btn" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;font-size:14px;font-weight:700">
            <span>+ إضافة عميل</span>
        </a>
    </div>
@endforelse
</div>
@if($clients->hasPages())<div class="pagination">@foreach($clients->links()->elements[0] ?? [] as $page => $url)<a class="{{ $page == $clients->currentPage() ? 'active' : '' }}" href="{{ $url }}">{{ $page }}</a>@endforeach</div>@endif
@endsection
