@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('clients.index') }}">العملاء</a> / استيراد ملف</div>
        <h1 class="page-title">استيراد جهات الاتصال عبر CSV</h1>
    </div>
    <div style="display:flex;gap:8px">
        <a class="btn btn-ghost" href="{{ route('clients.import.template', 'prospects') }}">⬇ تحميل نموذج الفرص</a>
        <a class="btn btn-ghost" href="{{ route('clients.import.template', 'subscribers') }}">⬇ تحميل نموذج المشتركين</a>
    </div>
</div>

<div class="grid grid-2">
    <div class="card form-card">
        <h3 style="margin-top:0">رفع ملف CSV</h3>
        <p class="muted">قم باختيار نوع البيانات وملف الـ CSV من جهازك. سيتم فحص التكرار تلقائياً بناءً على أرقام الهواتف.</p>

        <form method="POST" action="{{ route('clients.import.preview') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>نوع البيانات *</label>
                    <select name="type" required>
                        <option value="prospect" @selected(($type ?? '') === 'prospect')>فرص جديدة (Prospects)</option>
                        <option value="subscriber" @selected(($type ?? '') === 'subscriber')>مشتركون مباشرون (Subscribers)</option>
                    </select>
                </div>
                <div class="field">
                    <label>ملف الـ CSV *</label>
                    <input type="file" name="csv_file" accept=".csv,text/csv" required>
                </div>
                <div class="field full">
                    <button class="btn btn-primary" type="submit">معاينة وفحص الملف</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-top:0">تعليمات الاستيراد</h3>
        <ul style="padding-right:20px;font-size:13px;line-height:1.8;color:var(--nd-muted)">
            <li>تأكد من استخدام التنسيق القياسي الموجود في النماذج أعلاه.</li>
            <li>الحقول الإلزامية للفرص: <b>اسم النشاط، الهاتف، المنطقة، الفئة</b>.</li>
            <li>للمشتركين تضاف حقول: <b>نوع الفوترة (monthly, annual, installment)، والسعر الإجمالي</b>.</li>
            <li>يتم تنظيف أرقام الهواتف ومقارنتها تلقائياً مع قاعدة البيانات الحالية وداخل الملف لتجنب أي تكرار.</li>
            <li>لا يتم استيراد البيانات الفعلية إلا بعد مراجعة المعاينة والضغط على زر "تأكيد الاستيراد".</li>
        </ul>
    </div>
</div>

