@extends('layouts.app')

{{--
    Systems (§6, §15, P13): the business System catalog. Stable codes are reference information only.
    requires_credentials drives the client credential card (§18.1); switching it off is refused while
    clients still have saved credentials for that System (CommercialCatalogController).
--}}
@php
    use App\Support\FormState;
    $isAr = app()->getLocale() === 'ar';
    $name = fn ($system) => $isAr ? $system->name_ar : ($system->name_en ?: $system->name_ar);
    $otherName = fn ($system) => $isAr ? $system->name_en : $system->name_ar;
    $description = fn ($system) => $isAr ? ($system->description_ar ?: $system->description_en) : ($system->description_en ?: $system->description_ar);
@endphp

@section('content')
<div class="notify-admin" data-admin-page="systems">
    <x-notify.page-header :title="__('notify.systems.title')" :description="__('notify.systems.subtitle')">
        <x-slot:actions>
            <x-notify.button variant="secondary" icon="plus" data-open-sheet="system-add" aria-haspopup="dialog" data-page-action="add-system">{{ __('notify.systems.add') }}</x-notify.button>
        </x-slot:actions>
    </x-notify.page-header>

    <x-notify.list data-systems-list :label="__('notify.systems.title')" :empty="$products->isEmpty()"
        :columns="[__('notify.systems.columns.system'), __('notify.systems.columns.credentials'), ['label' => __('notify.systems.columns.suggested_price'), 'class' => 'is-num'], __('notify.systems.columns.status'), ['label' => __('notify.common.actions'), 'hidden' => true, 'class' => 'is-actions']]">
        @foreach($products as $system)
            <tr @class(['is-muted' => ! $system->is_active || $system->archived_at]) data-system="{{ $system->code }}">
                <td class="notify-list__primary">
                    <span class="notify-list__title">{{ $name($system) }}</span>
                    @if(filled($otherName($system)) && $otherName($system) !== $name($system))
                        <span class="notify-list__sub">{{ $otherName($system) }}</span>
                    @endif
                    @if(filled($description($system)))
                        <span class="notify-list__sub notify-admin-clamp">{{ $description($system) }}</span>
                    @endif
                </td>
                <td data-label="{{ __('notify.systems.columns.credentials') }}">
                    @if($system->requires_credentials)
                        <span class="notify-status notify-status--info" data-requires-credentials="1"><x-notify.icon name="key-round" :size="14" />{{ __('notify.systems.credentials_required') }}</span>
                    @else
                        <span class="notify-status notify-status--neutral" data-requires-credentials="0">{{ __('notify.systems.credentials_not_needed') }}</span>
                    @endif
                </td>
                <td data-label="{{ __('notify.systems.columns.suggested_price') }}" class="is-num">@if($system->default_monthly_price_minor !== null || $system->default_annual_price_minor !== null)
                        <span class="notify-admin-stack">
                            @if($system->default_monthly_price_minor !== null)<span>{{ __('notify.systems.per_month') }} <x-notify.money :minor="$system->default_monthly_price_minor" /></span>@endif
                            @if($system->default_annual_price_minor !== null)<span>{{ __('notify.systems.per_year') }} <x-notify.money :minor="$system->default_annual_price_minor" /></span>@endif
                        </span>
                    @endif</td>
                <td class="notify-list__end">
                    @if($system->archived_at)
                        <span class="notify-status notify-status--neutral">{{ __('notify.systems.archived_status') }}</span>
                    @elseif($system->is_active)
                        <span class="notify-status notify-status--success">{{ __('notify.systems.active') }}</span>
                    @else
                        <span class="notify-status notify-status--neutral">{{ __('notify.systems.inactive') }}</span>
                    @endif
                </td>
                <td class="notify-list__actions">
                    <button type="button" class="notify-button notify-button--secondary notify-button--sm" data-open-sheet="system-edit-{{ $system->id }}" aria-haspopup="dialog" data-system-edit="{{ $system->code }}">
                        <x-notify.icon name="pencil" :size="16" /><span class="notify-button__label">{{ __('notify.actions.edit') }}</span>
                    </button>
                    @unless($system->archived_at)
                        <x-notify.menu :label="__('notify.systems.more_for', ['name' => $name($system)])">
                            <x-slot:danger>
                                <form method="POST" action="{{ route('commercial-catalog.products.archive', $system) }}" data-confirm="{{ __('notify.systems.archive_confirm', ['name' => $name($system)]) }}" data-confirm-label="{{ __('notify.systems.archive') }}">
                                    @csrf
                                    <button type="submit" class="notify-menu__item notify-menu__item--danger" role="menuitem" data-system-archive="{{ $system->code }}">
                                        <x-notify.icon name="x" :size="18" /><span>{{ __('notify.systems.archive') }}</span>
                                    </button>
                                </form>
                            </x-slot:danger>
                        </x-notify.menu>
                    @endunless
                </td>
            </tr>
        @endforeach
        <x-slot:emptyState>
            <x-notify.empty-state :compact="true" icon="package" :title="__('notify.systems.empty')" />
        </x-slot:emptyState>
    </x-notify.list>
</div>

@php $old = FormState::oldFor('system-add'); @endphp
@formscope('system-add')
<x-notify.sheet id="system-add" :title="__('notify.systems.add')" :subtitle="__('notify.systems.add_hint')" :action="route('commercial-catalog.products.store')">
    @include('commercial-catalog.partials.system-fields', ['prefix' => 'system-add', 'system' => null, 'old' => $old, 'storedCredentials' => 0])
    <x-slot:footer>
        <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.actions.cancel') }}</button>
        <button type="submit" class="notify-button notify-button--primary">{{ __('notify.systems.add') }}</button>
    </x-slot:footer>
</x-notify.sheet>
@endformscope

@foreach($products as $system)
    @php
        $sheetId = 'system-edit-'.$system->id;
        $old = FormState::oldFor($sheetId);
    @endphp
    @formscope($sheetId)
    <x-notify.sheet :id="$sheetId" :title="__('notify.systems.edit_named', ['name' => $name($system)])" :action="route('commercial-catalog.products.update', $system)" method="PATCH">
        @include('commercial-catalog.partials.system-fields', ['prefix' => $sheetId, 'system' => $system, 'old' => $old, 'storedCredentials' => $credentialCounts[$system->id] ?? 0])
        <x-slot:footer>
            <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.actions.cancel') }}</button>
            <button type="submit" class="notify-button notify-button--primary">{{ __('notify.actions.save') }}</button>
        </x-slot:footer>
    </x-notify.sheet>
    @endformscope
@endforeach
@endsection
