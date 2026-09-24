@extends('layouts.app')

{{--
    Team & Roles (§15, P13). Abilities per member are resolved in AdministrationController::teamView;
    Founder protections are enforced server-side and forbidden actions are simply not rendered.
--}}
@php
    use App\Support\FormState;
    $openSheet ??= null;
    // V1 roles: Founder and Staff only (P13.1).
    $roleVariant = ['founder' => 'info', 'staff' => 'neutral'];
@endphp

@section('content')
<div class="notify-admin" data-admin-page="team">
    <x-notify.page-header :title="__('notify.team.title')" :description="__('notify.team.subtitle')">
        <x-slot:actions>
            <x-notify.button variant="primary" icon="plus" data-open-sheet="team-add" aria-haspopup="dialog" data-page-action="add-member">{{ __('notify.team.add') }}</x-notify.button>
        </x-slot:actions>
    </x-notify.page-header>

    <x-notify.list data-team-list :label="__('notify.team.title')" :empty="$teamMembers->isEmpty()"
        :columns="[__('notify.team.member'), __('notify.team.role'), __('notify.team.contact'), __('notify.team.status'), ['label' => __('notify.common.actions'), 'hidden' => true, 'class' => 'is-actions']]">
        @foreach($teamMembers as $member)
            @php $can = $abilities[$member->id]; @endphp
            <tr @class(['is-muted' => ! $member->is_active]) data-team-member="{{ $member->id }}">
                <td class="notify-list__primary">
                    <span class="notify-admin-person">
                        <x-notify.user-avatar :user="$member" size="sm" />
                        <span class="notify-admin-person__text">
                            <span class="notify-list__title">{{ $member->name }} @if($can['self'])<small class="notify-admin-muted">({{ __('notify.team.you') }})</small>@endif</span>
                            @if($member->job_title)<span class="notify-list__sub">{{ $member->job_title }}</span>@endif
                        </span>
                    </span>
                </td>
                <td class="notify-list__end">
                    <x-notify.badge :variant="$roleVariant[$member->role] ?? 'neutral'">{{ __('notify.team.roles.'.$member->role) }}</x-notify.badge>
                </td>
                <td>
                    <span class="notify-admin-stack">
                        <span dir="ltr" class="notify-admin-ltr">{{ $member->email }}</span>
                        @if($member->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $member->phone) }}" dir="ltr" class="notify-admin-ltr">{{ $member->phone }}</a>@endif
                    </span>
                </td>
                <td>
                    @if($member->is_active)
                        <span class="notify-status notify-status--success">{{ __('notify.statuses.active') }}</span>
                    @else
                        <span class="notify-status notify-status--neutral">{{ __('notify.statuses.inactive') }}</span>
                    @endif
                </td>
                <td class="notify-list__actions">
                    <button type="button" class="notify-button notify-button--secondary notify-button--sm" data-open-sheet="team-edit-{{ $member->id }}" aria-haspopup="dialog" data-team-edit="{{ $member->id }}">
                        <x-notify.icon name="pencil" :size="16" /><span class="notify-button__label">{{ __('notify.actions.edit') }}</span>
                    </button>
                    @if($can['reset_password'] || $can['toggle_active'])
                        <x-notify.menu :label="__('notify.team.more_for', ['name' => $member->name])">
                            @if($can['reset_password'])
                                <button type="button" class="notify-menu__item" role="menuitem" data-open-sheet="team-password-{{ $member->id }}" aria-haspopup="dialog" data-team-reset="{{ $member->id }}">
                                    <x-notify.icon name="key-round" :size="18" /><span>{{ __('notify.team.reset_password') }}</span>
                                </button>
                            @endif
                            <x-slot:danger>
                                @if($can['toggle_active'])
                                    @if($member->is_active)
                                        <form method="POST" action="{{ route('administration.team.deactivate', $member->id) }}" data-confirm="{{ __('notify.team.deactivate_confirm', ['name' => $member->name]) }}" data-confirm-label="{{ __('notify.team.deactivate') }}">
                                            @csrf
                                            <button type="submit" class="notify-menu__item notify-menu__item--danger" role="menuitem" data-team-deactivate="{{ $member->id }}">
                                                <x-notify.icon name="power" :size="18" /><span>{{ __('notify.team.deactivate') }}</span>
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('administration.team.activate', $member->id) }}">
                                            @csrf
                                            <button type="submit" class="notify-menu__item" role="menuitem" data-team-activate="{{ $member->id }}">
                                                <x-notify.icon name="power" :size="18" /><span>{{ __('notify.team.activate') }}</span>
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </x-slot:danger>
                        </x-notify.menu>
                    @endif
                </td>
            </tr>
        @endforeach
        <x-slot:emptyState>
            <x-notify.empty-state :compact="true" icon="users" :title="__('notify.team.empty')" />
        </x-slot:emptyState>
    </x-notify.list>

    <section class="notify-admin-card notify-admin-card--quiet" aria-labelledby="team-roles-title">
        <h2 id="team-roles-title" class="notify-admin-card__title">{{ __('notify.team.roles_title') }}</h2>
        <dl class="notify-admin-roles">
            @foreach(\App\Models\User::activeInternalRoles() as $role)
                <div><dt>{{ __('notify.team.roles.'.$role) }}</dt><dd>{{ __('notify.team.role_descriptions.'.$role) }}</dd></div>
            @endforeach
        </dl>
    </section>
