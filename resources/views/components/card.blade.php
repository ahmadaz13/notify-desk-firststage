@props([
    'title' => null,
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'card', 'style' => 'background:var(--nd-surface);border:1px solid var(--nd-border);border-radius:20px;padding:20px;box-shadow:0 10px 28px rgba(5,11,13,0.04);']) }}>
    @if($title || $subtitle)
        <div style="margin-bottom:14px;border-bottom:1px solid var(--nd-border);padding-bottom:10px">
            @if($title)
                <h3 style="margin:0;font-size:18px;font-weight:800;color:var(--nd-ink)">{{ $title }}</h3>
            @endif
            @if($subtitle)
                <div class="muted" style="margin-top:4px;font-size:13px">{{ $subtitle }}</div>
            @endif
        </div>
    @endif
    {{ $slot }}
</div>
