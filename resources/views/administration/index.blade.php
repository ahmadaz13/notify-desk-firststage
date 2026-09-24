@extends('layouts.app')

{{-- Administration index (§15, P13): orientation only. Every destination is its own canonical page. --}}
@section('content')
<div class="notify-admin notify-admin--narrow" data-admin-page="index">
    <x-notify.page-header :title="__('notify.administration.title')" :description="__('notify.administration.subtitle')" />

    <nav class="notify-admin-index" aria-label="{{ __('notify.administration.title') }}">
        @foreach($destinations as $destination)
            <a class="notify-admin-index__item" href="{{ $destination['href'] }}" data-admin-destination="{{ $destination['key'] }}">
                <span class="notify-admin-index__icon"><x-notify.icon :name="$destination['icon']" :size="20" /></span>
                <span class="notify-admin-index__text">
                    <span class="notify-admin-index__title">{{ $destination['label'] }}</span>
                    <span class="notify-admin-index__desc">{{ __('notify.administration.descriptions.'.$destination['key']) }}</span>
                </span>
                @if(filled($facts[$destination['key']] ?? null))
                    <span class="notify-admin-index__fact">{{ $facts[$destination['key']] }}</span>
                @endif
                <x-notify.icon name="chevron-left" :size="18" class="notify-admin-index__chevron" />
            </a>
        @endforeach
    </nav>
</div>
@endsection
