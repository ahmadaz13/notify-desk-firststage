@extends('layouts.app')

@section('content')
<div class="p5-wrap">
    <header class="p5-header">
        <div class="p5-header-main">
            <a href="{{ route('clients.show', $project->client_id) }}">{{ $project->client->business_name }}</a>
            <h1 class="p5-title">{{ $project->name }}</h1>
            <p>{{ $project->statusLabel() }} · {{ $project->agreedValueFormatted() }} {{ __('notify.common.currency_jod') }}</p>
        </div>
        @can('manage_custom_projects')
        <a href="{{ route('custom-projects.edit', $project) }}" class="p5-btn p5-btn-ghost">{{ __('custom_projects.edit') }}</a>
        @endcan
    </header>

    <div class="p5-card" style="margin-block:16px">
        <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
            <div><dt>{{ __('custom_projects.start_date') }}</dt><dd>{{ $project->start_date?->toDateString() ?? '—' }}</dd></div>
            <div><dt>{{ __('custom_projects.target_date') }}</dt><dd>{{ $project->target_completion_date?->toDateString() ?? '—' }}</dd></div>
            <div><dt>{{ __('custom_projects.created_by') }}</dt><dd>{{ $project->creator?->name ?? '—' }}</dd></div>
        </dl>
        @if($project->notes)<p style="white-space:pre-line">{{ $project->notes }}</p>@endif
        <a href="{{ route('clients.show', $project->client_id) }}">{{ __('custom_projects.back_client') }}</a>
    </div>

    <section class="p5-card" style="margin-block:16px" aria-label="{{ __('custom_projects.invoice_title') }}">
        <h2>{{ __('custom_projects.invoice_title') }}</h2>
        <p>{{ __('custom_projects.invoice_note') }}</p>
        @forelse($project->invoices as $invoice)
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-block:8px">
                <span>{{ __('custom_projects.invoice_number') }} {{ $invoice->invoice_number }} · {{ __('custom_projects.invoice_status.'.$invoice->status) }}</span>
                <span>{{ $invoice->totalJod() }} {{ __('notify.common.currency_jod') }}</span>
            </div>
        @empty
            <p>{{ __('custom_projects.no_invoices') }}</p>
        @endforelse
        @if(\App\Support\Permissions::allows(auth()->user(), \App\Support\Permissions::MANAGE_INVOICES) && !$project->archived_at && !$project->isCancelled())
            @if($errors->any())
                <div role="alert" class="p5-field-error">
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <form method="POST" action="{{ route('clients.one-time-invoices.store', $project->client_id) }}" class="p5-form-grid" style="margin-block:16px">
                @csrf
                <input type="hidden" name="custom_project_id" value="{{ $project->id }}">
                <input type="hidden" name="lines[0][line_type]" value="one_time_service">
                <div class="p5-field"><label for="invoice-issue">{{ __('custom_projects.issue_date') }}</label><input id="invoice-issue" type="date" name="issue_date" required value="{{ old('issue_date', now('Asia/Amman')->toDateString()) }}" class="p5-input"></div>
                <div class="p5-field"><label for="invoice-due">{{ __('custom_projects.due_date') }}</label><input id="invoice-due" type="date" name="due_date" required value="{{ old('due_date', now('Asia/Amman')->toDateString()) }}" class="p5-input"></div>
                <div class="p5-field"><label for="invoice-description">{{ __('custom_projects.description') }}</label><input id="invoice-description" name="lines[0][description]" required value="{{ old('lines.0.description', $project->name) }}" class="p5-input"></div>
                <div class="p5-field"><label for="invoice-quantity">{{ __('custom_projects.quantity') }}</label><input id="invoice-quantity" type="number" min="1" max="999" name="lines[0][quantity]" required value="{{ old('lines.0.quantity', 1) }}" class="p5-input"></div>
                <div class="p5-field"><label for="invoice-price">{{ __('custom_projects.unit_price') }}</label><input id="invoice-price" name="lines[0][unit_price_jod]" inputmode="decimal" required value="{{ old('lines.0.unit_price_jod', $project->agreedValueFormatted()) }}" class="p5-input"></div>
                <div><button type="submit" class="p5-btn p5-btn-primary">{{ __('custom_projects.issue_invoice') }}</button></div>
            </form>
        @endif
    </section>

    @can('manage_custom_projects')
    <section class="p5-card" style="margin-block:16px">
        <h2>{{ __('custom_projects.status_label') }}</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @foreach(\App\Models\CustomProject::STATUSES as $state)
                @if($state !== $project->status)
                    <form method="POST" action="{{ route('custom-projects.update', $project) }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="name" value="{{ $project->name }}">
                        <input type="hidden" name="agreed_value_jod" value="{{ $project->agreedValueFormatted() }}">
                        <input type="hidden" name="start_date" value="{{ $project->start_date?->toDateString() }}">
                        <input type="hidden" name="target_completion_date" value="{{ $project->target_completion_date?->toDateString() }}">
                        <input type="hidden" name="notes" value="{{ $project->notes }}">
                        <input type="hidden" name="status" value="{{ $state }}">
                        <button class="p5-btn p5-btn-ghost" type="submit">{{ __('custom_projects.status.'.$state) }}</button>
                    </form>
                @endif
            @endforeach
        </div>
        @if(!$project->archived_at && $project->isCancelled())
            <form method="POST" action="{{ route('custom-projects.archive', $project) }}" onsubmit="return confirm(@js(__('custom_projects.archive_confirm')))" style="margin-block:12px">
                @csrf
                <button type="submit" class="p5-btn p5-btn-ghost">{{ __('custom_projects.archive') }}</button>
            </form>
        @endif
    </section>
    @endcan
</div>
@endsection
