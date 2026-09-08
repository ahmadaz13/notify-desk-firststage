@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">إدارة التعارضات والازدواجية</div>
        <h1 class="page-title">طلبات حل تعارض العملاء</h1>
    </div>
</div>

<div class="card" style="padding:0;overflow:hidden">
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;text-align:right">
            <thead>
                <tr style="background:#f8fafc;border-bottom:1px solid var(--nd-border);font-size:13px;color:var(--nd-muted)">
                    <th style="padding:16px">الشريك مقدم الطلب</th>
                    <th style="padding:16px">العميل المسجل حالياً</th>
                    <th style="padding:16px">رقم الهاتف</th>
                    <th style="padding:16px">الاسم المقترح</th>
                    <th style="padding:16px">تاريخ الطلب</th>
                    <th style="padding:16px;text-align:center">الإجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($conflicts as $conflict)
                    <tr style="border-bottom:1px solid var(--nd-border);font-size:14px">
                        <td style="padding:16px;font-weight:700">
                            {{ $conflict->partner?->company_name ?: 'شريك غير معروف' }}
                        </td>
                        <td style="padding:16px">
                            @if($conflict->client)
                                <a href="{{ route('clients.show', $conflict->client_id) }}" style="color:var(--nd-accent);font-weight:700">
                                    {{ $conflict->client->business_name }}
                                </a>
                                <div class="muted" style="font-size:11px">المنطقة: {{ $conflict->client->city_area }}</div>
                            @else
                                <span class="muted">عميل محذوف #{{ $conflict->client_id }}</span>
                            @endif
                        </td>
                        <td style="padding:16px;direction:ltr;text-align:right;font-family:monospace">
                            {{ $conflict->submitted_phone }}
                        </td>
                        <td style="padding:16px;font-weight:600">
                            {{ $conflict->submitted_name }}
                        </td>
                        <td style="padding:16px;color:var(--nd-muted)">
                            {{ $conflict->created_at->format('Y-m-d H:i') }}
                        </td>
                        <td style="padding:16px;text-align:center">
                            <a href="{{ route('conflicts.show', $conflict->id) }}" class="btn btn-primary" style="padding:7px 14px">
                                مراجعة والبت ←
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding:36px;text-align:center" class="muted">
                            🎉 لا توجد طلبات تعارض معلقة حالياً. جميع البيانات مسجلة دون ازدواجية.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($conflicts->hasPages())
    <div style="margin-top:16px">
        {{ $conflicts->links() }}
    </div>
@endif
@endsection
