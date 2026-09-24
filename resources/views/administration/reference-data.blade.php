@extends('layouts.app')

{{--
    Operational Reference Data (§15.1, P13): the small vocabularies used in client forms.
    Business types = client_category. Options are deactivated, never deleted: clients store the value,
    so historical values keep rendering.
--}}
@php
    use App\Services\ReferenceDataService;
    use App\Support\FormState;
    $isLabelled = fn (string $list) => in_array($list, ReferenceDataService::LABELLED_LISTS, true);
@endphp

@section('content')
<div class="notify-admin" data-admin-page="reference-data">
    <x-notify.page-header :title="__('notify.reference_data.title')" :description="__('notify.reference_data.subtitle')" />

    <nav class="notify-admin-jump" aria-label="{{ __('notify.reference_data.jump_label') }}">
        @foreach($lists as $listKey => $options)
            <a href="#list-{{ $listKey }}" class="notify-admin-jump__item">{{ __('notify.reference_data.lists.'.$listKey.'.title') }} <span class="notify-segments__count" dir="ltr">{{ $options->where('is_active', true)->count() }}</span></a>
        @endforeach
    </nav>

    @foreach($lists as $listKey => $options)
        <section class="notify-admin-card" id="list-{{ $listKey }}" aria-labelledby="list-{{ $listKey }}-title" data-reference-list="{{ $listKey }}">
            <div class="notify-admin-card__head">
                <div>
                    <h2 id="list-{{ $listKey }}-title" class="notify-admin-card__title">{{ __('notify.reference_data.lists.'.$listKey.'.title') }}</h2>
                    <p class="notify-admin-card__desc">{{ __('notify.reference_data.lists.'.$listKey.'.description') }}</p>
                </div>
                <button type="button" class="notify-button notify-button--secondary notify-button--sm" data-open-sheet="reference-add-{{ $listKey }}" aria-haspopup="dialog" data-reference-add="{{ $listKey }}">
                    <x-notify.icon name="plus" :size="16" /><span class="notify-button__label">{{ __('notify.reference_data.lists.'.$listKey.'.add') }}</span>
                </button>
            </div>

            <x-notify.list :label="__('notify.reference_data.lists.'.$listKey.'.title')" :empty="$options->isEmpty()"
                :columns="$isLabelled($listKey)
                    ? [__('notify.reference_data.columns.option'), __('notify.reference_data.columns.other_language'), __('notify.reference_data.columns.status'), ['label' => __('notify.common.actions'), 'hidden' => true, 'class' => 'is-actions']]
                    : [__('notify.reference_data.columns.suggestion'), __('notify.reference_data.columns.status'), ['label' => __('notify.common.actions'), 'hidden' => true, 'class' => 'is-actions']]">
                @foreach($options as $option)
                    <tr @class(['is-muted' => ! $option->is_active]) data-reference-option="{{ $option->id }}">
                        <td class="notify-list__primary"><span class="notify-list__title">{{ $option->label() }}</span></td>
                        @if($isLabelled($listKey))
                            <td data-label="{{ __('notify.reference_data.columns.other_language') }}">{{ app()->getLocale() === 'ar' ? $option->label_en : $option->label_ar }}</td>
                        @endif
                        <td class="notify-list__end">
                            @if($option->is_active)
                                <span class="notify-status notify-status--success">{{ __('notify.reference_data.active') }}</span>
                            @else
                                <span class="notify-status notify-status--neutral">{{ __('notify.reference_data.inactive') }}</span>
                            @endif
                        </td>
                        <td class="notify-list__actions">
                            <button type="button" class="notify-button notify-button--ghost notify-button--sm" data-open-sheet="reference-edit-{{ $option->id }}" aria-haspopup="dialog" aria-label="{{ __('notify.reference_data.edit_named', ['name' => $option->label()]) }}">
                                <x-notify.icon name="pencil" :size="16" /><span class="notify-button__label">{{ __('notify.actions.edit') }}</span>
                            </button>
                            <x-notify.menu :label="__('notify.reference_data.more_for', ['name' => $option->label()])">
                                <form method="POST" action="{{ route('administration.reference-data.active', $option) }}"
                                    @if($option->is_active) data-confirm="{{ __('notify.reference_data.deactivate_confirm', ['name' => $option->label()]) }}" data-confirm-label="{{ __('notify.reference_data.deactivate') }}" data-confirm-tone="neutral" @endif>
                                    @csrf
                                    <input type="hidden" name="active" value="{{ $option->is_active ? '0' : '1' }}">
                                    <button type="submit" class="notify-menu__item" role="menuitem" data-reference-toggle="{{ $option->id }}">
                                        <x-notify.icon name="power" :size="18" /><span>{{ $option->is_active ? __('notify.reference_data.deactivate') : __('notify.reference_data.activate') }}</span>
                                    </button>
                                </form>
                            </x-notify.menu>
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState>
                    <x-notify.empty-state :compact="true" icon="clipboard-list" :title="__('notify.reference_data.lists.'.$listKey.'.empty')" />
                </x-slot:emptyState>
            </x-notify.list>
        </section>
    @endforeach
</div>

{{-- Add sheets (one per list). --}}
@foreach($lists as $listKey => $options)
    @php
        $sheetId = 'reference-add-'.$listKey;
        $old = FormState::oldFor($sheetId);
    @endphp
    @formscope($sheetId)
    <x-notify.sheet :id="$sheetId" size="sm" :title="__('notify.reference_data.lists.'.$listKey.'.add')" :subtitle="__('notify.reference_data.lists.'.$listKey.'.add_hint')" :action="route('administration.reference-data.store')">
        <input type="hidden" name="list_key" value="{{ $listKey }}">
        @include('administration.partials.reference-option-fields', ['prefix' => $sheetId, 'listKey' => $listKey, 'option' => null, 'old' => $old])
        <x-slot:footer>
            <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.actions.cancel') }}</button>
            <button type="submit" class="notify-button notify-button--primary">{{ __('notify.reference_data.add') }}</button>
        </x-slot:footer>
    </x-notify.sheet>
    @endformscope
@endforeach

{{-- Edit sheets. --}}
@foreach($lists as $listKey => $options)
    @foreach($options as $option)
        @php
            $sheetId = 'reference-edit-'.$option->id;
            $old = FormState::oldFor($sheetId);
        @endphp
        @formscope($sheetId)
        <x-notify.sheet :id="$sheetId" size="sm" :title="__('notify.reference_data.edit_named', ['name' => $option->label()])" :action="route('administration.reference-data.update', $option)" method="PATCH">
            @include('administration.partials.reference-option-fields', ['prefix' => $sheetId, 'listKey' => $listKey, 'option' => $option, 'old' => $old])
            @if($isLabelled($listKey))
                <p class="notify-form-note notify-form-note--info">{{ __('notify.reference_data.value_kept_note') }}</p>
            @endif
            <x-slot:footer>
                <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.actions.cancel') }}</button>
                <button type="submit" class="notify-button notify-button--primary">{{ __('notify.actions.save') }}</button>
            </x-slot:footer>
        </x-notify.sheet>
        @endformscope
    @endforeach
@endforeach
@endsection
