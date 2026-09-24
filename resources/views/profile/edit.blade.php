@extends('layouts.app')

{{--
    Work profile (§17, P13): identity + a small personal summary (CompletedWorkService, next 3
    appointments), then work details and password. Not HR: no salary, IDs, documents or attendance.
--}}
@php
    use App\Support\FormState;
    use App\Support\OperationalTime;
    $old = FormState::oldFor('profile-details');
@endphp

@section('content')
<div class="notify-admin notify-admin--narrow" data-admin-page="profile">
    <x-notify.page-header :title="__('notify.profile.title')" :description="__('notify.profile.subtitle')" />

    <section class="notify-admin-card notify-profile-identity" aria-label="{{ __('notify.profile.identity') }}" data-profile-identity>
        <x-notify.user-avatar :user="$user" size="lg" />
        <div class="notify-profile-identity__text">
            <p class="notify-profile-identity__name">{{ $user->name }}</p>
            @if($user->job_title)<p class="notify-admin-muted">{{ $user->job_title }}</p>@endif
            <x-notify.badge variant="info" icon="lock" data-profile-role>{{ __('notify.team.roles.'.$user->role) }}</x-notify.badge>
        </div>
    </section>

    <section class="notify-admin-card" aria-labelledby="profile-summary-title" data-profile-summary>
        <h2 id="profile-summary-title" class="notify-admin-card__title">{{ __('notify.profile.summary_title') }}</h2>
        <div class="notify-profile-summary">
            <div class="notify-profile-summary__stat" data-completed-today="{{ $completedToday }}">
                <span class="notify-profile-summary__value" dir="ltr">{{ $completedToday }}</span>
                <span class="notify-profile-summary__label">{{ __('notify.profile.completed_today') }}</span>
            </div>
            <div class="notify-profile-summary__next">
                <h3 class="notify-admin-subtitle">{{ __('notify.profile.next_appointments') }}</h3>
                @forelse($nextAppointments as $appointment)
                    @php $at = \Carbon\Carbon::parse($appointment->appointment_date->toDateString().' '.($appointment->appointment_time ?: '00:00'), OperationalTime::TIMEZONE); @endphp
                    <a class="notify-profile-appointment" href="{{ route('clients.show', $appointment->client_id) }}" data-profile-appointment="{{ $appointment->id }}">
                        <span class="notify-profile-appointment__when">{{ $appointment->appointment_time ? OperationalTime::dayAndClock($at, $now) : OperationalTime::day($at, $now) }}</span>
                        <span class="notify-profile-appointment__client">{{ $appointment->client?->business_name }}</span>
                        <span class="notify-admin-muted notify-admin-small">{{ \App\Support\AppointmentTypes::label($appointment->appointment_type) }}</span>
                    </a>
                @empty
                    <p class="notify-admin-muted notify-admin-small" data-profile-no-appointments>{{ __('notify.profile.no_appointments') }}</p>
                @endforelse
            </div>
        </div>
    </section>

    @formscope('profile-details')
    <section class="notify-admin-card" aria-labelledby="profile-details-title">
        <h2 id="profile-details-title" class="notify-admin-card__title">{{ __('notify.profile.work_details') }}</h2>
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="notify-admin-form" data-profile-form>
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="profile-details">

            <x-notify.form-field :label="__('notify.profile.photo')" for="profile-avatar" name="avatar" :optional="true" :hint="__('notify.profile.photo_hint')">
                <div class="notify-profile-photo">
                    <x-notify.user-avatar :user="$user" size="md" />
                    <input id="profile-avatar" class="notify-input notify-admin-file" type="file" name="avatar" accept="image/png,image/jpeg,image/webp" @invalid('avatar', 'profile-avatar', 'default', true)>
                </div>
                @if($user->avatarUrl())
                    <label class="notify-check notify-admin-small">
                        <input type="checkbox" name="remove_avatar" value="1">
                        <span>{{ __('notify.profile.remove_photo') }}</span>
                    </label>
                @endif
            </x-notify.form-field>

            <div class="notify-form-row">
                <x-notify.form-field :label="__('notify.profile.name')" for="profile-name" name="name" :required="true" class="notify-form-row__full">
                    <input id="profile-name" class="notify-input" name="name" value="{{ $old('name', $user->name) }}" required maxlength="255" autocomplete="name" @invalid('name', 'profile-name')>
                </x-notify.form-field>
                <x-notify.form-field :label="__('notify.profile.job_title')" for="profile-job" name="job_title" :optional="true">
                    <input id="profile-job" class="notify-input" name="job_title" value="{{ $old('job_title', $user->job_title) }}" maxlength="120" autocomplete="organization-title" @invalid('job_title', 'profile-job')>
                </x-notify.form-field>
                <x-notify.form-field :label="__('notify.profile.phone')" for="profile-phone" name="phone" :optional="true">
                    <input id="profile-phone" class="notify-input" type="tel" name="phone" value="{{ $old('phone', $user->phone) }}" maxlength="50" inputmode="tel" autocomplete="tel" dir="ltr" @invalid('phone', 'profile-phone')>
                </x-notify.form-field>
            </div>

            <dl class="notify-admin-readonly-list">
                <div>
                    <dt>{{ __('notify.profile.email') }}</dt>
                    <dd><span dir="ltr" data-profile-email>{{ $user->email }}</span></dd>
                </div>
                <div>
                    <dt>{{ __('notify.profile.role') }}</dt>
                    <dd>{{ __('notify.team.roles.'.$user->role) }}</dd>
                </div>
            </dl>
            <p class="notify-admin-muted notify-admin-small">{{ __('notify.profile.managed_by_owner') }}</p>

            <div class="notify-form-actions">
                <button class="notify-button notify-button--primary" type="submit">{{ __('notify.actions.save') }}</button>
            </div>
        </form>
    </section>
    @endformscope

    @formscope('profile-password')
    <section class="notify-admin-card" id="password" aria-labelledby="profile-password-title">
        <h2 id="profile-password-title" class="notify-admin-card__title">{{ __('notify.profile.change_password') }}</h2>
        <form method="POST" action="{{ route('profile.password') }}" class="notify-admin-form" data-password-form>
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="profile-password">
            <x-notify.form-field :label="__('notify.profile.current_password')" for="profile-current-password" name="current_password" :required="true">
                <input id="profile-current-password" class="notify-input" type="password" name="current_password" autocomplete="current-password" required @invalid('current_password', 'profile-current-password')>
            </x-notify.form-field>
            <div class="notify-form-row">
                <x-notify.form-field :label="__('notify.profile.new_password')" for="profile-new-password" name="password" :required="true" :hint="__('notify.team.password_hint')">
                    <input id="profile-new-password" class="notify-input" type="password" name="password" autocomplete="new-password" minlength="8" required @invalid('password', 'profile-new-password', 'default', true)>
                </x-notify.form-field>
                <x-notify.form-field :label="__('notify.profile.confirm_password')" for="profile-confirm-password" name="password_confirmation" :required="true">
                    <input id="profile-confirm-password" class="notify-input" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
                </x-notify.form-field>
            </div>
            <div class="notify-form-actions">
                <button class="notify-button notify-button--primary" type="submit">{{ __('notify.profile.update_password') }}</button>
            </div>
        </form>
    </section>
    @endformscope
</div>
@endsection
