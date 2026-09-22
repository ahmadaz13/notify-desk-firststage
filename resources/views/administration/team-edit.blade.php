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
                تعديل عضو
            </div>
            <h1 class="p5-title">تعديل: {{ $member->name }}</h1>
        </div>
    </header>

    {{-- Edit Profile --}}
    <div class="p5-card" style="margin-top:18px;">
        <div style="font-size:13px;font-weight:700;color:#0A1128;margin-bottom:14px;">بيانات العضو</div>
        <form method="POST" action="{{ route('administration.team.update', $member->id) }}">
            @csrf
            @method('PUT')

            <div class="p5-form-grid">
                <div class="p5-field" style="grid-column:1/-1">
                    <label>الاسم الكامل *</label>
                    <input name="name" required class="p5-input" value="{{ old('name', $member->name) }}">
                    @error('name')<div class="p5-field-error">{{ $message }}</div>@enderror
                </div>

                <div class="p5-field">
                    <label>البريد الإلكتروني *</label>
                    <input name="email" type="email" required class="p5-input"
                           value="{{ old('email', $member->email) }}">
                    @error('email')<div class="p5-field-error">{{ $message }}</div>@enderror
                </div>

                <div class="p5-field">
                    <label>الدور *</label>
                    <select name="role" class="p5-select"
                        @if($member->role === 'founder') disabled @endif>
                        @if($member->role === 'founder')
                            <option value="founder" selected>مؤسس</option>
                            <input type="hidden" name="role" value="founder">
                        @else
                            <option value="staff"   {{ old('role', $member->role) === 'staff'   ? 'selected' : '' }}>موظف</option>
                            <option value="admin"   {{ old('role', $member->role) === 'admin'   ? 'selected' : '' }}>مدير</option>
                        @endif
                    </select>
                    @error('role')<div class="p5-field-error">{{ $message }}</div>@enderror
                </div>

                <div class="p5-field" style="display:flex;align-items:center;gap:10px;padding-top:20px;">
                    <label style="display:flex;gap:8px;align-items:center;cursor:pointer;">
                        <input type="checkbox" name="is_active"
                               @if($member->is_active) checked @endif
                               @if($member->id === auth()->id()) disabled @endif
                               style="accent-color:#0055CC;width:16px;height:16px;">
                        <span>الحساب فعال</span>
                    </label>
                    @if($member->id === auth()->id())
                        <span style="font-size:12px;color:#9CA3AF;">(لا يمكنك إلغاء تفعيل حسابك)</span>
                    @endif
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <a href="{{ route('administration.team') }}" class="p5-btn p5-btn-ghost">إلغاء</a>
                <button type="submit" class="p5-btn p5-btn-primary">حفظ التعديلات</button>
            </div>
        </form>
    </div>

    {{-- Reset Password --}}
    @if($member->role !== 'founder' || $member->id === auth()->id())
    <div class="p5-card" style="margin-top:16px;">
        <div style="font-size:13px;font-weight:700;color:#0A1128;margin-bottom:14px;">إعادة تعيين كلمة المرور</div>
        <form method="POST" action="{{ route('administration.team.reset-password', $member->id) }}">
            @csrf
            <div class="p5-form-grid">
                <div class="p5-field">
                    <label>كلمة المرور الجديدة *</label>
                    <input name="password" type="password" required class="p5-input"
                           placeholder="8 أحرف على الأقل" autocomplete="new-password">
                    @error('password')<div class="p5-field-error">{{ $message }}</div>@enderror
                </div>
                <div class="p5-field">
                    <label>تأكيد كلمة المرور *</label>
                    <input name="password_confirmation" type="password" required class="p5-input"
                           autocomplete="new-password">
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:14px;">
                <button type="submit" class="p5-btn p5-btn-soft p5-btn-sm"
                        style="color:#DC2626;"
                        onclick="return confirm('إعادة تعيين كلمة مرور {{ $member->name }}؟')">
                    إعادة تعيين كلمة المرور
                </button>
            </div>
        </form>
    </div>
    @endif
</div>

<style>
.p5-breadcrumb-link { color:#0055CC;text-decoration:none; }
.p5-breadcrumb-link:hover { text-decoration:underline; }
.p5-breadcrumb-sep { margin:0 6px;color:#9CA3AF; }
.p5-field-error { color:#DC2626;font-size:12px;margin-top:4px; }
</style>
@endsection