</div>

{{-- Add member: never a Founder (§2.1). --}}
@php $old = FormState::oldFor('team-add'); @endphp
@formscope('team-add')
<x-notify.sheet id="team-add" :title="__('notify.team.add')" :subtitle="__('notify.team.add_hint')" :action="route('administration.team.store')" :data-sheet-reopen="$openSheet === 'team-add' ?: null">
    <div class="notify-form-row">
        <x-notify.form-field :label="__('notify.team.name')" for="team-add-name" name="name" :required="true" class="notify-form-row__full">
            <input id="team-add-name" class="notify-input" name="name" value="{{ $old('name') }}" required maxlength="255" autocomplete="off" @invalid('name', 'team-add-name')>
        </x-notify.form-field>
        <x-notify.form-field :label="__('notify.team.email')" for="team-add-email" name="email" :required="true">
            <input id="team-add-email" class="notify-input" type="email" name="email" value="{{ $old('email') }}" required maxlength="255" autocomplete="off" dir="ltr" @invalid('email', 'team-add-email')>
        </x-notify.form-field>
        <x-notify.form-field :label="__('notify.team.phone')" for="team-add-phone" name="phone" :optional="true">
            <input id="team-add-phone" class="notify-input" type="tel" name="phone" value="{{ $old('phone') }}" maxlength="50" inputmode="tel" dir="ltr" @invalid('phone', 'team-add-phone')>
        </x-notify.form-field>
        <x-notify.form-field :label="__('notify.team.job_title')" for="team-add-job" name="job_title" :optional="true" class="notify-form-row__full">
            <input id="team-add-job" class="notify-input" name="job_title" value="{{ $old('job_title') }}" maxlength="120" @invalid('job_title', 'team-add-job')>
        </x-notify.form-field>
        {{-- Ordinary creation is Staff only (P13.1); a Founder can promote an existing member later. --}}
        <div class="notify-form-row__full notify-admin-readonly" data-add-role-staff>
            <span class="notify-admin-readonly__label">{{ __('notify.team.role') }}</span>
            <span><x-notify.badge variant="neutral">{{ __('notify.team.roles.staff') }}</x-notify.badge></span>
            <small class="notify-admin-muted">{{ __('notify.team.add_role_note') }}</small>
        </div>
        <x-notify.form-field :label="__('notify.team.password')" for="team-add-password" name="password" :required="true" :hint="__('notify.team.password_hint')">
            <input id="team-add-password" class="notify-input" type="password" name="password" required minlength="8" autocomplete="new-password" @invalid('password', 'team-add-password', 'default', true)>
        </x-notify.form-field>
        <x-notify.form-field :label="__('notify.team.password_confirm')" for="team-add-password-confirm" name="password_confirmation" :required="true">
            <input id="team-add-password-confirm" class="notify-input" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
        </x-notify.form-field>
    </div>
    <x-slot:footer>
        <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.actions.cancel') }}</button>
        <button type="submit" class="notify-button notify-button--primary">{{ __('notify.team.create_account') }}</button>
    </x-slot:footer>
</x-notify.sheet>
@endformscope

