@extends('layouts.app')

@section('content')
<div class="notify-page-head">
    <div>
        <div class="eyebrow"><a href="{{ route('clients.index') }}">{{ __('notify.clients.title') }}</a> / {{ __('notify.clients.create_title') }}</div>
        <h1 class="page-title">{{ __('notify.clients.create_title') }}</h1>
    </div>
</div>

<section class="notify-form-shell">
    <form
        method="POST"
        action="{{ route('clients.store') }}"
        class="notify-prospect-form notify-client-form"
        x-init="$nextTick(() => { const field = $el.querySelector('[aria-invalid=true]'); if (field) { field.focus(); field.scrollIntoView({ block: 'center' }); } })"
    >
        @csrf

        @include('clients.partials.form-core', ['client' => null])

        <div class="notify-form-footer notify-form-footer--sticky">
            <x-notify.button :href="route('clients.index')" variant="ghost">{{ __('notify.actions.cancel') }}</x-notify.button>
            <x-notify.button type="submit" variant="primary" icon="plus">{{ __('notify.clients.create_prospect_action') }}</x-notify.button>
        </div>
    </form>
</section>
@endsection
