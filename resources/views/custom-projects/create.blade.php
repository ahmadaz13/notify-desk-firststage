@extends('layouts.app')

@section('content')
<div class="p5-wrap" style="max-width:700px">
    <h1 class="p5-title">{{ __('custom_projects.create') }}</h1>
    <div class="p5-card" style="margin-block:16px">
        @include('custom-projects.form', ['project' => null])
    </div>
</div>
@endsection
