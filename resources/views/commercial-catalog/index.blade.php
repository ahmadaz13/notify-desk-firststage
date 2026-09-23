@extends('layouts.app')

@section('content')
<div class="notify-page-head">
    <div><div class="eyebrow">{{ __('notify.navigation.administration') }}</div><h1 class="page-title">{{ __('notify.systems.title') }}</h1></div>
</div>

<section class="p3-card">
    <h2>{{ __('notify.systems.add') }}</h2>
    <form method="POST" action="{{ route('commercial-catalog.products.store') }}" class="notify-form-grid">
        @csrf
        <div class="field"><label>{{ __('notify.systems.name_ar') }}</label><input name="name_ar" required class="touch-input" value="{{ old('name_ar') }}"></div>
        <div class="field"><label>{{ __('notify.systems.name_en') }}</label><input name="name_en" required class="touch-input" value="{{ old('name_en') }}"></div>
        <div class="field"><label>{{ __('notify.systems.default_monthly') }}</label><input name="default_monthly_price_jod" class="touch-input" inputmode="decimal" placeholder="0.000"></div>
        <div class="field"><label>{{ __('notify.systems.default_annual') }}</label><input name="default_annual_price_jod" class="touch-input" inputmode="decimal" placeholder="0.000"></div>
        <div><button class="p5-btn p5-btn-primary" type="submit">{{ __('notify.actions.save') }}</button></div>
    </form>
</section>

@foreach($products as $system)
<section class="p3-card" style="margin-top:12px">
    <form method="POST" action="{{ route('commercial-catalog.products.update', $system) }}" class="notify-form-grid">
        @csrf @method('PATCH')
        <div class="field"><label>{{ __('notify.systems.name_ar') }}</label><input name="name_ar" required class="touch-input" value="{{ $system->name_ar }}"></div>
        <div class="field"><label>{{ __('notify.systems.name_en') }}</label><input name="name_en" required class="touch-input" value="{{ $system->name_en }}"></div>
        <div class="field"><label>{{ __('notify.systems.default_monthly') }}</label><input name="default_monthly_price_jod" class="touch-input" value="{{ $system->default_monthly_price_minor === null ? '' : \App\Support\Money::fromMinorUnits($system->default_monthly_price_minor)->format() }}"></div>
        <div class="field"><label>{{ __('notify.systems.default_annual') }}</label><input name="default_annual_price_jod" class="touch-input" value="{{ $system->default_annual_price_minor === null ? '' : \App\Support\Money::fromMinorUnits($system->default_annual_price_minor)->format() }}"></div>
        <label><input type="checkbox" name="is_active" value="1" @checked($system->is_active)> {{ __('notify.systems.active') }}</label>
        <div><button class="p5-btn p5-btn-secondary" type="submit">{{ __('notify.actions.save') }}</button></div>
    </form>
</section>
@endforeach
@endsection
