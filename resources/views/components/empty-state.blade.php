@props([
    'icon' => '📦',
    'title' => 'لا توجد عناصر',
    'description' => 'لم يتم تسجيل أي بيانات في هذه القائمة حتى الآن.',
    'actionText' => null,
    'actionUrl' => null,
])

<div {{ $attributes->merge(['class' => 'empty-state', 'style' => 'padding:40px 20px;text-align:center;background:var(--nd-surface);border-radius:18px;border:1px dashed var(--nd-border)']) }}>
    <div style="font-size:44px;margin-bottom:12px;opacity:0.9">{{ $icon }}</div>
    <h3 style="margin:0 0 6px 0;font-size:17px;font-weight:800;color:var(--nd-ink)">{{ $title }}</h3>
    <p class="muted" style="margin:0 0 16px 0;font-size:14px;max-width:440px;margin-inline:auto;line-height:1.5">{{ $description }}</p>
    @if($actionText && $actionUrl)
        <a href="{{ $actionUrl }}" class="btn btn-primary touch-btn" style="display:inline-flex;align-items:center;gap:6px;padding:10px 22px;font-size:14px;font-weight:800">
            <span>＋</span>
            <span>{{ $actionText }}</span>
        </a>
    @endif
    {{ $slot }}
</div>
