{{--
    Notify V1 service contract (§8.4): two A4 pages — (1) commercial agreement summary, (2) terms & signatures.
    Rendered by mPDF for the PDF ($mode = 'pdf') and by the browser for Preview ($mode = 'screen').
    Everything printed comes from $document (App\Support\ContractDocument); absent data is omitted, never faked.
--}}
@php
    $d = $document;
    $company = $d['company'];
    $client = $d['client'];
    $isPdf = ($mode ?? 'pdf') === 'pdf';
    $companyName = $company['name_ar'] ?? ($company['name_en'] ?? 'Notify');
    $companyLegal = collect([
        'registration_number' => 'رقم التسجيل',
        'national_number' => 'الرقم الوطني للمنشأة',
        'tax_number' => 'الرقم الضريبي',
    ])->filter(fn ($label, $key) => filled($company[$key] ?? null));
    $logoPath = filled($company['logo'] ?? null) ? storage_path('app/public/'.$company['logo']) : null;
    $showLogo = $isPdf && $logoPath && is_file($logoPath) && extension_loaded('gd');
    $currency = 'د.أ';
    // Arabic count agreement: 2 → دفعتين, 3–10 → N دفعات, 11+ → N دفعة.
    $count = (int) $d['installments_count'];
    $installmentsWord = match (true) { $count === 2 => 'دفعتين', $count <= 10 => $count.' دفعات', default => $count.' دفعة' };
    // §8.4.1 payment wording; the agreed value is printed once, here.
    $value = '<strong><span class="ltr" dir="ltr">'.e($d['agreed_value']).'</span> '.$currency.'</strong>';
    $paymentSentence = match (true) {
        ! $d['is_annual'] => 'اشتراك شهري بقيمة '.$value.' يُستحق شهرياً في اليوم '.e($d['monthly_due_day']).' من كل شهر.',
        $d['is_installments'] => 'اشتراك سنوي بقيمة '.$value.' مقسّط على '.$installmentsWord.' وفق الجدول التالي:',
        default => 'اشتراك سنوي بقيمة '.$value.' يُدفع دفعة واحدة بتاريخ <span class="ltr" dir="ltr">'.e($d['full_payment_date']).'</span>.',
    };
    $scheduleColumns = count($d['schedules']) > 6 ? array_chunk($d['schedules'], (int) ceil(count($d['schedules']) / 2)) : [$d['schedules']];
    // One renewal sentence, printed identically on Page 1 and in Page 2 clause 6 (owner hardening).
    $renewal = $d['is_annual']
        ? 'يتجدد الاشتراك السنوي لمدة مماثلة ما لم يُخطر أحد الطرفين الآخر خطياً بعدم الرغبة في التجديد قبل 30 يوماً من نهاية مدة الاشتراك.'
        : 'يتجدد الاشتراك الشهري تلقائياً لدورة شهرية جديدة ما لم يطلب الطرف الثاني إيقاف التجديد قبل موعد استحقاق الدورة التالية.';
    // Elastic Page-1 spacing: simple agreements breathe more; the 12-installment layout keeps its dense rhythm.
    $density = $d['is_installments'] ? (count($d['schedules']) > 6 ? 'dense' : 'medium') : 'roomy';
    $space = [
        'dense' => ['title' => '2mm', 'section' => '3.2mm', 'points' => '3.2mm', 'party' => '2mm', 'kv' => '1.1mm', 'svc' => '0.9mm', 'point' => '0.8mm'],
        'medium' => ['title' => '2.8mm', 'section' => '4.6mm', 'points' => '5mm', 'party' => '2.8mm', 'kv' => '1.5mm', 'svc' => '1.1mm', 'point' => '1.2mm'],
        'roomy' => ['title' => '4.5mm', 'section' => '8.5mm', 'points' => '11mm', 'party' => '4.5mm', 'kv' => '2.7mm', 'svc' => '1.3mm', 'point' => '2.4mm'],
    ][$density];
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    @unless($isPdf)<meta name="viewport" content="width=device-width, initial-scale=1">@endunless
    <title>{{ $d['number'] ?: 'مسودة عقد' }} — {{ $client['business_name'] ?? '' }}</title>
    <style>
        body { font-family: xbriyaz, 'Noto Naskh Arabic', 'Segoe UI', Tahoma, sans-serif; font-size: 9.8pt; line-height: 1.55; color: #1f2933; direction: rtl; }
        h1, h2, h3, p { margin: 0; }
        .ltr { direction: ltr; unicode-bidi: embed; }
        .accent { color: #0b4f8a; }
        .muted { color: #5b6673; }
        .head { width: 100%; border-collapse: collapse; border-bottom: 1.2pt solid #0b4f8a; margin-bottom: 3mm; }
        .head td { vertical-align: bottom; padding: 0 0 2.2mm 0; }
        .brand { font-size: 13pt; font-weight: bold; color: #0b4f8a; }
        .brand-en { font-size: 9pt; color: #5b6673; }
        .doc-title { font-size: 12pt; font-weight: bold; text-align: left; }
        .doc-meta { font-size: 9pt; text-align: left; color: #5b6673; }
        .status { display: inline; font-size: 9pt; font-weight: bold; color: #9a3412; border: 0.8pt solid #9a3412; padding: 0.4mm 2mm; }
        .page-title { font-size: 11.5pt; font-weight: bold; color: #0b4f8a; margin: 1mm 0 {{ $space['title'] }}; }
        .section { font-size: 10.5pt; font-weight: bold; color: #0b4f8a; margin: {{ $space['section'] }} 0 1.6mm; }
        .section-points { margin-top: {{ $space['points'] }}; }
        .parties { width: 100%; border-collapse: separate; border-spacing: 2mm 0; margin: 0 -2mm; }
        .parties td { width: 50%; vertical-align: top; border: 0.6pt solid #cbd2d9; padding: {{ $space['party'] }} 3mm; }
        .party-label { font-size: 9pt; font-weight: bold; color: #0b4f8a; margin-bottom: 0.8mm; }
        .party-name { font-size: 10.5pt; font-weight: bold; }
        .kv { width: 100%; border-collapse: collapse; }
        .kv td { padding: {{ $space['kv'] }} 2mm; border-bottom: 0.5pt solid #e4e7eb; vertical-align: top; }
        .kv td.k { width: 30%; color: #5b6673; }
        .svc-row { padding: {{ $space['svc'] }} 0; }
        .svc-name { font-weight: bold; direction: ltr; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid th { background: #eef3f8; color: #0b4f8a; font-size: 9pt; padding: 1.1mm 2mm; border: 0.5pt solid #cbd2d9; text-align: right; }
        .grid td { font-size: 9pt; padding: 1mm 2mm; border: 0.5pt solid #cbd2d9; }
        .schedule-wrap { width: 100%; border-collapse: collapse; }
        .schedule-wrap > tbody > tr > td { vertical-align: top; padding: 0 0 0 2mm; }
        .key-points { margin: 0; padding: 0 5mm 0 0; }
        .key-points li { margin-bottom: {{ $space['point'] }}; }
        .clause { margin-bottom: 2.7mm; text-align: justify; }
        .clause-title { font-size: 10.4pt; font-weight: bold; color: #0b4f8a; }
        .custom-terms { white-space: pre-line; text-align: justify; }
        .signatures { width: 100%; border-collapse: collapse; margin-top: 6mm; border-top: 1pt solid #0b4f8a; page-break-inside: avoid; }
        .signatures td { vertical-align: bottom; padding: 0 0 1.2mm; height: 11mm; }
        .signatures td.sig-head { height: auto; padding: 2.5mm 0 1mm; vertical-align: top; font-weight: bold; color: #0b4f8a; }
        .signatures td.sig-k { width: 30mm; color: #5b6673; }
        .signatures td.sig-v { width: 52mm; border-bottom: 0.6pt dotted #7b8794; }
        .signatures td.sig-tall { height: 20mm; }
        .signatures td.sig-gap { width: 8mm; }
        @if(! $isPdf)
            html { background: #e9edf1; }
            body { margin: 0; padding: 72px 0 32px; }
            .sheet { position: relative; box-sizing: border-box; width: 210mm; min-height: 297mm; margin: 0 auto 10mm; padding: 18mm 19mm 20mm; background: #fff; box-shadow: 0 2px 10px rgba(15, 23, 42, .12); overflow: hidden; }
            .watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 96pt; font-weight: bold; color: rgba(154, 52, 18, .07); transform: rotate(-35deg); pointer-events: none; }
            .sheet-footer { position: absolute; bottom: 8mm; left: 19mm; right: 19mm; font-size: 8.5pt; color: #5b6673; text-align: center; }
            .preview-bar { position: fixed; top: 0; left: 0; right: 0; z-index: 10; display: flex; gap: 12px; align-items: center; justify-content: space-between; padding: 12px 20px; background: #fff; border-bottom: 1px solid #d5dbe1; font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 14px; }
            .preview-bar a { color: #0b4f8a; font-weight: 700; text-decoration: none; min-height: 44px; display: inline-flex; align-items: center; }
            @media (max-width: 820px) { .sheet { width: auto; min-height: 0; margin: 0 8px 12px; padding: 18px 16px 40px; } body { padding-top: 64px; } }
        @endif
    </style>
</head>
<body>
@unless($isPdf)
    <div class="preview-bar">
        <strong>@if($d['number'])<span dir="ltr">{{ $d['number'] }}</span>@else مسودة / DRAFT @endif</strong>
        @isset($backUrl)<a href="{{ $backUrl }}">{{ __('notify.contracts.back_to_client') }}</a>@endisset
    </div>
@endunless

{{-- ============ PAGE 1 — COMMERCIAL AGREEMENT SUMMARY ============ --}}
@unless($isPdf)<div class="sheet" data-contract-page="1">@if($d['is_draft'])<div class="watermark">DRAFT</div>@endif @endunless
<table class="head">
    <tr>
        <td>
            @if($showLogo)<img src="{{ $logoPath }}" style="height:13mm;margin-bottom:1.5mm" alt=""><br>@endif
            <span class="brand">{{ $companyName }}</span>
            @if(filled($company['name_en'] ?? null) && ($company['name_en'] !== $companyName))<br><span class="brand-en ltr" dir="ltr">{{ $company['name_en'] }}</span>@endif
        </td>
        <td style="text-align:left">
            <div class="doc-title">عقد تقديم خدمات وحلول برمجية</div>
            <div class="doc-meta">
                @if($d['is_draft'])
                    <span class="status" data-contract-status="draft">مسودة / DRAFT</span>
                @else
                    رقم العقد: <span class="ltr" dir="ltr" data-contract-number>{{ $d['number'] }}</span> · تاريخ الإصدار: <span class="ltr" dir="ltr">{{ $d['date'] }}</span>
                @endif
            </div>
        </td>
    </tr>
</table>

<h1 class="page-title">ملخص الاتفاق التجاري</h1>

<table class="parties">
    <tr>
        <td>
            <div class="party-label">الطرف الأول — مزوّد الخدمة</div>
            <div class="party-name">{{ $companyName }}</div>
            @if(filled($company['address'] ?? null))<div>{{ $company['address'] }}</div>@endif
            @if(filled($company['phone'] ?? null))<div>الهاتف: <span class="ltr" dir="ltr">{{ $company['phone'] }}</span></div>@endif
            @if(filled($company['email'] ?? null))<div>البريد الإلكتروني: <span class="ltr" dir="ltr">{{ $company['email'] }}</span></div>@endif
            @foreach($companyLegal as $key => $label)
                <div data-company-legal="{{ $key }}">{{ $label }}: <span class="ltr" dir="ltr">{{ $company[$key] }}</span></div>
            @endforeach
        </td>
        <td>
            <div class="party-label">الطرف الثاني — العميل</div>
            <div class="party-name">{{ $client['business_name'] ?? '' }}</div>
            @if(filled($client['contact_name'] ?? null))<div>المسؤول: {{ $client['contact_name'] }}@if(filled($client['contact_phone'] ?? null)) · <span class="ltr" dir="ltr">{{ $client['contact_phone'] }}</span>@endif</div>@endif
            @if(filled($client['phone'] ?? null))<div>الهاتف: <span class="ltr" dir="ltr">{{ $client['phone'] }}</span></div>@endif
            @if(filled($client['city_area'] ?? null))<div>المدينة / المنطقة: {{ $client['city_area'] }}</div>@endif
            @if(filled($client['address'] ?? null))<div>العنوان: {{ $client['address'] }}</div>@endif
        </td>
    </tr>
</table>

<h2 class="section">الخدمات المشمولة</h2>
<div class="services" data-contract-services>
    @foreach($d['services'] as $service)
        @php($description = filled($service['custom_title'] ?? null) ? ($service['custom_description'] ?? null) : ($service['description_ar'] ?? null))
        {{-- Block rows, one line of markup each: mPDF mis-sizes auto-width table cells holding long shaped Arabic. --}}
        <div class="svc-row" data-contract-service="{{ $service['code'] ?? '' }}">• <span class="svc-name" dir="ltr">{{ $service['name'] }}</span>@if(filled($service['custom_title'] ?? null)) — {{ $service['custom_title'] }}@endif @if(filled($description))<span class="muted">— {{ $description }}</span>@endif</div>
    @endforeach
</div>

<h2 class="section">الاشتراك والقيمة المتفق عليها</h2>
<table class="kv">
    <tr><td class="k">نوع الاشتراك</td><td>{{ $d['is_annual'] ? 'سنوي' : 'شهري' }}</td></tr>
    @if(filled($d['start_date']))<tr><td class="k">تاريخ البدء</td><td><span class="ltr" dir="ltr">{{ $d['start_date'] }}</span></td></tr>@endif
    @if($d['is_annual'] && filled($d['end_date']))<tr><td class="k">تاريخ الانتهاء</td><td><span class="ltr" dir="ltr">{{ $d['end_date'] }}</span></td></tr>@endif
    {{-- A recurring monthly agreement has no final end date; it states its renewal cycle instead. --}}
    @unless($d['is_annual'])<tr><td class="k">دورة التجديد</td><td data-renewal-cycle>شهرية</td></tr>@endunless
    <tr><td class="k">القيمة وترتيب الدفع</td><td data-payment-terms>{!! $paymentSentence !!}</td></tr>
</table>

@if($d['is_installments'])
    <table class="schedule-wrap" style="margin-top:2mm" data-installments>
        <tr>
            @foreach($scheduleColumns as $column)
                <td style="width:{{ count($scheduleColumns) > 1 ? '50%' : '100%' }}">
                    <table class="grid">
                        <tr><th>الدفعة</th><th>المبلغ</th><th>تاريخ الاستحقاق</th></tr>
                        @foreach($column as $row)
                            <tr><td>{{ $row['sequence'] }}</td><td><span class="ltr" dir="ltr">{{ $row['amount'] }}</span> {{ $currency }}</td><td><span class="ltr" dir="ltr">{{ $row['due_date'] }}</span></td></tr>
                        @endforeach
                    </table>
                </td>
            @endforeach
        </tr>
    </table>
@endif

{{-- Commercial summary only; Page 2 holds the authoritative terms. --}}
<h2 class="section section-points">أبرز بنود الاتفاق</h2>
<ul class="key-points" data-key-points>
    <li>تُقدَّم الخدمات المشمولة أعلاه طوال مدة الاشتراك المتفق عليها.</li>
    <li>يبدأ التفعيل بعد استلام الدفعة الأولى وتوفر بيانات العميل الأساسية.</li>
    <li data-renewal>{{ $renewal }}</li>
    <li>لا تُسترد المبالغ المسددة عن فترات أو خدمات تم تفعيلها، وفق البند 7 من الشروط والأحكام.</li>
</ul>
@unless($isPdf)<div class="sheet-footer">@if($d['number'])رقم العقد <span dir="ltr">{{ $d['number'] }}</span>@else مسودة @endif — صفحة 1 من 2</div></div>@endunless

{{-- ============ PAGE 2 — TERMS & SIGNATURES ============ --}}
@if($isPdf)<pagebreak />@else<div class="sheet" data-contract-page="2">@if($d['is_draft'])<div class="watermark">DRAFT</div>@endif @endif
<h1 class="page-title">الشروط والأحكام</h1>

@if($d['terms'])
    <div class="custom-terms" data-custom-terms>{{ $d['terms'] }}</div>
@else
    @include('contracts.clauses.v1', ['document' => $d, 'renewal' => $renewal])
@endif

@php($parties = [
    ['head' => 'عن الطرف الأول — '.$companyName, 'name' => $company['authorized_signatory'] ?? null],
    ['head' => 'عن الطرف الثاني — '.($client['business_name'] ?? ''), 'name' => $client['contact_name'] ?? null],
])
{{-- One flat table (mPDF-safe): label + writing line per party, a gap column between the parties. --}}
<table class="signatures" data-signatures>
    <tr>
        <td colspan="2" class="sig-head">{{ $parties[0]['head'] }}</td><td class="sig-gap"></td><td colspan="2" class="sig-head">{{ $parties[1]['head'] }}</td>
    </tr>
    @foreach(['name' => 'الاسم', 'signature' => 'التوقيع والختم إن وجد', 'date' => 'التاريخ'] as $row => $label)
        <tr>
            @foreach($parties as $index => $party)
                @if($index === 1)<td class="sig-gap"></td>@endif
                <td class="sig-k {{ $row === 'signature' ? 'sig-tall' : '' }}">{{ $label }}</td>
                <td class="sig-v {{ $row === 'signature' ? 'sig-tall' : '' }}">{{ $row === 'name' ? $party['name'] : '' }}</td>
            @endforeach
        </tr>
    @endforeach
</table>
@unless($isPdf)<div class="sheet-footer">@if($d['number'])رقم العقد <span dir="ltr">{{ $d['number'] }}</span>@else مسودة @endif — صفحة 2 من 2</div></div>@endunless
</body>
</html>
