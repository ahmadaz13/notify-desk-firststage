@extends('layouts.app')

@section('content')
<div class="notify-admin notify-admin--narrow" data-admin-page="custom-project-create">
    <a class="notify-admin-back" href="{{ route('custom-projects.index') }}"><x-notify.icon name="chevron-right" :size="16" class="notify-admin-back__icon" />{{ __('custom_projects.title') }}</a>
    <x-notify.page-header :title="__('custom_projects.create')" :description="__('custom_projects.form_intro')" />
    <section class="notify-admin-card">
        @include('custom-projects.form', ['project' => null])
    </section>
</div>
@endsection
