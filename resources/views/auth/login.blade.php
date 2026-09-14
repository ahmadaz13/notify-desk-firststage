<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل الدخول · Notify</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="brand">Notify</div>
        <p class="eyebrow">منظومة إدارة المبيعات وعلاقات الشركاء</p>
        <h1>مرحباً بعودتك</h1>
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <div class="field">
                <label for="email">البريد الإلكتروني أو رقم الهاتف</label>
                <input id="email" type="text" name="email" value="{{ old('email') }}" placeholder="name@example.com أو 079XXXXXXX" autocomplete="username" required autofocus>
                @error('email')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="password">كلمة المرور</label>
                <input id="password" type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                @error('password')<span class="error">{{ $message }}</span>@enderror
            </div>
            <button class="btn btn-primary" type="submit">دخول إلى مساحة العمل</button>
        </form>
        <p class="muted" style="text-align:center;margin:18px 0 0">
            Notify · نظام آمن لإدارة العمليات والشركاء
        </p>
    </div>
</div>
</body>
</html>
