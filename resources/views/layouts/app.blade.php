<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'NotifyDesk Pilot Edition' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script defer src="{{ asset('js/alpine.min.js') }}"></script>
</head>
<body>
<div class="app-shell">
    <header class="topbar">
        <a class="brand" href="{{ route('dashboard') }}">Notify<span>Desk</span></a>
        @auth
        @php
            $unreadNotificationsCount = \Illuminate\Support\Facades\DB::table('notifications')
                ->where('user_id', auth()->id())
                ->whereNull('read_at')
                ->count();
        @endphp
        <nav class="nav-links">
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">اليوم</a>
            <a class="{{ request()->routeIs('clients.index') || request()->routeIs('clients.show') ? 'active' : '' }}" href="{{ route('clients.index') }}">العملاء</a>
            <a class="{{ request()->routeIs('clients.import*') ? 'active' : '' }}" href="{{ route('clients.import') }}">استيراد CSV</a>
            <a href="{{ route('dashboard') }}#quick-add">إضافة سريعة</a>
            <a href="{{ route('dashboard') }}#money">المال</a>
            @if(auth()->user()->isAdmin())
                <a class="{{ request()->routeIs('conflicts.*') ? 'active' : '' }}" href="{{ route('conflicts.index') }}">التعارضات</a>
            @endif
            <a class="{{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}">المزيد</a>
        </nav>
        <div style="display:flex;align-items:center;gap:12px">
            <a href="{{ route('notifications.index') }}" title="الإشعارات والتنبيهات" style="position:relative;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:12px;background:#fff;border:1px solid var(--nd-border);color:var(--nd-ink);text-decoration:none">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                @if($unreadNotificationsCount > 0)
                    <span style="position:absolute;top:-4px;right:-4px;background:var(--nd-danger);color:#fff;font-size:10px;font-weight:800;border-radius:10px;padding:2px 5px;line-height:1">{{ $unreadNotificationsCount }}</span>
                @endif
            </a>
            <div class="user-chip"><span>{{ auth()->user()->name }}</span><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost" type="submit">خروج</button></form></div>
        </div>
        @endauth
    </header>
    <main class="content-wrap">
        @if(session('success'))<div class="flash">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="flash" style="background:#fff0f1;color:#c84c54">{{ $errors->first() ?? 'يرجى مراجعة البيانات المدخلة.' }}</div>@endif
        @yield('content')
    </main>
    @auth
    <nav class="bottom-nav">
        <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><b>⌂</b><span>اليوم</span></a>
        <a class="{{ request()->routeIs('clients.index') || request()->routeIs('clients.show') ? 'active' : '' }}" href="{{ route('clients.index') }}"><b>♙</b><span>العملاء</span></a>
        <a class="{{ request()->routeIs('clients.import*') ? 'active' : '' }}" href="{{ route('clients.import') }}"><b>⬇</b><span>استيراد</span></a>
        <a class="{{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}" style="position:relative">
            <b>🔔</b>
            <span>التنبيهات</span>
            @if($unreadNotificationsCount > 0)
                <span style="position:absolute;top:6px;right:calc(50% - 16px);background:var(--nd-danger);color:#fff;font-size:9px;font-weight:800;border-radius:8px;padding:1px 4px;line-height:1">{{ $unreadNotificationsCount }}</span>
            @endif
        </a>
        <a href="{{ route('dashboard') }}#quick-add"><b>＋</b><span>إضافة</span></a>
    </nav>
    @endauth
</div>
</body>
</html>
