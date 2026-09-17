<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>عقد اشتراك خدمات Notify - {{ $contractNumber }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 20mm 15mm 20mm 15mm;
            @bottom-center {
                content: "صفحة " counter(page) " من " counter(pages);
                font-family: 'Cairo', sans-serif;
                font-size: 10pt;
                color: #64748b;
            }
        }
        * {
            box-sizing: border-box;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #fff !important;
                padding: 0 !important;
            }
            .page-container {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        }
        @media screen {
            body {
                background: #f8fafc;
                padding: 24px 12px;
            }
            .page-container {
                max-width: 210mm;
                margin: 0 auto;
                background: #fff;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
                padding: 20mm 15mm;
                border-radius: 8px;
            }
        }
        body {
            font-family: 'Cairo', sans-serif;
            margin: 0;
            padding: 0;
            color: #050B0D;
            line-height: 1.65;
            font-size: 11pt;
            direction: rtl;
            text-align: right;
        }
        .page {
            page-break-after: always;
            position: relative;
            min-height: 100%;
        }
        .page:last-child {
            page-break-after: avoid;
        }
        .cover-page {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 250mm;
            border: 2px solid #0C86ED;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
        }
        .cover-header {
            border-bottom: 3px solid #0C86ED;
            padding-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .brand-title {
            font-size: 28pt;
            font-weight: 900;
            color: #0C86ED;
            margin: 0;
        }
        .brand-subtitle {
            font-size: 13pt;
            color: #32383A;
            margin-top: 4px;
            font-weight: 700;
        }
        .contract-badge {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #0C86ED;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 12pt;
            display: inline-block;
            direction: ltr;
        }
        .cover-body {
            margin: 40px 0;
        }
        .cover-title {
            font-size: 22pt;
            font-weight: 900;
            color: #050B0D;
            margin-bottom: 15px;
        }
        .legal-notice {
            background: #fffbeb;
            border-right: 4px solid #f59e0b;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 10.5pt;
            color: #92400e;
            margin-bottom: 25px;
        }
        .parties-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .party-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
        }
        .party-card h4 {
            margin: 0 0 10px 0;
            color: #0C86ED;
            font-size: 12pt;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 6px;
        }
        .party-card p {
            margin: 4px 0;
            font-size: 10.5pt;
        }
        .section-heading {
            color: #0C86ED;
            font-size: 13pt;
            font-weight: 800;
            border-bottom: 1.5px solid #0C86ED;
            padding-bottom: 4px;
            margin-top: 22px;
            margin-bottom: 8px;
            page-break-after: avoid;
        }
        .clause-item {
            margin-bottom: 12px;
            text-align: justify;
        }
        .clause-item strong {
            color: #050B0D;
            display: block;
            margin-bottom: 3px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0;
            font-size: 10pt;
        }
        table, th, td {
            border: 1px solid #cbd5e1;
        }
        th {
            background: #f0f9ff;
            color: #0369a1;
            font-weight: 800;
            padding: 8px 10px;
            text-align: right;
        }
        td {
            padding: 8px 10px;
        }
        .signatures-area {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .sig-box {
            border: 1px solid #94a3b8;
            border-radius: 10px;
            padding: 14px;
            background: #fafafa;
        }
        .sig-box h4 {
            margin: 0 0 10px 0;
            color: #050B0D;
            font-size: 11pt;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 6px;
        }
        .sig-line {
            margin-top: 40px;
            border-top: 1px solid #334155;
            padding-top: 6px;
            font-size: 10pt;
            color: #64748b;
        }
        .appendix-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            margin-top: 15px;
        }
        .ltr {
            direction: ltr;
            display: inline-block;
            font-family: 'Inter', sans-serif;
        }
        .no-break {
            page-break-inside: avoid;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: none;
            }
            .page {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    @php
        $productSnapshot = $snapshot['product'] ?? [];
        $packageSnapshot = $snapshot['package'] ?? [];
        $pricingSnapshot = $snapshot['pricing'] ?? [];
        $billingInterval = $pricingSnapshot['billing_interval'] ?? ($snapshot['subscription']['billing_type'] ?? null);
    @endphp

    {{-- Printable Action Bar (Hidden when printed) --}}
    <div class="no-print" style="position:sticky;top:0;z-index:9999;background:#050B0D;color:#fff;padding:12px 24px;box-shadow:0 4px 15px rgba(0,0,0,0.25);display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <div style="display:flex;gap:14px;align-items:center">
            <span style="font-weight:900;color:#0C86ED;font-size:14pt">Notify</span>
            <span style="background:#1e293b;padding:3px 10px;border-radius:6px;font-size:10pt" class="ltr">{{ $contractNumber }}</span>
            <span style="font-size:9.5pt;color:#94a3b8">
                @if(isset($contract) && $contract->isIssued())
                    <span style="color:#10b981;font-weight:700">● معتمد ورسمي</span>
                @elseif(isset($contract) && $contract->isVoided())
                    <span style="color:#ef4444;font-weight:700">{{ __('notify.contracts.void') }}</span>
                @else
                    <span style="color:#f59e0b;font-weight:700">● مسودة تشغيلية للمراجعة القانونية</span>
                @endif
            </span>
        </div>
        <div style="display:flex;gap:10px;align-items:center">
            <button onclick="window.print()" style="background:#0C86ED;color:#fff;border:none;padding:8px 18px;border-radius:8px;cursor:pointer;font-weight:700;font-family:inherit;display:inline-flex;align-items:center;gap:6px">
                🖨 طباعة / حفظ كملف PDF
            </button>
            @if(isset($contract))
                <a href="{{ route('contracts.download', $contract->id) }}" style="background:#334155;color:#fff;text-decoration:none;padding:8px 14px;border-radius:8px;font-size:10pt;font-weight:600">
                    تحميل المستند HTML
                </a>
                <a href="{{ route('clients.show', $contract->client_id) }}" style="background:transparent;color:#94a3b8;text-decoration:none;padding:8px 12px;border-radius:8px;font-size:10pt">
                    ← العودة لملف العميل
                </a>
            @else
                <button onclick="window.history.back()" style="background:#334155;color:#fff;border:none;padding:8px 14px;border-radius:8px;cursor:pointer;font-family:inherit">
                    إغلاق
                </button>
            @endif
        </div>
    </div>

    <div class="page-container">
    {{-- PAGE 1: COVER PAGE --}}
    <div class="page">
        <div class="cover-page">
            <div class="cover-header">
                <div>
                    <h1 class="brand-title">Notify</h1>
                    <div class="brand-subtitle">منظومة الاتصالات وإدارة العمليات الذكية</div>
                </div>
                <div>
                    <div class="contract-badge">{{ $contractNumber }}</div>
                </div>
            </div>

            <div class="cover-body">
                <div class="legal-notice">
                    ⚠️ <strong>تنبيه إداري:</strong> هذه المسودة التشغيلية المعتمدة لنظام <strong>Notify</strong> مجهزة ومسجلة للتوثيق والاعتماد الإداري والمراجعة القانونية في المملكة الأردنية الهاشمية.
                </div>

                <div class="cover-title">عقد تقديم خدمات وحلول برمجية سحابية</div>
                <p style="font-size:12pt;color:#32383A">
                    تم إبرام هذا العقد والاتفاق عليه في هذا اليوم الموفق <strong class="ltr">{{ $issuedDate }}</strong> بين كل من:
                </p>

                <div class="parties-grid">
                    <div class="party-card">
                        <h4>الطرف الأول (المزود)</h4>
                        <p><strong>الاسم التجاري:</strong> {{ $snapshot['provider']['name_ar'] }}</p>
                        <p><strong>المقر:</strong> {{ $snapshot['provider']['city'] }}، {{ $snapshot['provider']['country'] }}</p>
                        <p><strong>البريد الرسمي:</strong> <span class="ltr">{{ $snapshot['provider']['email'] }}</span></p>
                        <p><strong>الصفة:</strong> مزود التراخيص البرمجية وحلول نقاط البيع والمتاجر</p>
                    </div>

                    <div class="party-card">
                        <h4>الطرف الثاني (العميل)</h4>
                        <p><strong>اسم النشاط:</strong> {{ $snapshot['client']['business_name'] }}</p>
                        <p><strong>المفوض بالتوقيع:</strong> {{ $snapshot['client']['contact_person'] ?: 'المفوض القانوني' }}</p>
                        <p><strong>المنطقة:</strong> {{ $snapshot['client']['city_area'] }}</p>
                        <p><strong>الهاتف المعتمد:</strong> <span class="ltr">{{ $snapshot['client']['phone'] }}</span></p>
                    </div>
                </div>

                <div style="margin-top:25px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:16px">
                    <h4 style="margin:0 0 8px 0;color:#0369a1;font-size:11pt">ملخص الخدمات والباقة المعتمدة في العقد:</h4>
                    @if(!empty($productSnapshot['name_ar']) || !empty($packageSnapshot['name_ar']))
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;font-size:10.5pt">
                            <div><strong>المنتج / النظام:</strong> {{ $productSnapshot['name_ar'] ?? '—' }} @if(!empty($productSnapshot['name_en'])) ({{ $productSnapshot['name_en'] }}) @endif</div>
                            <div><strong>الباقة:</strong> {{ $packageSnapshot['name_ar'] ?? '—' }} @if(!empty($packageSnapshot['name_en'])) ({{ $packageSnapshot['name_en'] }}) @endif</div>
                            <div><strong>دورية الفوترة:</strong> <span class="ltr">{{ $billingInterval ?: '—' }}</span></div>
                            @if(($snapshot['subscription']['payment_terms'] ?? 'full') === 'installments')
                                <div><strong>طريقة السداد:</strong> {{ $snapshot['subscription']['installments_count'] }} أقساط ضمن التزام سنوي واحد</div>
                            @endif
                            <div><strong>القيمة المعتمدة:</strong> <span class="ltr">{{ number_format($snapshot['financial']['grand_total'], 3) }}</span> {{ $snapshot['financial']['currency_ar'] }}</div>
                        </div>
                    @endif
                    <ul style="margin:0;padding-right:20px;font-size:10.5pt">
                        @foreach($snapshot['services'] as $svc)
                            <li>
                                <strong>{{ $svc['name_ar'] }} @if(!empty($svc['name_en'])) ({{ $svc['name_en'] }}) @endif</strong>
                                @if(($svc['price_contribution'] ?? null) !== null)
                                    — مساهمة القيمة: <span class="ltr">{{ number_format($svc['price_contribution'], 3) }}</span> د.أ
                                @endif
                                @if(!empty($svc['notes'])) — {{ $svc['notes'] }} @endif
                            </li>
                        @endforeach
                        @if(empty($snapshot['services']))
                            <li>الباقة المخصصة للمنشأة — إجمالي القيمة: <span class="ltr">{{ number_format($snapshot['financial']['grand_total'], 3) }}</span> د.أ</li>
                        @endif
                    </ul>
                </div>
            </div>

            <div style="text-align:center;color:#64748b;font-size:9.5pt;border-top:1px solid #e2e8f0;padding-top:10px">
                جميع الحقوق محفوظة لمنظومة Notify © {{ now()->year }} — وثيقة إلكترونية رسمية غير قابلة للتحوير
            </div>
        </div>
    </div>

    {{-- PAGE 2+: THE 22 MAIN CONTRACT CLAUSES --}}
    <div class="page">
        <h2 style="color:#0C86ED;text-align:center;margin-top:0">بنود وشروط اتفاقية تقديم الخدمة (22 بنداً)</h2>

        {{-- البند 1 --}}
        <div class="clause-item">
            <div class="section-heading">1. موضوع العقد</div>
            يمنح الطرف الأول بموجب هذا العقد الطرف الثاني ترخيصاً سحابياً غير حصري وغير قابل للتنازل لاستخدام منظومة <strong>Notify</strong> والحلول الرقمية المختارة وفق المواصفات والخصائص المحددة في هذا العقد وملاحقه التشغيلية.
        </div>

        {{-- البند 2 --}}
        <div class="clause-item">
            <div class="section-heading">2. تفاصيل الخدمة والباقة المختارة</div>
            @if(!empty($productSnapshot['name_ar']) || !empty($packageSnapshot['name_ar']))
                المنتج / النظام: <strong>{{ $productSnapshot['name_ar'] ?? '—' }}</strong>، الباقة: <strong>{{ $packageSnapshot['name_ar'] ?? '—' }}</strong>، دورية الفوترة: <strong class="ltr">{{ $billingInterval ?: '—' }}</strong>.<br>
            @endif
            تشمل الخدمات المشمولة بالترخيص كلاً من:
            @foreach($snapshot['services'] as $s) {{ $s['name_ar'] }}، @endforeach
            وذلك وفق حدود الاستخدام القياسية ومستوى الأداء التقني المعلن لمنظومة Notify.
        </div>

        {{-- البند 3 --}}
        <div class="clause-item">
            <div class="section-heading">3. مدة الاشتراك وسريان العقد</div>
            يبدأ سريان هذا العقد اعتباراً من تاريخ تفعيل الحساب في <span class="ltr">{{ $snapshot['subscription']['start_date'] }}</span>
            @if(!empty($snapshot['subscription']['current_period_end']))
                وتنتهي فترة الخدمة الحالية في <span class="ltr">{{ $snapshot['subscription']['current_period_end'] }}</span>
            @endif
            وفق دورية الفوترة المحددة أعلاه، ويتجدد تلقائياً لمدد مماثلة ما لم يخطر أحد الطرفين الآخر خطياً بعدم الرغبة في التجديد قبل 30 يوماً من انتهاء المدة.
        </div>

        {{-- البند 4 --}}
        <div class="clause-item">
            <div class="section-heading">4. قيمة الاشتراك والرسوم وجدول الدفعات</div>
            يلتزم الطرف الثاني بسداد الرسوم المستحقة بإجمالي قدره <strong class="ltr">{{ number_format($snapshot['financial']['grand_total'], 3) }} د.أ</strong> (شاملاً رسوم التأسيس والخصومات التشجيعية وضريبة المبيعات المقررة قانوناً)، ووفق مواعيد جدول الأقساط والاستحقاقات المبين في هذا العقد.
        </div>

        {{-- البند 5 --}}
        <div class="clause-item">
            <div class="section-heading">5. التفعيل والتسليم التشغيلي</div>
            يلتزم الطرف الأول بتهيئة الحساب وتسليم بيانات الدخول وتفعيل الأنظمة المطلوبة خلال مهلة العمل المتفق عليها من تاريخ استلام الدفعة الأولى وتوفر بيانات العميل الأساسية.
        </div>

        {{-- البند 6 --}}
        <div class="clause-item">
            <div class="section-heading">6. التزامات مزود الخدمة (Notify)</div>
            يلتزم الطرف الأول بضمان استقرار الخوادم السحابية بمعدل إتاحة لا يقل عن 99% شهرياً، وتوفير التحديثات الأمنية المستمرة والدعم الفني عن بُعد خلال ساعات العمل الرسمية.
        </div>

        {{-- البند 7 --}}
        <div class="clause-item">
            <div class="section-heading">7. التزامات العميل</div>
            يلتزم الطرف الثاني بالحفاظ على سرية بيانات تسجيل الدخول الخاصة بحسابه، وعدم استخدام المنظومة في أي أنشطة مخالفة للقوانين والأنظمة المعمول بها، وتعيين ضابط ارتباط مسؤول للتعامل مع الدعم الفني.
        </div>

        {{-- البند 8 --}}
        <div class="clause-item">
            <div class="section-heading">8. التعديلات والتحديثات البرمجية</div>
            يحق للطرف الأول إجراء تحسينات وتحديثات دورية على المنظومة والواجهات لرفع الكفاءة، شريطة عدم المساس بالوظائف الأساسية للخدمات المشترك بها أو الإضرار ببيانات العميل.
        </div>

        {{-- البند 9 --}}
        <div class="clause-item">
            <div class="section-heading">9. الملكية الفكرية والملفات التقنية</div>
            تظل كامل حقوق الملكية الفكرية، الأكواد البرمجية، العلامات التجارية، التصاميم، والبنية السحابية لمنظومة <strong>Notify</strong> ملكاً حصرياً ومطلقاً للطرف الأول، ولا يمنح هذا العقد العميل أي حق ملكية للمصدر البرمجي.
        </div>

        {{-- البند 10 --}}
        <div class="clause-item">
            <div class="section-heading">10. حظر إعادة البيع أو نقل الترخيص</div>
            يحظر على الطرف الثاني حظراً تاماً إعادة بيع الخدمة، أو تأجيرها، أو التنازل عنها، أو مشاركتها مع أطراف ثالثة أو فروع تجارية غير مسجلة في هذا العقد دون موافقة خطية مسبقة من الطرف الأول.
        </div>
    </div>

    {{-- PAGE 3: CLAUSES 11 TO 22 --}}
    <div class="page">
        {{-- البند 11 --}}
        <div class="clause-item">
            <div class="section-heading">11. حدود صلاحية مندوب المبيعات والوسطاء</div>
            لا يحق لأي مندوب مبيعات أو شريك وسيط تقديم أي وعود شفهية أو خصومات أو ميزات فنية غير منصوص عليها صراحة في هذا العقد، وتعتبر التواقيع والنماذج المعتمدة من إدارة Notify المركزية وحدها الملزمة.
        </div>

        {{-- البند 12 --}}
        <div class="clause-item">
            <div class="section-heading">12. التأخير الناتج عن العميل</div>
            في حال تأخر العميل عن تزويد الطرف الأول بالبيانات والصور وقوائم الأصناف والأسعار المطلوبة للإطلاق، لا يُعد ذلك إخلالاً من المزود ولا يترتب عليه تأجيل موعد استحقاق الدفعات أو الأقساط.
        </div>

        {{-- البند 13 --}}
        <div class="clause-item">
            <div class="section-heading">13. الإلغاء والاسترداد</div>
            نظراً لطبيعة الخدمات البرمجية والتجهيزات الرقمية الفورية، فإن المبالغ المدفوعة ورسوم التأسيس غير قابلة للاسترداد بعد تفعيل الحساب أو تسليم الرابط. وفي حال طلب الإلغاء المبكر تظل الأقساط المستحقة نافذة.
        </div>

        {{-- البند 14 --}}
        <div class="clause-item">
            <div class="section-heading">14. إيقاف أو تعليق الخدمة</div>
            يحق للطرف الأول تعليق الخدمة مؤقتاً في حال تأخر الطرف الثاني عن سداد أي دفعة مستحقة لأكثر من 7 أيام عمل من تاريخ الاستحقاق، مع إعادة التفعيل فور تسوية المستحقات المالية.
        </div>

        {{-- البند 15 --}}
        <div class="clause-item">
            <div class="section-heading">15. حدود المسؤولية</div>
            تنحصر مسؤولية الطرف الأول في تقديم الدعم الفني وإصلاح الأعطال البرمجية للمنظومة، ولا يتحمل أي مسؤولية عن أي خسائر تجارية غير مباشرة أو انقطاعات ناتجة عن مزودي شبكة الإنترنت أو أجهزة العميل المحلية.
        </div>

        {{-- البند 16 --}}
        <div class="clause-item">
            <div class="section-heading">16. خدمات الطرف الثالث وتكاملاتها</div>
            في حال استخدام خدمات خارجية مثل بوابات الدفع أو رسائل SMS، تخضع تلك الخدمات لسياسات وشروط وأسعار المزودين الخارجيين دون أدنى مسؤولية مباشرة على الطرف الأول بشأن تسوياتها المالية.
        </div>

        {{-- البند 17 --}}
        <div class="clause-item">
            <div class="section-heading">17. سرية البيانات وأمن المعلومات</div>
            يلتزم الطرفان بالمحافظة التامة على سرية البيانات التشغيلية والتجارية، وتتعهد Notify بتطبيق معايير أمن وحماية البيانات وعزلها وعدم إفشائها لأي جهة إلا بأمر قضائي رسمي.
        </div>

        {{-- البند 18 --}}
        <div class="clause-item">
            <div class="section-heading">18. المحتوى والمواد المقدمة من العميل</div>
            يتحمل العميل وحده المسؤولية القانونية الكاملة عن صحة ومطابقة الشعارات والمنتجات والأسعار والمواد المرفوعة عبر حسابه للقوانين السائدة وحقوق الملكية الفكرية للغير.
        </div>

        {{-- البند 19 --}}
        <div class="clause-item">
            <div class="section-heading">19. عدم الضمان التجاري أو تحقيق حجم مبيعات محدد</div>
            توفر Notify حلولاً تقنية وأدوات تمكينية فقط، ولا تضمن تحقيق نسب مبيعات محددة أو أرباح تجارية، حيث يعتمد ذلك كلياً على جودة منتجات العميل وخططه التسويقية.
        </div>

        {{-- البند 20 --}}
        <div class="clause-item">
            <div class="section-heading">20. الإشعارات والمراسلات الرسمية</div>
            تعتبر العناوين والبريد الإلكتروني وأرقام الهواتف المثبتة في مقدمة هذا العقد هي العناوين المعتمدة لكافة الإخطارات والمطالبات والمراسلات الرسمية بين الطرفين.
        </div>

        {{-- البند 21 --}}
        <div class="clause-item">
            <div class="section-heading">21. القانون الواجب التطبيق وحل النزاعات</div>
            يخضع هذا العقد ويفسر وفقاً للقوانين والأنظمة المعمول بها في المملكة الأردنية الهاشمية، وتختص محاكم عمان (قصر العدل) بنظر أي نزاع قد ينشأ عن تنفيذ هذا العقد أو تفسيره.
        </div>

        {{-- البند 22 --}}
        <div class="clause-item">
            <div class="section-heading">22. أحكام عامة ونسخ العقد</div>
            حُرر هذا العقد من نسختين أصليتين متطابقتين، بيد كل طرف نسخة للعمل بموجبها عند الاقتضاء، ويشكل هذا العقد بكافة بنوده وملاحقه الثلاثة كامل الاتفاق النهائي الملزم للطرفين.
        </div>
    </div>

    {{-- PAGE 4: SIGNATURES & FINANCIAL SCHEDULE --}}
    <div class="page">
        <h3 style="color:#0C86ED;border-bottom:2px solid #0C86ED;padding-bottom:6px">جدول الأقساط والدفعات المالية المعتمدة</h3>
        @if(($snapshot['subscription']['payment_terms'] ?? 'full') === 'installments')
            <p style="font-size:10pt;color:#334155">
                إجمالي الالتزام السنوي: <strong class="ltr">{{ number_format($snapshot['financial']['grand_total'], 3) }} {{ $snapshot['financial']['currency_ar'] }}</strong>.
                يوضح الجدول مواعيد التحصيل فقط ولا يغير مدة الاشتراك السنوية أو قيمته الإجمالية.
            </p>
        @endif
        <table>
            <thead>
                <tr>
                    <th>القسط / الدفعة</th>
                    <th>تاريخ الاستحقاق</th>
                    <th>المبلغ الأساسي</th>
                    <th>رسوم التأسيس</th>
                    <th>الخصم</th>
                    <th>الضريبة (16%)</th>
                    <th>المبلغ الصافي المستحق</th>
                </tr>
            </thead>
            <tbody>
                @forelse($snapshot['schedules'] as $sch)
                    <tr>
                        <td>الدفعة رقم {{ $sch['sequence'] }}</td>
                        <td class="ltr">{{ $sch['due_date'] }}</td>
                        <td>{{ number_format($sch['subtotal'], 3) }} د.أ</td>
                        <td>{{ number_format($sch['setup_fee_amount'], 3) }} د.أ</td>
                        <td>{{ number_format($sch['discount_amount'], 3) }} د.أ</td>
                        <td>{{ number_format($sch['tax_amount'], 3) }} د.أ</td>
                        <td><strong>{{ number_format($sch['amount_due'], 3) }} د.أ</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center">دفعة سنوية واحدة بقيمة {{ number_format($snapshot['financial']['grand_total'], 3) }} د.أ</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- SIGNATURES --}}
        <div class="signatures-area">
            <div class="sig-box">
                <h4>توقيع الطرف الأول (المزود - Notify)</h4>
                <p><strong>الاسم:</strong> المفوض بإدارة العمليات</p>
                <p><strong>الصفة:</strong> إدارة منظومة Notify الرسمية</p>
                <p><strong>التاريخ:</strong> <span class="ltr">{{ $issuedDate }}</span></p>
                <div class="sig-line">التوقيع والخاتم الرسمي: ____________________</div>
            </div>

            <div class="sig-box">
                <h4>توقيع الطرف الثاني (العميل المشترك)</h4>
                <p><strong>المنشأة:</strong> {{ $snapshot['client']['business_name'] }}</p>
                <p><strong>اسم المفوض:</strong> {{ $snapshot['client']['contact_person'] ?: 'المفوض بالتوقيع' }}</p>
                <p><strong>التاريخ:</strong> <span class="ltr">{{ $issuedDate }}</span></p>
                <div class="sig-line">التوقيع وخاتم المنشأة: ____________________</div>
            </div>
        </div>

        {{-- WITNESS SIGNATURE --}}
        <div class="sig-box" style="margin-top:16px">
            <h4>شاهد إثبات وتوثيق التعاقد</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                <div>
                    <p><strong>اسم الشاهد:</strong> ____________________________</p>
                    <p><strong>الرقم الوطني / الهوية:</strong> ______________________</p>
                </div>
                <div>
                    <p><strong>التوقيع:</strong> ____________________________</p>
                    <p><strong>التاريخ:</strong> <span class="ltr">{{ $issuedDate }}</span></p>
                </div>
            </div>
        </div>
    </div>

    {{-- PAGE 5: THE 3 APPENDICES --}}
    <div class="page">
        <h2 style="color:#0C86ED;text-align:center;margin-top:0">الملاحق التشغيلية الإلزامية (3 ملاحق)</h2>

        {{-- APPENDIX 1 --}}
        <div class="appendix-card">
            <h3 style="color:#0369a1;margin-top:0">الملحق رقم (1): تفاصيل التفعيل والتسليم الفني للخدمة</h3>
            <p style="font-size:10pt;color:#334155">
                يوثق هذا الملحق نطاق التسليم التقني، ويتضمن ربط النطاق السحابي، تهيئة نظام نقاط البيع (POS) أو المتجر الإلكتروني، تسليم لوحة التحكم ببيانات المرور المشفرة، وإجراء جلسة تدريبية تعريفية للعميل وموظفيه.
            </p>
            <div style="font-size:10pt;color:#050B0D">
                <strong>حالة التسليم الفني:</strong> جاهز للتنفيذ والتهيئة فور اعتماد وتوقيع العقد.
            </div>
        </div>

        {{-- APPENDIX 2 --}}
        <div class="appendix-card">
            <h3 style="color:#0369a1;margin-top:0">الملحق رقم (2): إقرار العميل باستلام وجاهزية الخدمة</h3>
            <p style="font-size:10pt;color:#334155">
                يقر الطرف الثاني (العميل) بأنه عاين الخدمات والنماذج والواجهات واطلع على مواصفات منظومة Notify وتأكد من مطابقتها لاحتياجات منشأته التشغيلية، وأنه استلم صلاحيات الدخول كاملة دون أي ملاحظات جوهرية.
            </p>
            <div style="margin-top:14px;border-top:1px dashed #cbd5e1;padding-top:8px;font-size:10pt">
                توقيع العميل بالاستلام: ______________________ التاريخ: <span class="ltr">{{ $issuedDate }}</span>
            </div>
        </div>

        {{-- APPENDIX 3 --}}
        <div class="appendix-card">
            <h3 style="color:#0369a1;margin-top:0">الملحق رقم (3): الاعتماد الداخلي للتسليم والتحصيل المالي</h3>
            <p style="font-size:10pt;color:#334155">
                خاص بفريق العمليات والمالية الداخلي لمنظومة Notify لتوثيق رقم إيصال التحصيل، طريقة الدفع المعتمدة (تحويل بنكي / CliQ / زين كاش / أورنج موني / نقدي)، وتأكيد مطابقة البيانات في السجل المالي المركزي.
            </p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;font-size:10pt">
                <div><strong>المسؤول المالي:</strong> إدارة الحسابات</div>
                <div><strong>رقم القيد الداخلي:</strong> <span class="ltr">{{ $contractNumber }}-REC</span></div>
            </div>
        </div>

        <div style="margin-top:30px;text-align:center;font-size:10pt;color:#64748b">
            نهاية وثيقة العقد المعتمدة — منظومة Notify © {{ now()->year }}
        </div>
    </div>
    </div> {{-- end page-container --}}

    @if($autoPrint ?? false)
    <script>
        window.addEventListener('DOMContentLoaded', function () {
            setTimeout(function () {
                window.print();
            }, 500);
        });
    </script>
    @endif

</body>
</html>
