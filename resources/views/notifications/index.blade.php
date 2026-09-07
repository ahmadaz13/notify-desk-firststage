@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('dashboard') }}">الرئيسية</a> / مركز الإشعارات</div>
        <h1 class="page-title">التنبيهات والإشعارات</h1>
        <div class="muted" style="margin-top:4px">
            لديك {{ $unreadCount }} تنبيه غير مقروء يحتاج انتباهك
        </div>
    </div>
    <div style="display:flex;gap:8px">
        @if($unreadCount > 0)
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button class="btn btn-soft" type="submit">✓ تحديد الكل كمقروء</button>
        </form>
        @endif
        <a class="btn btn-ghost" href="{{ route('dashboard') }}">العودة لليوم</a>
    </div>
</div>

<div class="card" style="margin-bottom:16px;padding:12px 18px">
    <div style="display:flex;gap:12px;font-size:13px;font-weight:700">
        <a class="{{ ($filter ?? 'all') === 'all' ? 'btn btn-primary' : 'btn btn-ghost' }}" href="{{ route('notifications.index', ['filter' => 'all']) }}">
            جميع التنبيهات
        </a>
        <a class="{{ ($filter ?? '') === 'unread' ? 'btn btn-primary' : 'btn btn-ghost' }}" href="{{ route('notifications.index', ['filter' => 'unread']) }}">
            غير المقروءة ({{ $unreadCount }})
        </a>
    </div>
</div>

<div class="card list">
    @forelse($notifications as $item)
    <div class="list-row" style="{{ $item->read_at ? 'opacity:0.75' : 'background:rgba(204,251,241,0.15);border-radius:12px;padding:14px 12px;margin-bottom:6px' }}">
        <span class="badge {{ str_contains($item->type, 'overdue') ? 'red' : (str_contains($item->type, 'appointment') ? 'gold' : 'green') }}">
            @if(str_contains($item->type, 'appointment'))
                موعد
            @elseif(str_contains($item->type, 'overdue'))
                متأخرات
            @else
                دفعة
            @endif
        </span>

        <div class="list-main">
            <div style="display:flex;align-items:center;gap:8px">
                <strong style="font-size:14px">{{ $item->title }}</strong>
                @if(!$item->read_at)
                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--nd-accent)"></span>
                @endif
            </div>
            <small style="color:var(--nd-ink);font-size:12px;margin:3px 0">{{ $item->message }}</small>
            <small class="muted">{{ $item->created_at }}</small>
        </div>

        <div style="display:flex;align-items:center;gap:8px">
            @if($item->action_url)
                <a class="btn btn-soft" href="{{ $item->action_url }}">عرض التفاصيل ←</a>
            @endif

            @if(!$item->read_at)
                <form method="POST" action="{{ route('notifications.read', $item->id) }}">
                    @csrf
                    <button class="btn btn-ghost" title="تعليم كمقروء" type="submit">✓ مقروء</button>
                </form>
            @endif
        </div>
    </div>
    @empty
    <div class="muted" style="padding:40px;text-align:center">
        لا توجد إشعارات في هذا التصنيف.
    </div>
    @endforelse
</div>

@if($notifications->hasPages())
<div class="pagination">
    @foreach($notifications->links()->elements[0] ?? [] as $page => $url)
        <a class="{{ $page == $notifications->currentPage() ? 'active' : '' }}" href="{{ $url }}">{{ $page }}</a>
    @endforeach
</div>
@endif
@endsection
