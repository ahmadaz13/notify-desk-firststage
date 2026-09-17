@props([
    'variant' => 'primary', // primary, soft, ghost, danger
    'type' => 'button',
    'href' => null,
])

@php
    $baseClasses = 'btn touch-btn transition-all duration-150 inline-flex items-center justify-center gap-2 font-bold cursor-pointer rounded-[12px] px-4 py-2.5 min-h-[48px]';
    $variantClasses = match($variant) {
        'primary' => 'btn-primary bg-[#0C86ED] hover:bg-[#0a73cc] text-white',
        'soft' => 'btn-soft bg-[#eff6ff] text-[#0C86ED]',
        'ghost' => 'btn-ghost bg-white border border-[var(--nd-border)] text-[#050B0D]',
        'danger' => 'btn-danger bg-[#fef2f2] text-[#ef4444]',
        default => 'btn-primary bg-[#0C86ED] text-white',
    };
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => "$baseClasses $variantClasses"]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => "$baseClasses $variantClasses"]) }}>
        {{ $slot }}
    </button>
@endif
