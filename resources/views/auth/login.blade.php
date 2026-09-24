<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('notify.auth.login_title') }}</title>
    @include('partials.pwa-head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="brand" style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
            <img src="{{ asset('brand/notify/notify-logo-light.svg') }}" alt="Notify" class="notify-brand__logo" width="36" height="36">
            <span>Notify</span>
        </div>
        <p class="eyebrow">{{ __('notify.auth.eyebrow') }}</p>
        <h1>{{ __('notify.auth.welcome_back') }}</h1>
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <div class="field">
                <label for="email">{{ __('notify.auth.email_or_phone') }}</label>
                <input id="email" type="text" name="email" value="{{ old('email') }}" placeholder="{{ __('notify.auth.email_placeholder') }}" autocomplete="username" required autofocus>
                @error('email')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="password">{{ __('notify.auth.password') }}</label>
                <input id="password" type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                @error('password')<span class="error">{{ $message }}</span>@enderror
            </div>
            <button class="btn btn-primary" type="submit">{{ __('notify.auth.sign_in_workspace') }}</button>
        </form>
        <p class="muted" style="text-align:center;margin:18px 0 0">
            {{ __('notify.auth.secure_system') }}
        </p>
    </div>
</div>
</body>
</html>
