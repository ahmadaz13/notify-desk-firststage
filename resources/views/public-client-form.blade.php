<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل عميل جديد · مندوب الشريك</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body {
            background: linear-gradient(135deg, #f0fdfa 0%, #f1f5f9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .delegate-card {
            width: min(520px, 100%);
            background: #ffffff;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 20px 45px rgba(15, 118, 110, 0.08);
            border: 1px solid rgba(148, 163, 184, 0.25);
        }
        .partner-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 12px;
            background: #ccfbf1;
            color: #0f766e;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
<div class="delegate-card">
    <div style="text-align:center;margin-bottom:24px">
        <div class="partner-badge">
            <span>🤝 مندوب شريك:</span>
            <strong>{{ $partner->company_name }}</strong>
        </div>
        <h1 style="font-size:24px;font-weight:800;margin:0 0 6px;color:var(--nd-ink)">تسجيل عميل جديد</h1>
        <p class="muted" style="margin:0;font-size:13px">أدخل بيانات العميل ليتم إضافته ومتابعته ضمن حساب الشريك</p>
    </div>

    @if(session('success'))
        <div class="flash" style="background:#dcfce7;color:#15803d;border:1px solid #86efac;border-radius:14px;padding:14px;margin-bottom:20px;font-weight:700">
            ✓ {{ session('success') }}
        </div>
    @endif

    @if(session('info'))
        <div class="flash" style="background:#e0f2fe;color:#0369a1;border:1px solid #7dd3fc;border-radius:14px;padding:14px;margin-bottom:20px;font-weight:700">
            ℹ {{ session('info') }}
        </div>
    @endif

    @if($errors->any())
        <div class="flash" style="background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;border-radius:14px;padding:14px;margin-bottom:20px">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('public.client.store', $uuid) }}">
        @csrf

        <div class="field" style="margin-bottom:16px">
            <label style="font-size:13px;font-weight:700;color:var(--nd-ink)">رقم الهاتف *</label>
            <input class="touch-input" type="tel" name="phone" required placeholder="079XXXXXXXX أو +962..." style="direction:ltr;text-align:right" autocomplete="tel">
        </div>

        <div class="field" style="margin-bottom:16px">
            <label style="font-size:13px;font-weight:700;color:var(--nd-ink)">اسم العميل / النشاط التجاري *</label>
            <input class="touch-input" type="text" name="name" required placeholder="مثال: صيدلية الأمل">
        </div>

        <div class="field" style="margin-bottom:16px">
            <label style="font-size:13px;font-weight:700;color:var(--nd-ink)">المنطقة / المدينة (اختياري)</label>
            <input class="touch-input" type="text" name="area" placeholder="مثال: عمان - الصويفية">
        </div>

        <div class="field" style="margin-bottom:24px">
            <label style="font-size:13px;font-weight:700;color:var(--nd-ink)">المصدر / قناة الاستقطاب (اختياري)</label>
            <input class="touch-input" type="text" name="source" placeholder="مثال: زيارة ميدانية، اتصال، ترشيح...">
        </div>

        <button type="submit" class="btn btn-primary touch-btn" style="width:100%;font-size:16px;min-height:50px">
            إرسال بيانات العميل
        </button>
    </form>
</div>
</body>
</html>
