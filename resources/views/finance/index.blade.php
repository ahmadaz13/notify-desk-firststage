@extends('layouts.app')

@php
    $money = fn (int $minor) => ($minor < 0 ? '-' : '') . \App\Support\Money::fromMinorUnits(abs($minor))->format() . ' ' . __('notify.common.currency_jod');
@endphp

@section('content')
<div class="p4-wrap">
    {{-- Page Header & Consolidated Section Navigation --}}
    <header class="p4-header">
        <div class="p4-header-main" style="max-width: 100%;">
            <div class="p4-eyebrow">{{ __('notify.navigation.finance') }} / {{ __('notify.finance.title') }}</div>
            <h1 class="p4-title">{{ __('notify.finance.title') }}</h1>
            <p class="p4-subtitle">{{ __('notify.finance.subtitle') }}</p>

            {{-- 6 Consolidated Cockpit Sections Tabs --}}
            <nav class="notify-mode-tabs" aria-label="Finance Cockpit Sections" style="margin-top: 14px; overflow-x: auto; max-width: 100%; white-space: nowrap;">
                @foreach([
                    'overview' => __('notify.finance.sections.overview'),
                    'collections' => __('notify.finance.sections.collections'),
                    'expenses' => __('notify.finance.sections.expenses'),
                    'capital_assets' => __('notify.finance.sections.capital_assets'),
                    'reports' => __('notify.finance.sections.reports'),
                    'advanced' => __('notify.finance.sections.advanced'),
                ] as $secKey => $secLabel)
                    <a
                        href="{{ route('finance.index', ['section' => $secKey] + request()->except(['section', 'page'])) }}"
                        class="notify-mode-tab {{ $section === $secKey ? 'is-active' : '' }}"
                        data-finance-tab="{{ $secKey }}"
                    >
                        {{ $secLabel }}
                    </a>
                @endforeach
            </nav>
        </div>

        @if(in_array($section, ['overview', 'reports'], true))
            <div class="p4-header-actions" style="margin-top: 8px;">
                <form method="GET" action="{{ route('finance.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
                    <input type="hidden" name="section" value="{{ $section }}">
                    <div class="p4-field">
                        <label>الفترة</label>
                        <select name="range" class="p4-select" style="min-width:130px">
                            @foreach(['today' => 'اليوم', 'this_month' => 'هذا الشهر', 'previous_month' => 'الشهر السابق', 'this_year' => 'هذا العام', 'previous_year' => 'العام السابق', 'custom_date_range' => 'مخصص'] as $value => $label)
                                <option value="{{ $value }}" @selected($period->range === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="p4-field">
                        <label>من</label>
                        <input type="date" name="date_from" class="p4-input" value="{{ request('date_from', $period->start->toDateString()) }}">
                    </div>
                    <div class="p4-field">
                        <label>إلى</label>
                        <input type="date" name="date_to" class="p4-input" value="{{ request('date_to', $period->end->toDateString()) }}">
                    </div>
                    <div class="p4-field">
                        <label>المقارنة</label>
                        <select name="comparison" class="p4-select" style="min-width:130px">
                            @foreach(['none' => 'بدون مقارنة', 'previous_period' => 'الفترة السابقة', 'previous_year_same_period' => 'العام السابق'] as $value => $label)
                                <option value="{{ $value }}" @selected($period->comparisonMode === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="p4-btn p4-btn-primary" type="submit">تطبيق</button>
                </form>
            </div>
        @endif
    </header>

    {{-- Render Active Consolidated Section --}}
    @if($section === 'overview')
        @include('finance.partials.overview')
    @elseif($section === 'collections')
        @include('finance.partials.collections')
    @elseif($section === 'expenses')
        @include('finance.partials.expenses')
    @elseif($section === 'capital_assets')
        @include('finance.partials.capital-assets')
    @elseif($section === 'reports')
        @include('finance.partials.reports')
    @elseif($section === 'advanced')
        @include('finance.partials.advanced')
    @endif
</div>
@endsection
