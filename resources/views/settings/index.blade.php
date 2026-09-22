@extends('layouts.app')

@section('content')
<div class="notify-page-head">
    <div><div class="eyebrow">{{ __('notify.navigation.administration') }}</div><h1 class="page-title">{{ __('notify.navigation.settings') }}</h1></div>
    <a class="notify-button notify-button--primary" href="{{ route('settings.export') }}">{{ __('notify.settings.export_financial_excel') }}</a>
</div>
<section class="p3-card">
    <form method="GET" action="{{ route('settings.index') }}">
        <label for="activity-type">{{ __('notify.settings.activity_type') }}</label>
        <select id="activity-type" name="type" onchange="this.form.submit()">
            <option value="all">{{ __('notify.common.all') }}</option>
            @foreach($activityTypes as $type)<option value="{{ $type }}" @selected($selectedType === $type)>{{ $type }}</option>@endforeach
        </select>
    </form>
    <div class="table-responsive" style="margin-top:12px">
        <table class="p5-table"><thead><tr><th>{{ __('notify.common.date') }}</th><th>{{ __('notify.common.user') }}</th><th>{{ __('notify.common.client') }}</th><th>{{ __('notify.common.description') }}</th></tr></thead>
        <tbody>@foreach($activityLogs as $log)<tr><td>{{ $log->created_at }}</td><td>{{ $log->user_name }}</td><td>{{ $log->client_name }}</td><td>{{ $log->description }}</td></tr>@endforeach</tbody></table>
    </div>
</section>
@endsection
