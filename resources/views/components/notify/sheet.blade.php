@props([
    'id',
    'title',
    'subtitle' => null,
    'size' => 'md',
    'action' => null,
    'method' => 'POST',
    'errorBag' => 'default',
    'formAttributes' => [],
    'role' => 'dialog',
])

{{--
    Sheet (P12): the one modal surface for forms. Phone: near-full-screen from the bottom with a
    visible header, scrolling body and reachable footer. Tablet/desktop: centred, sized by `size`
    (sm 420 · md 560 · lg 720). Behaviour (open/close, Escape, focus trap, focus return) lives in
    resources/js/interactions.js.

    With `action`, body + footer are wrapped in the form and a hidden `_form` id is added, so a
    validation failure redirects back and this same sheet reopens with the old safe input and field
    errors. Opened by any `[data-open-sheet="{{ '{id}' }}"]` or by the P10 `?open=` deep link.
--}}
@php
    $bag = $errors->getBag($errorBag);
    $reopen = $bag->any() && old('_form') === $id;
    $verb = strtoupper($method);
    $formAttributeBag = new \Illuminate\View\ComponentAttributeBag($formAttributes);
@endphp

<div {{ $attributes->merge(['class' => 'notify-sheet notify-sheet--'.$size]) }} id="{{ $id }}" data-sheet @if($reopen) data-sheet-reopen @endif hidden>
    <div class="notify-sheet__backdrop" data-sheet-close aria-hidden="true"></div>
    <div class="notify-sheet__panel" role="{{ $role }}" aria-modal="true" aria-labelledby="{{ $id }}-title" @if($subtitle) aria-describedby="{{ $id }}-subtitle" @endif tabindex="-1">
        <header class="notify-sheet__header">
            <div class="notify-sheet__heading">
                <h2 class="notify-sheet__title" id="{{ $id }}-title">{{ $title }}</h2>
                @if($subtitle)
                    <p class="notify-sheet__subtitle" id="{{ $id }}-subtitle">{{ $subtitle }}</p>
                @endif
            </div>
            <button type="button" class="notify-icon-button notify-sheet__close" data-sheet-close aria-label="{{ __('notify.ui.close') }}">
                <x-notify.icon name="x" :size="20" />
            </button>
        </header>

        @if($action)
            <form method="{{ $verb === 'GET' ? 'GET' : 'POST' }}" action="{{ $action }}" {{ $formAttributeBag->merge(['class' => 'notify-sheet__form']) }}>
                @unless($verb === 'GET')
                    @csrf
                    @if(! in_array($verb, ['GET', 'POST'], true))
                        @method($verb)
                    @endif
                    <input type="hidden" name="_form" value="{{ $id }}">
                @endunless
                <div class="notify-sheet__body">
                    @if($reopen)
                        <div class="notify-form-alert" role="alert" data-sheet-errors>
                            <x-notify.icon name="alert-circle" :size="16" />
                            <div>
                                <p>{{ __('notify.ui.fix_errors') }}</p>
                                <ul>
                                    @foreach(array_slice(array_unique($bag->all()), 0, 3) as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif
                    {{ $slot }}
                </div>
                @isset($footer)
                    <footer {{ $footer->attributes->merge(['class' => 'notify-sheet__footer']) }}>{{ $footer }}</footer>
                @endisset
            </form>
        @else
            <div class="notify-sheet__body">{{ $slot }}</div>
            @isset($footer)
                <footer {{ $footer->attributes->merge(['class' => 'notify-sheet__footer']) }}>{{ $footer }}</footer>
            @endisset
        @endif
    </div>
</div>
