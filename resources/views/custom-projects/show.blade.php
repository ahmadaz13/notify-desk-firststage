@extends('layouts.app')

{{--
    Custom Project detail (§20a, P13). Invoicing requires a linked Client and goes through the existing
    one-time invoice path (clients.one-time-invoices.store → InvoiceService, financial.idempotency).
    It never touches subscriptions, MRR or ARR. Staff: read-only, no management or invoice controls.
--}}
@php
    use App\Support\FormState;
    use App\Support\Permissions;
    $user = auth()->user();
    $canManage = Permissions::allows($user, Permissions::MANAGE_CUSTOM_PROJECTS);
    $canInvoice = $canManage && Permissions::allows($user, Permissions::MANAGE_INVOICES);
    $invoiceable = $project->client_id && ! $project->archived_at && ! $project->isCancelled();
    $statusTone = ['planned' => 'neutral', 'active' => 'info', 'completed' => 'success', 'cancelled' => 'neutral'];
    $old = FormState::oldFor('project-invoice');
@endphp

@section('content')
<div class="notify-admin notify-admin--medium" data-admin-page="custom-project">
    <a class="notify-admin-back" href="{{ route('custom-projects.index') }}"><x-notify.icon name="chevron-right" :size="16" class="notify-admin-back__icon" />{{ __('custom_projects.title') }}</a>

    <x-notify.page-header :title="$project->name">
        <x-slot:actions>
            <span class="notify-status notify-status--{{ $statusTone[$project->status] ?? 'neutral' }}" data-project-status>{{ $project->statusLabel() }}</span>
            @if($project->archived_at)<span class="notify-status notify-status--neutral">{{ __('custom_projects.archived_status') }}</span>@endif
            @if($canManage)
                <x-notify.button :href="route('custom-projects.edit', $project)" variant="secondary" icon="pencil" data-project-edit>{{ __('custom_projects.edit') }}</x-notify.button>
                @unless($project->archived_at)
                    <x-notify.menu :label="__('custom_projects.more_actions')">
                        @foreach(\App\Models\CustomProject::STATUSES as $state)
                            @continue($state === $project->status)
                            <form method="POST" action="{{ route('custom-projects.update', $project) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="name" value="{{ $project->name }}">
                                <input type="hidden" name="start_date" value="{{ $project->start_date?->toDateString() }}">
                                <input type="hidden" name="target_completion_date" value="{{ $project->target_completion_date?->toDateString() }}">
                                <input type="hidden" name="notes" value="{{ $project->notes }}">
                                <input type="hidden" name="status" value="{{ $state }}">
                                <button class="notify-menu__item" type="submit" role="menuitem" data-project-set-status="{{ $state }}">
                                    <x-notify.icon name="check" :size="18" /><span>{{ __('custom_projects.mark_as', ['status' => __('custom_projects.status.'.$state)]) }}</span>
                                </button>
                            </form>
                        @endforeach
                        @if($project->isCancelled())
                            <x-slot:danger>
                                <form method="POST" action="{{ route('custom-projects.archive', $project) }}" data-confirm="{{ __('custom_projects.archive_confirm') }}" data-confirm-label="{{ __('custom_projects.archive') }}">
                                    @csrf
                                    <button type="submit" class="notify-menu__item notify-menu__item--danger" role="menuitem" data-project-archive>
                                        <x-notify.icon name="x" :size="18" /><span>{{ __('custom_projects.archive') }}</span>
                                    </button>
                                </form>
                            </x-slot:danger>
                        @endif
                    </x-notify.menu>
                @endunless
            @endif
        </x-slot:actions>
    </x-notify.page-header>

    <section class="notify-admin-card" aria-labelledby="project-details-title">
        <h2 id="project-details-title" class="notify-admin-card__title">{{ __('custom_projects.details') }}</h2>
        <dl class="notify-admin-readonly-list notify-admin-readonly-list--grid">
            <div>
                <dt>{{ __('custom_projects.client') }}</dt>
                <dd>
                    @if($project->client)
                        <a href="{{ route('clients.show', $project->client_id) }}" data-project-client>{{ $project->client->business_name }}</a>
                    @else
                        <span class="notify-admin-muted" data-no-client>{{ __('custom_projects.no_client') }}</span>
                    @endif
                </dd>
            </div>
            <div><dt>{{ __('custom_projects.value') }}</dt><dd><x-notify.money :minor="(int) $project->agreed_value_minor" /></dd></div>
            <div><dt>{{ __('custom_projects.start_date') }}</dt><dd><span dir="ltr">{{ $project->start_date?->toDateString() ?? '—' }}</span></dd></div>
            <div><dt>{{ __('custom_projects.target_date') }}</dt><dd><span dir="ltr">{{ $project->target_completion_date?->toDateString() ?? '—' }}</span></dd></div>
            <div><dt>{{ __('custom_projects.created_by') }}</dt><dd>{{ $project->creator?->name ?? '—' }}</dd></div>
        </dl>
        @if($project->notes)
            <p class="notify-admin-notes">{{ $project->notes }}</p>
        @endif
        <p class="notify-admin-muted notify-admin-small">{{ __('custom_projects.value_note') }}</p>
    </section>

    <section class="notify-admin-card" aria-labelledby="project-invoices-title" data-project-invoices>
        <div class="notify-admin-card__head">
            <div>
                <h2 id="project-invoices-title" class="notify-admin-card__title">{{ __('custom_projects.invoice_title') }}</h2>
                <p class="notify-admin-card__desc">{{ __('custom_projects.invoice_note') }}</p>
            </div>
            @if($canInvoice && $invoiceable)
                <button type="button" class="notify-button notify-button--primary notify-button--sm" data-open-sheet="project-invoice" aria-haspopup="dialog" data-project-invoice-open>
                    <x-notify.icon name="receipt" :size="16" /><span class="notify-button__label">{{ __('custom_projects.issue_invoice') }}</span>
                </button>
            @endif
        </div>

        @if(! $project->client_id)
            <p class="notify-form-note notify-form-note--info" data-invoice-needs-client>
                {{ __('custom_projects.client_required_for_invoice') }}
                @if($canManage && ! $project->archived_at)
                    <a href="{{ route('custom-projects.edit', $project) }}#project-client">{{ __('custom_projects.link_client') }}</a>
                @endif
            </p>
        @endif

        <x-notify.list :label="__('custom_projects.invoice_title')" :empty="$project->invoices->isEmpty()"
            :columns="[__('custom_projects.invoice_number'), __('custom_projects.columns.status'), ['label' => __('custom_projects.invoice_amount'), 'class' => 'is-num']]">
            @foreach($project->invoices as $invoice)
                <tr data-project-invoice="{{ $invoice->id }}">
                    <td class="notify-list__primary"><span class="notify-list__title" dir="ltr">{{ $invoice->invoice_number }}</span></td>
                    <td data-label="{{ __('custom_projects.columns.status') }}"><span class="notify-status notify-status--{{ $invoice->status === 'voided' ? 'neutral' : 'info' }}">{{ __('custom_projects.invoice_status.'.$invoice->status) }}</span></td>
                    <td class="notify-list__end notify-list__amount"><x-notify.money :minor="(int) $invoice->total_minor" /></td>
                </tr>
            @endforeach
            <x-slot:emptyState>
                <x-notify.empty-state :compact="true" icon="receipt" :title="__('custom_projects.no_invoices')" />
            </x-slot:emptyState>
        </x-notify.list>
    </section>
