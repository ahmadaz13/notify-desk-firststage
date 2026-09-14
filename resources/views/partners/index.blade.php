@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">إدارة المنظومة التشاركية</div>
        <h1 class="page-title">قائمة الشركاء والوسطاء</h1>
    </div>
    <div>
        <a class="btn btn-primary touch-btn" href="{{ route('partners.create') }}">＋ إضافة شريك جديد</a>
    </div>
</div>

@php
    $cred = session('new_partner_credentials') ?? session('reset_partner_credentials');
@endphp

@if($cred)
    @php
        $allCredsText = "بيانات دخول الشريك (" . $cred['company_name'] . "):\n"
            . "رابط تسجيل الدخول: " . $cred['login_url'] . "\n"
            . "البريد الإلكتروني: " . $cred['email'] . "\n"
            . "كلمة المرور: " . $cred['password'] . "\n"
            . "رابط المندوب للعملاء: " . $cred['delegate_link'];
    @endphp
    <div class="card" style="background:#fefce8;border:1.5px solid #eab308;margin-bottom:24px;padding:20px;border-radius:16px" x-data="{ copiedAll: false, copiedPass: false }">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
            <div style="font-weight:800;color:#854d0e;font-size:16px">
                🔑 {{ session('reset_partner_credentials') ? 'تمت إعادة تعيين كلمة مرور الشريك بنجاح' : 'تم إنشاء حساب الشريك بنجاح - بيانات الدخول' }}
            </div>
            <span class="badge" style="background:#fef08a;color:#713f12">تظهر لمرة واحدة فقط</span>
        </div>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px;margin-bottom:14px;background:#fff;padding:14px;border-radius:12px;border:1px solid #fef08a">
            <div>
                <small class="muted">الشركة / الشريك:</small>
                <div style="font-weight:700">{{ $cred['company_name'] }}</div>
            </div>
            <div>
                <small class="muted">رابط تسجيل الدخول:</small>
                <div><a href="{{ $cred['login_url'] }}" style="color:var(--nd-accent);direction:ltr;font-family:monospace">{{ $cred['login_url'] }}</a></div>
            </div>
            <div>
                <small class="muted">البريد الإلكتروني:</small>
                <div style="font-family:monospace;direction:ltr">{{ $cred['email'] }}</div>
            </div>
            <div>
                <small class="muted">كلمة المرور:</small>
                <div style="display:flex;align-items:center;gap:8px">
                    <span style="font-family:monospace;font-weight:800;font-size:16px;color:#b45309;background:#fef3c7;padding:2px 8px;border-radius:6px">{{ $cred['password'] }}</span>
                    <button type="button" class="btn btn-ghost" style="padding:3px 8px;font-size:11px" @click="navigator.clipboard.writeText('{{ $cred['password'] }}'); copiedPass = true; setTimeout(() => copiedPass = false, 2000)">
                        <span x-show="!copiedPass">نسخ</span>
                        <span x-show="copiedPass" style="display:none;color:var(--nd-success)">✓</span>
                    </button>
                </div>
            </div>
            <div style="grid-column:1/-1">
                <small class="muted">رابط المندوب للعملاء:</small>
                <div style="font-family:monospace;direction:ltr;color:var(--nd-accent);word-break:break-all">{{ $cred['delegate_link'] }}</div>
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
            <small class="muted" style="color:#a16207">يرجى نسخ هذه البيانات وحفظها أو إرسالها للشريك الآن، فلن تظهر كلمة المرور مرة أخرى.</small>
            <button type="button" class="btn btn-primary touch-btn" @click="navigator.clipboard.writeText(`{{ $allCredsText }}`); copiedAll = true; setTimeout(() => copiedAll = false, 2500)" style="background:#ca8a04;border-color:#ca8a04">
                <span x-show="!copiedAll">📋 نسخ جميع بيانات الاعتماد دفعة واحدة</span>
                <span x-show="copiedAll" style="display:none">تم نسخ جميع البيانات! ✓</span>
            </button>
        </div>
    </div>
