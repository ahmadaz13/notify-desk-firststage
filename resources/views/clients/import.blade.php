@extends('layouts.app')

@section('content')
<div class="p5-wrap">
    {{-- Header --}}
    <header class="p5-header">
        <div class="p5-header-main">
            <div class="p5-eyebrow"><a href="{{ route('clients.index') }}" style="color:#0055CC;text-decoration:none">{{ __('notify.clients.title') }}</a> / استيراد ملف</div>
            <h1 class="p5-title">{{ __('notify.clients.import_title') }}</h1>
            <p class="p5-subtitle">رفع واستيراد الفرص التشغيلية الجديدة مع فحص آلي فوري للتكرار والبيانات الإلزامية قبل التأكيد. تحويل العميل إلى مشترك يتم فقط من ملف العميل عبر مسار الاشتراك المدفوع المعتمد.</p>
        </div>
        <div class="p5-header-actions">
            <a class="p5-btn p5-btn-soft" href="{{ route('clients.import.template', 'prospects') }}">{{ __('notify.clients.download_template') }}</a>
        </div>
    </header>

    {{-- Upload & Instructions --}}
    <div class="p5-grid-2">
        {{-- Upload Form --}}
        <div class="p5-card">
            <div class="p5-card-head">
                <h2 class="p5-card-title">{{ __('notify.clients.upload_card_title') }}</h2>
                <span class="p5-kpi-meta">{{ __('notify.clients.upload_card_step') }}</span>
            </div>
            <p class="p5-kpi-meta" style="margin-bottom:14px">{{ __('notify.clients.upload_card_desc') }}</p>
            <form method="POST" action="{{ route('clients.import.preview') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="type" value="prospect">
                <div class="p5-form-grid">
                    <div class="p5-field">
                        <label>{{ __('notify.clients.file_label') }} *</label>
                        <input type="file" name="csv_file" accept=".csv,text/csv" required class="p5-input">
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;margin-top:14px">
                    <button class="p5-btn p5-btn-primary" type="submit">معاينة وفحص الملف</button>
                </div>
            </form>
        </div>

        {{-- Guidelines --}}
        <div class="p5-card">
            <div class="p5-card-head">
                <h2 class="p5-card-title">إرشادات تنسيق الملف</h2>
                <span class="p5-kpi-meta">قواعد مهمة</span>
            </div>
            <ul style="padding-inline-start:18px;font-size:13px;line-height:1.8;color:#475569;margin:0">
                <li>استخدم التنسيق القياسي الموجود في النماذج أعلاه دون تعديل أسماء الأعمدة.</li>
                <li>الحقول الإلزامية: <strong>اسم النشاط، الهاتف، المنطقة، الفئة</strong>.</li>
                <li>عمود <code>primary_phone_type</code> (اختياري) يحدد لمن يعود رقم الهاتف: <code>business</code> أو <code>owner</code> أو <code>manager</code>. إذا تُرك فارغاً يُعتبر الرقم رقم النشاط التجاري (<code>business</code>).</li>
                <li>أعمدة جهة الاتصال اختيارية: <code>contact_name</code> (أو <code>contact_person</code>)، <code>contact_role</code>، <code>contact_phone</code>، <code>contact_whatsapp</code>، <code>contact_email</code>. اسم المالك غير مطلوب.</li>
                <li>الاستيراد ينشئ فرصاً تشغيلية فقط؛ لا ينشئ اشتراكات أو فواتير أو دفعات أو جداول تحصيل.</li>
                <li>يتم فحص أرقام الهواتف ومقارنتها تلقائياً مع قاعدة البيانات الحالية وداخل الملف لتجنب أي تكرار.</li>
                <li>لا يتم إدخال أي سجل فعلي إلى النظام إلا بعد فحص المعاينة والضغط على "تأكيد الاستيراد".</li>
            </ul>
        </div>
    </div>

    {{-- Preview Results Section --}}
    @if(isset($preview))
        <div class="p5-card">
            <div class="p5-card-head">
                <div>
                    <h2 class="p5-card-title">نتائج فحص ومعاينة الملف</h2>
                    <span class="p5-kpi-meta" style="display:block;margin-top:2px">إجمالي الصفوف المقروءة: {{ $preview['total'] }}</span>
                </div>
            </div>

            {{-- Summary KPIs --}}
            <div class="p5-kpis" style="margin-bottom:18px">
                <div class="p5-kpi-card">
                    <span class="p5-kpi-label">صفوف صالحة للاستيراد</span>
                    <span class="p5-kpi-value is-success">{{ count($preview['valid']) }}</span>
                    <span class="p5-kpi-meta">مستوفية للشروط وجاهزة للإدخال</span>
                </div>
                <div class="p5-kpi-card">
                    <span class="p5-kpi-label">صفوف مكررة (مستبعدة)</span>
                    <span class="p5-kpi-value is-warning">{{ count($preview['duplicates']) }}</span>
                    <span class="p5-kpi-meta">موجودة مسبقاً أو مكررة بالملف</span>
                </div>
                <div class="p5-kpi-card">
                    <span class="p5-kpi-label">صفوف غير مكتملة (مرفوضة)</span>
                    <span class="p5-kpi-value is-danger">{{ count($preview['invalid']) }}</span>
                    <span class="p5-kpi-meta">تنقصها حقول إلزامية أو رقم غير صالح</span>
                </div>
            </div>

            {{-- 1. Valid Rows Table & Confirm Button --}}
            @if(count($preview['valid']) > 0)
                <div style="margin-bottom:20px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px">
                        <h3 style="font-size:15px;color:#16A34A;margin:0">الصفوف الصالحة ({{ count($preview['valid']) }})</h3>
                        <form method="POST" action="{{ route('clients.import.confirm') }}">
                            @csrf
                            <button class="p5-btn p5-btn-primary" type="submit">✓ تأكيد استيراد الصفوف الصالحة ({{ count($preview['valid']) }})</button>
                        </form>
                    </div>
                    <div class="p5-table-wrap">
                        <table class="p5-table">
                            <thead>
                                <tr>
                                    <th>السطر</th>
                                    <th>اسم النشاط</th>
                                    <th>الهاتف</th>
                                    <th>المنطقة</th>
                                    <th>الفئة</th>
                                    <th>جهة الاتصال</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($preview['valid'] as $row)
                                    <tr>
                                        <td>{{ $row['row'] }}</td>
                                        <td><strong>{{ $row['data']['business_name'] ?? '' }}</strong></td>
                                        <td dir="ltr" style="text-align:right">{{ $row['data']['phone'] ?? '' }}</td>
                                        <td>{{ $row['data']['city_area'] ?? '' }}</td>
                                        <td>{{ $row['data']['business_category'] ?? '' }}</td>
                                        <td>{{ ($row['data']['contact_name'] ?? '') ?: (($row['data']['contact_person'] ?? '') ?: '—') }} <small>({{ __('notify.clients.contact_model.phone_types.'.(app(\App\Services\CsvImportService::class)->primaryPhoneType($row['data']) ?? 'business')) }})</small></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- 2. Duplicates Table --}}
            @if(count($preview['duplicates']) > 0)
                <div style="margin-bottom:20px">
                    <h3 style="font-size:14px;color:#D97706;margin:0 0 10px">الصفوف المكررة المستبعدة ({{ count($preview['duplicates']) }})</h3>
                    <div class="p5-table-wrap">
                        <table class="p5-table">
                            <thead>
                                <tr>
                                    <th>السطر</th>
                                    <th>اسم النشاط</th>
                                    <th>الهاتف</th>
                                    <th>سبب الاستبعاد</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($preview['duplicates'] as $row)
                                    <tr>
                                        <td>{{ $row['row'] }}</td>
                                        <td><strong>{{ $row['data']['business_name'] ?? '' }}</strong></td>
                                        <td dir="ltr" style="text-align:right">{{ $row['data']['phone'] ?? '' }}</td>
                                        <td><span class="p5-badge p5-badge-warning">{{ $row['reason'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- 3. Invalid Table --}}
            @if(count($preview['invalid']) > 0)
                <div>
                    <h3 style="font-size:14px;color:#B42318;margin:0 0 10px">الصفوف غير المكتملة المرفوضة ({{ count($preview['invalid']) }})</h3>
                    <div class="p5-table-wrap">
                        <table class="p5-table">
                            <thead>
                                <tr>
                                    <th>السطر</th>
                                    <th>اسم النشاط</th>
                                    <th>الهاتف</th>
                                    <th>سبب الرفض</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($preview['invalid'] as $row)
                                    <tr>
                                        <td>{{ $row['row'] }}</td>
                                        <td><strong>{{ $row['data']['business_name'] ?? 'غير محدد' }}</strong></td>
                                        <td dir="ltr" style="text-align:right">{{ $row['data']['phone'] ?? 'غير محدد' }}</td>
                                        <td><span class="p5-badge p5-badge-danger">{{ $row['reason'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