@foreach($teamMembers as $member)
    @php
        $can = $abilities[$member->id];
        $sheetId = 'team-edit-'.$member->id;
        $old = FormState::oldFor($sheetId);
    @endphp
    @formscope($sheetId)
    <x-notify.sheet :id="$sheetId" :title="__('notify.team.edit_member', ['name' => $member->name])" :action="route('administration.team.update', $member->id)" method="PUT" :data-sheet-reopen="$openSheet === $sheetId ?: null">
        <div class="notify-form-row">
            <x-notify.form-field :label="__('notify.team.name')" :for="$sheetId.'-name'" name="name" :required="true" class="notify-form-row__full">
                <input id="{{ $sheetId }}-name" class="notify-input" name="name" value="{{ $old('name', $member->name) }}" required maxlength="255" @invalid('name', $sheetId.'-name')>
            </x-notify.form-field>
            <x-notify.form-field :label="__('notify.team.email')" :for="$sheetId.'-email'" name="email" :required="true" :hint="__('notify.team.email_hint')">
                <input id="{{ $sheetId }}-email" class="notify-input" type="email" name="email" value="{{ $old('email', $member->email) }}" required maxlength="255" dir="ltr" @invalid('email', $sheetId.'-email', 'default', true)>
            </x-notify.form-field>
            <x-notify.form-field :label="__('notify.team.phone')" :for="$sheetId.'-phone'" name="phone" :optional="true">
                <input id="{{ $sheetId }}-phone" class="notify-input" type="tel" name="phone" value="{{ $old('phone', $member->phone) }}" maxlength="50" inputmode="tel" dir="ltr" @invalid('phone', $sheetId.'-phone')>
            </x-notify.form-field>
            <x-notify.form-field :label="__('notify.team.job_title')" :for="$sheetId.'-job'" name="job_title" :optional="true" class="notify-form-row__full">
                <input id="{{ $sheetId }}-job" class="notify-input" name="job_title" value="{{ $old('job_title', $member->job_title) }}" maxlength="120" @invalid('job_title', $sheetId.'-job')>
            </x-notify.form-field>

            @if($can['change_role'])
                <x-notify.form-field :label="__('notify.team.role')" name="role" :required="true" :group="true" class="notify-form-row__full">
                    <div class="notify-choices notify-choices--segmented">
                        @foreach($can['roles'] as $role)
                            <label class="notify-choice-chip">
                                <input type="radio" name="role" value="{{ $role }}" required @checked($old('role', $member->role) === $role)>
                                <span>{{ __('notify.team.roles.'.$role) }}</span>
                            </label>
                        @endforeach
                    </div>
                </x-notify.form-field>
            @else
                <input type="hidden" name="role" value="{{ $member->role }}">
                <div class="notify-form-row__full notify-admin-readonly" data-role-readonly>
                    <span class="notify-admin-readonly__label">{{ __('notify.team.role') }}</span>
                    <span><x-notify.badge :variant="$roleVariant[$member->role] ?? 'neutral'" icon="lock">{{ __('notify.team.roles.'.$member->role) }}</x-notify.badge></span>
                    <small class="notify-admin-muted">{{ $can['self'] ? __('notify.team.own_role_note') : __('notify.team.founder_note') }}</small>
                </div>
            @endif

            {{-- Active state: a hidden value keeps it unchanged where it may not be edited here. --}}
            @if($can['toggle_active'])
                <div class="notify-form-row__full">
                    <input type="hidden" name="is_active" value="0">
                    <label class="notify-choice-chip notify-admin-switch">
                        <input type="checkbox" name="is_active" value="1" @checked((bool) $old('is_active', $member->is_active))>
                        <span>{{ __('notify.team.active_account') }}</span>
                    </label>
                </div>
            @else
                <input type="hidden" name="is_active" value="{{ $member->is_active ? '1' : '0' }}">
            @endif
        </div>
        <x-slot:footer>
            <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.actions.cancel') }}</button>
            <button type="submit" class="notify-button notify-button--primary">{{ __('notify.actions.save') }}</button>
        </x-slot:footer>
    </x-notify.sheet>
    @endformscope

    @if($can['reset_password'])
        @php $passwordSheet = 'team-password-'.$member->id; @endphp
        @formscope($passwordSheet)
        <x-notify.sheet :id="$passwordSheet" size="sm" :title="__('notify.team.reset_password')" :subtitle="__('notify.team.reset_password_for', ['name' => $member->name])" :action="route('administration.team.reset-password', $member->id)">
            <x-notify.form-field :label="__('notify.team.new_password')" :for="$passwordSheet.'-new'" name="password" :required="true" :hint="__('notify.team.password_hint')">
                <input id="{{ $passwordSheet }}-new" class="notify-input" type="password" name="password" required minlength="8" autocomplete="new-password" @invalid('password', $passwordSheet.'-new', 'default', true)>
            </x-notify.form-field>
            <x-notify.form-field :label="__('notify.team.password_confirm')" :for="$passwordSheet.'-confirm'" name="password_confirmation" :required="true">
                <input id="{{ $passwordSheet }}-confirm" class="notify-input" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </x-notify.form-field>
            <x-slot:footer>
                <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.actions.cancel') }}</button>
                <button type="submit" class="notify-button notify-button--primary">{{ __('notify.team.reset_password') }}</button>
            </x-slot:footer>
        </x-notify.sheet>
        @endformscope
    @endif
@endforeach
@endsection
