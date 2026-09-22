@extends('layouts.app')

@section('content')
<div class="p5-wrap">
    <header class="p5-header">
        <div class="p5-header-main">
            <div class="p5-eyebrow">
                <a href="{{ route('administration.index') }}" class="p5-breadcrumb-link">الإدارة</a>
                <span class="p5-breadcrumb-sep">/</span>
                الفريق والصلاحيات
            </div>
            <h1 class="p5-title">إدارة الفريق الداخلي</h1>
            <p class="p5-subtitle">أعضاء الفريق الداخلي وأدوارهم في النظام.</p>
        </div>
        <div class="p5-header-actions">
            <a href="{{ route('administration.team.create') }}" class="p5-btn p5-btn-primary">
                إضافة عضو جديد
            </a>
        </div>
    </header>

    {{-- Role legend --}}
    <div class="admin-role-legend">
        <div class="admin-role-legend__item">
            <span class="admin-role-badge admin-role-badge--founder">مؤسس</span>
            <span>صلاحيات كاملة، لا يمكن تعديل الدور.</span>
        </div>
        <div class="admin-role-legend__item">
            <span class="admin-role-badge admin-role-badge--admin">مدير</span>
            <span>وصول إداري ومالي كامل.</span>
        </div>
        <div class="admin-role-legend__item">
            <span class="admin-role-badge admin-role-badge--staff">موظف</span>
            <span>عمليات يومية فقط. لا يرى المالية والإدارة.</span>
        </div>
    </div>

    {{-- Team Table --}}
    <div class="p5-card" style="margin-top:18px;">
        @if($teamMembers->isEmpty())
            <x-notify.empty-state title="لا يوجد أعضاء فريق" description="أضف أول عضو للفريق." />
        @else
            <table class="p5-table">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>البريد الإلكتروني</th>
                        <th>الدور</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($teamMembers as $member)
                    <tr class="{{ !$member->is_active ? 'p5-row--inactive' : '' }}">
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span class="notify-avatar" style="width:32px;height:32px;font-size:13px;">
                                    {{ mb_substr($member->name, 0, 1) }}
                                </span>
                                <div>
                                    <div style="font-weight:600;color:#0A1128;">{{ $member->name }}</div>
                                    @if($member->id === auth()->id())
                                        <div style="font-size:11px;color:#0055CC;">(أنت)</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td style="color:#4A5568;font-size:13px;">{{ $member->email }}</td>
                        <td>
                            @php
                                $roleBadgeClass = match($member->role) {
                                    'founder' => 'admin-role-badge--founder',
                                    'admin'   => 'admin-role-badge--admin',
                                    'staff'   => 'admin-role-badge--staff',
                                    default   => 'admin-role-badge--staff',
                                };
                                $roleLabel = match($member->role) {
                                    'founder' => 'مؤسس',
                                    'admin'   => 'مدير',
                                    'staff'   => 'موظف',
                                    default   => $member->role,
                                };
                            @endphp
                            <span class="admin-role-badge {{ $roleBadgeClass }}">{{ $roleLabel }}</span>
                        </td>
                        <td>
                            @if($member->is_active)
                                <span class="p5-badge p5-badge--success">فعال</span>
                            @else
                                <span class="p5-badge p5-badge--danger">غير فعال</span>
                            @endif
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <a href="{{ route('administration.team.edit', $member->id) }}"
                                   class="p5-btn p5-btn-ghost p5-btn-sm">تعديل</a>

                                @if($member->id !== auth()->id() && $member->role !== 'founder')
                                    @if($member->is_active)
                                        <form method="POST" action="{{ route('administration.team.deactivate', $member->id) }}"
                                              onsubmit="return confirm('إلغاء تفعيل حساب {{ $member->name }}؟')">
                                            @csrf
                                            <button type="submit" class="p5-btn p5-btn-ghost p5-btn-sm"
                                                    style="color:#DC2626;">إلغاء التفعيل</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<style>
.p5-breadcrumb-link { color:#0055CC; text-decoration:none; }
.p5-breadcrumb-link:hover { text-decoration:underline; }
.p5-breadcrumb-sep { margin:0 6px; color:#9CA3AF; }
.admin-role-legend {
    display:flex;gap:18px;flex-wrap:wrap;
    background:#F8FAFC;border:1px solid #E2E8F0;
    border-radius:10px;padding:14px 18px;
    margin-top:16px;align-items:center;
}
.admin-role-legend__item { display:flex;align-items:center;gap:8px;font-size:13px;color:#4A5568; }
.admin-role-badge {
    display:inline-flex;align-items:center;
    padding:2px 10px;border-radius:99px;
    font-size:11px;font-weight:700;line-height:1.6;
}
.admin-role-badge--founder { background:#FEF3C7;color:#92400E; }
.admin-role-badge--admin   { background:#E0E7FF;color:#3730A3; }
.admin-role-badge--staff   { background:#F0FDF4;color:#166534; }
.p5-table { width:100%;border-collapse:collapse; }
.p5-table th { text-align:start;padding:10px 14px;font-size:12px;font-weight:600;color:#6B7280;border-bottom:1px solid #E2E8F0;background:#F8FAFC; }
.p5-table td { padding:12px 14px;border-bottom:1px solid #F1F5F9;font-size:14px; }
.p5-table tr:last-child td { border-bottom:none; }
.p5-row--inactive { opacity:0.6; }
</style>
@endsection
