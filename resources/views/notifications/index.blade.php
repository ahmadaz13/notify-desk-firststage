@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('dashboard') }}">{{ __('notify.navigation.today') }}</a> / {{ __('notify.notifications.badge_center') }}</div>
        <h1 class="page-title">{{ __('notify.notifications.title') }}</h1>
        <div class="muted" style="margin-top:4px">
            {{ __('notify.notifications.unread_count', ['count' => $unreadCount]) }}
        </div>
    </div>
    <div style="display:flex;gap:8px">
        @if($unreadCount > 0)
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button class="btn btn-soft" type="submit">{{ __('notify.notifications.mark_all_read') }}</button>
        </form>
        @endif
        <a class="btn btn-ghost" href="{{ route('dashboard') }}">{{ __('notify.notifications.back_to_today') }}</a>
    </div>
</div>

<div class="card" style="margin-bottom:16px;padding:12px 18px">
    <div style="display:flex;gap:12px;font-size:13px;font-weight:700">
        <a class="{{ ($filter ?? 'all') === 'all' ? 'btn btn-primary' : 'btn btn-ghost' }}" href="{{ route('notifications.index', ['filter' => 'all']) }}">
            {{ __('notify.notifications.all_tab') }}
        </a>
        <a class="{{ ($filter ?? '') === 'unread' ? 'btn btn-primary' : 'btn btn-ghost' }}" href="{{ route('notifications.index', ['filter' => 'unread']) }}">
            {{ __('notify.notifications.unread_tab', ['count' => $unreadCount]) }}
        </a>
    </div>
</div>

<div class="card list">
    @forelse($notifications as $item)
    <div class="list-row" style="{{ $item->read_at ? 'opacity:0.75' : 'background:rgba(204,251,241,0.15);border-radius:12px;padding:14px 12px;margin-bottom:6px' }}">
        <span class="badge {{ str_contains($item->type, 'overdue') ? 'red' : (str_contains($item->type, 'appointment') ? 'gold' : 'green') }}">
            @if(str_contains($item->type, 'appointment'))
                {{ __('notify.notifications.type_appointment') }}
            @elseif(str_contains($item->type, 'overdue'))
                {{ __('notify.notifications.type_overdue') }}
            @else
                {{ __('notify.notifications.type_payment') }}
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
                <a class="btn btn-soft" href="{{ $item->action_url }}">{{ __('notify.notifications.view_details') }}</a>
            @endif

            @if(!$item->read_at)
                <form method="POST" action="{{ route('notifications.read', $item->id) }}">
                    @csrf
                    <button class="btn btn-ghost" title="{{ __('notify.notifications.mark_as_read') }}" type="submit">{{ __('notify.notifications.mark_as_read') }}</button>
                </form>
            @endif
        </div>
    </div>
    @empty
    <div class="muted" style="padding:40px;text-align:center">
        {{ __('notify.notifications.empty') }}
    </div>
    @endforelse
</div>

{{ $notifications->links() }}
@endsection