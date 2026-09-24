@extends('layouts.app')

@section('content')
<div class="notify-admin notify-admin--narrow" data-admin-page="custom-project-edit">
    <a class="notify-admin-back" href="{{ route('custom-projects.show', $project) }}"><x-notify.icon name="chevron-right" :size="16" class="notify-admin-back__icon" />{{ $project->name }}</a>
    <x-notify.page-header :title="__('custom_projects.edit')" />
    <section class="notify-admin-card">
        @include('custom-projects.form', ['project' => $project, 'client' => null])
    </section>
</div>
@endsection
