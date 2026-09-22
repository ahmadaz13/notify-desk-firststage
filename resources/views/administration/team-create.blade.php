@extends('layouts.app')

@section('content')
<div class="p5-wrap" style="max-width:600px;">
    <header class="p5-header">
        <div class="p5-header-main">
            <div class="p5-eyebrow">
                <a href="{{ route('administration.index') }}" class="p5-breadcrumb-link">الإدارة</a>
                <span class="p5-breadcrumb-sep">/</span>
                <a href="{{ route('administration.team') }}" class="p5-breadcrumb-link">الفريق</a>
                <span class="p5-breadcrumb-sep">/</span>
                إضافة عضو
            </div>
            <h1 class="p5-title">إضافة عضو فريق جديد</h1>
        </div>
    </header>

    <div class="p5-card" style="margin-top:18px;">
        <form method="POST" action="{{ route('administration.team.store') }}">
            @csrf

            <div class="p5-form-grid">
                <div class="p5-field" style="grid-column:1/-1">
                    <label>الاسم الكامل *</label>
                    <input name="name" required class="p5-input" value="{{ old('name') }}"
                           placeholder="أحمد محمد" autofocus>
                    @error('name')<div class="p5-field-error">{{ $message }}</div>@enderror
                </div>

                <div class="p5-field">
                    <label>البريد الإلكتروني *</label>
                    <input name="email" type="email" required class="p5-input" value="{{ old('email') }}"
                           placeholder="ahmad@example.com">
                    @error('email')<div class="p5-field-error">{{ $message }}</div>@enderror
                </div>

                <div class="p5-field">
                    <label>الدور *</label>
                    <select name="role" class="p5-select">
                        <option value="staff" {{ old('role') === 'staff' ? 'selected' : '' }}>موظف – عمليات يومية فقط</option>
                        <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>مدير – وصول مالي وإداري كامل</option>
                    </select>
                    @error('role')<div class="p5-field-error">{{ $message }}</div>@enderror
                </div>

                <div class="p5-field">
                    <label>كلمة المرور *</label>
                    <input name="password" type="password" required class="p5-input"
                           placeholder="8 أحرف على الأقل" autocomplete="new-password">
                    @error('password')<div class="p5-field-error">{{ $message }}</div>@enderror
                </div>

                <div class="p5-field">
                    <label>تأكيد كلمة المرور *</label>
                    <input name="password_confirmation" type="password" required class="p5-input"
                           placeholder="أعد كتابة كلمة المرور" autocomplete="new-password">
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <a href="{{ route('administration.team') }}" class="p5-btn p5-btn-ghost">إلغاء</a>
                <button type="submit" class="p5-btn p5-btn-primary">إنشاء الحساب</button>
            </div>
        </form>
    </div>
</div>

<style>
.p5-breadcrumb-link { color:#0055CC;text-decoration:none; }
.p5-breadcrumb-link:hover { text-decoration:underline; }
.p5-breadcrumb-sep { margin:0 6px;color:#9CA3AF; }
.p5-field-error { color:#DC2626;font-size:12px;margin-top:4px; }
</style>
@endsection