@if(isset($preview))
<div style="margin-top:24px">
    <div class="section-head">
        <h2>نتائج فحص ومعاينة الملف</h2>
        <span class="muted">إجمالي الصفوف: {{ $preview['total'] }}</span>
    </div>

    <div class="grid grid-3 kpis" style="margin-bottom:16px">
        <div class="card">
            <div class="kpi-label">صفوف صالحة للاستيراد</div>
            <div class="kpi-value" style="color:var(--nd-success)">{{ count($preview['valid']) }}</div>
            <div class="muted">جاهزة للإدخال في النظام</div>
        </div>
        <div class="card">
            <div class="kpi-label">صفوف مكررة (مستبعدة)</div>
            <div class="kpi-value" style="color:var(--nd-warning)">{{ count($preview['duplicates']) }}</div>
            <div class="muted">موجودة بالنظام أو مكررة بالملف</div>
        </div>
        <div class="card">
            <div class="kpi-label">صفوف غير مكتملة (مرفوضة)</div>
            <div class="kpi-value" style="color:var(--nd-danger)">{{ count($preview['invalid']) }}</div>
            <div class="muted">تنقصها حقول إلزامية</div>
        </div>
    </div>

    @if(count($preview['valid']) > 0)
    <div class="card" style="margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <h3 style="margin:0;color:var(--nd-success)">الصفوف الصالحة ({{ count($preview['valid']) }})</h3>
            <form method="POST" action="{{ route('clients.import.confirm') }}">
                @csrf
                <button class="btn btn-primary" type="submit">✓ تأكيد استيراد الصفوف الصالحة ({{ count($preview['valid']) }})</button>
            </form>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px;text-align:right">
                <thead>
                    <tr style="border-bottom:1px solid var(--nd-border);color:var(--nd-muted)">
                        <th style="padding:8px">السطر</th>
                        <th style="padding:8px">اسم النشاط</th>
                        <th style="padding:8px">الهاتف</th>
                        <th style="padding:8px">المنطقة</th>
                        <th style="padding:8px">الفئة</th>
                        <th style="padding:8px">جهة الاتصال</th>
                        @if($type === 'subscriber')
                        <th style="padding:8px">الفوترة</th>
                        <th style="padding:8px">المبلغ</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($preview['valid'] as $row)
                    <tr style="border-bottom:1px solid var(--nd-border)">
                        <td style="padding:8px">{{ $row['row'] }}</td>
                        <td style="padding:8px;font-weight:700">{{ $row['data']['business_name'] ?? '' }}</td>
                        <td style="padding:8px;direction:ltr;text-align:right">{{ $row['data']['phone'] ?? '' }}</td>
                        <td style="padding:8px">{{ $row['data']['city_area'] ?? '' }}</td>
                        <td style="padding:8px">{{ $row['data']['business_category'] ?? '' }}</td>
                        <td style="padding:8px">{{ $row['data']['contact_person'] ?? '-' }}</td>
                        @if($type === 'subscriber')
                        <td style="padding:8px"><span class="badge">{{ $row['data']['billing_type'] ?? '' }}</span></td>
                        <td style="padding:8px">{{ $row['data']['total_price'] ?? '' }} د.أ</td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if(count($preview['duplicates']) > 0)
    <div class="card" style="margin-bottom:16px">
        <h3 style="margin-top:0;color:var(--nd-warning)">الصفوف المكررة المستبعدة ({{ count($preview['duplicates']) }})</h3>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px;text-align:right">
                <thead>
                    <tr style="border-bottom:1px solid var(--nd-border);color:var(--nd-muted)">
                        <th style="padding:8px">السطر</th>
                        <th style="padding:8px">اسم النشاط</th>
                        <th style="padding:8px">الهاتف</th>
                        <th style="padding:8px">السبب</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($preview['duplicates'] as $row)
                    <tr style="border-bottom:1px solid var(--nd-border)">
                        <td style="padding:8px">{{ $row['row'] }}</td>
                        <td style="padding:8px;font-weight:700">{{ $row['data']['business_name'] ?? '' }}</td>
                        <td style="padding:8px;direction:ltr;text-align:right">{{ $row['data']['phone'] ?? '' }}</td>
                        <td style="padding:8px;color:var(--nd-warning)">{{ $row['reason'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if(count($preview['invalid']) > 0)
    <div class="card">
        <h3 style="margin-top:0;color:var(--nd-danger)">الصفوف غير المكتملة المرفوضة ({{ count($preview['invalid']) }})</h3>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px;text-align:right">
                <thead>
                    <tr style="border-bottom:1px solid var(--nd-border);color:var(--nd-muted)">
                        <th style="padding:8px">السطر</th>
                        <th style="padding:8px">اسم النشاط</th>
                        <th style="padding:8px">الهاتف</th>
                        <th style="padding:8px">سبب الرفض</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($preview['invalid'] as $row)
                    <tr style="border-bottom:1px solid var(--nd-border)">
                        <td style="padding:8px">{{ $row['row'] }}</td>
                        <td style="padding:8px;font-weight:700">{{ $row['data']['business_name'] ?? 'غير محدد' }}</td>
                        <td style="padding:8px;direction:ltr;text-align:right">{{ $row['data']['phone'] ?? 'غير محدد' }}</td>
                        <td style="padding:8px;color:var(--nd-danger)">{{ $row['reason'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endif
@endsection