@elseif(session('new_delegate_link'))
    <div class="card" style="background:#e6f4ea;border:1px solid #34a853;margin-bottom:20px;padding:16px" x-data="{ copied: false }">
        <div style="font-weight:800;color:#137333;margin-bottom:6px">🔗 تم إنشاء رابط المندوب الخاص بالشريك:</div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <input type="text" readonly value="{{ session('new_delegate_link') }}" class="touch-input" style="flex:1;background:#fff;direction:ltr;font-family:monospace" id="new-link-input">
            <button type="button" class="btn btn-primary touch-btn" @click="navigator.clipboard.writeText('{{ session('new_delegate_link') }}'); copied = true; setTimeout(() => copied = false, 2500)">
                <span x-show="!copied">نسخ الرابط</span>
                <span x-show="copied" style="display:none">تم النسخ! ✓</span>
            </button>
        </div>
    </div>
@endif

<div class="card" style="padding:0;overflow:hidden">
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;text-align:right">
            <thead>
                <tr style="background:#f8fafc;border-bottom:1px solid var(--nd-border);font-size:13px;color:var(--nd-muted)">
                    <th style="padding:16px">اسم الشركة</th>
                    <th style="padding:16px">البريد الإلكتروني</th>
                    <th style="padding:16px">الهاتف</th>
                    <th style="padding:16px">نسبة الأرباح</th>
                    <th style="padding:16px">عدد العملاء</th>
                    <th style="padding:16px">رابط المندوب</th>
                    <th style="padding:16px;text-align:center">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($partners as $partner)
                    @php $delegateUrl = url('/p/' . $partner->public_uuid . '/client/create'); @endphp
                    <tr style="border-bottom:1px solid var(--nd-border);font-size:14px" x-data="{ copied: false }">
                        <td style="padding:16px;font-weight:700">{{ $partner->company_name }}</td>
                        <td style="padding:16px;direction:ltr;text-align:right">{{ $partner->email }}</td>
                        <td style="padding:16px">{{ $partner->phone ?: '—' }}</td>
                        <td style="padding:16px">
                            @if($partner->profit_share_percentage !== null)
                                <span class="badge green">{{ $partner->profit_share_percentage }}%</span>
                            @else
                                <span class="muted">غير محدد</span>
                            @endif
                        </td>
                        <td style="padding:16px;font-weight:700">{{ $partner->clients_count ?? $partner->clients()->count() }}</td>
                        <td style="padding:16px">
                            <div style="display:flex;align-items:center;gap:6px">
                                <a href="{{ $delegateUrl }}" target="_blank" style="color:var(--nd-accent);font-size:12px;text-decoration:underline;direction:ltr">
                                    /p/{{ substr($partner->public_uuid, 0, 8) }}...
                                </a>
                                <button type="button" class="btn btn-ghost" style="padding:5px 9px;font-size:11px" @click="navigator.clipboard.writeText('{{ $delegateUrl }}'); copied = true; setTimeout(() => copied = false, 2000)" title="نسخ رابط المندوب">
                                    <span x-show="!copied">نسخ</span>
                                    <span x-show="copied" style="display:none;color:var(--nd-success)">✓</span>
                                </button>
                            </div>
                        </td>
                        <td style="padding:16px;text-align:center">
                            <div style="display:inline-flex;gap:8px">
                                <a href="{{ route('partners.edit', $partner->id) }}" class="btn btn-soft" style="padding:6px 12px">تعديل</a>
                                <form method="POST" action="{{ route('partners.reset-password', $partner->id) }}" onsubmit="return confirm('هل أنت متأكد من إعادة تعيين كلمة المرور لهذا الشريك؟')">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost" style="padding:6px 10px;border-color:#f59e0b;color:#b45309" title="إعادة تعيين كلمة المرور">🔑 كلمة المرور</button>
                                </form>
                                <form method="POST" action="{{ route('partners.destroy', $partner->id) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذا الشريك؟ سيتم حذف حساب الدخول المرتبط به.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger" style="padding:6px 12px">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:48px 20px;text-align:center">
                            <div style="font-size:48px;margin-bottom:12px;opacity:0.85">🤝</div>
                            <h3 style="margin:0 0 8px 0;font-size:18px;color:var(--nd-ink)">لا يوجد شركاء بعد</h3>
                            <p class="muted" style="margin:0 0 20px 0;font-size:14px">ابدأ ببناء شبكة شركائك وتوليد روابط مناديب مخصصة لكل شريك لمتابعة عملاء الشركاء وعمولاتهم.</p>
                            <a href="{{ route('partners.create') }}" class="btn btn-primary touch-btn" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;font-size:14px;font-weight:700">
                                <span>+ إضافة شريك</span>
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($partners->hasPages())
    <div style="margin-top:16px">
        {{ $partners->links() }}
    </div>
@endif
@endsection
