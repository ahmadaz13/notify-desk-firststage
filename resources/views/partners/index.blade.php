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

@if(session('new_delegate_link'))
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
                        <td colspan="7" style="padding:32px;text-align:center" class="muted">
                            لا يوجد شركاء مسجلين حالياً. قم بإضافة أول شريك لإنشاء رابط مندوب مخصص له.
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