</div>

@if($canInvoice && $invoiceable)
    @formscope('project-invoice')
    <x-notify.sheet id="project-invoice" :title="__('custom_projects.issue_invoice')" :subtitle="__('custom_projects.invoice_sheet_hint', ['client' => $project->client->business_name])"
        :action="route('clients.one-time-invoices.store', $project->client_id)">
        <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <input type="hidden" name="custom_project_id" value="{{ $project->id }}">
        <input type="hidden" name="lines[0][line_type]" value="one_time_service">
        <div class="notify-form-row">
            <x-notify.form-field :label="__('custom_projects.description')" for="invoice-description" name="lines.0.description" :required="true" class="notify-form-row__full">
                <input id="invoice-description" class="notify-input" name="lines[0][description]" required maxlength="500" value="{{ $old('lines.0.description', $project->name) }}" @invalid('lines.0.description', 'invoice-description')>
            </x-notify.form-field>
            <x-notify.form-field :label="__('custom_projects.unit_price')" for="invoice-price" name="lines.0.unit_price_jod" :required="true">
                <x-notify.money-input id="invoice-price" name="lines[0][unit_price_jod]" :required="true" :value="$old('lines.0.unit_price_jod', $project->agreed_value_minor > 0 ? $project->agreedValueFormatted() : '')" />
            </x-notify.form-field>
            <x-notify.form-field :label="__('custom_projects.quantity')" for="invoice-quantity" name="lines.0.quantity" :required="true">
                <input id="invoice-quantity" class="notify-input" type="number" min="1" max="999" name="lines[0][quantity]" required inputmode="numeric" dir="ltr" value="{{ $old('lines.0.quantity', 1) }}" @invalid('lines.0.quantity', 'invoice-quantity')>
            </x-notify.form-field>
            <x-notify.form-field :label="__('custom_projects.issue_date')" for="invoice-issue" name="issue_date" :required="true">
                <input id="invoice-issue" class="notify-input" type="date" name="issue_date" required value="{{ $old('issue_date', now('Asia/Amman')->toDateString()) }}" @invalid('issue_date', 'invoice-issue')>
            </x-notify.form-field>
            <x-notify.form-field :label="__('custom_projects.due_date')" for="invoice-due" name="due_date" :required="true">
                <input id="invoice-due" class="notify-input" type="date" name="due_date" required value="{{ $old('due_date', now('Asia/Amman')->toDateString()) }}" @invalid('due_date', 'invoice-due')>
            </x-notify.form-field>
        </div>
        <p class="notify-form-note notify-form-note--info">{{ __('custom_projects.invoice_boundary_note') }}</p>
        <x-slot:footer>
            <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.actions.cancel') }}</button>
            <button type="submit" class="notify-button notify-button--primary" data-project-invoice-submit>{{ __('custom_projects.issue_invoice') }}</button>
        </x-slot:footer>
    </x-notify.sheet>
    @endformscope
@endif
@endsection
