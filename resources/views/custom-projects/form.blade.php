{{--
    Custom Project form (P13, P12 form system). The Client link is optional: a project can be tracked
    without one, but it cannot be invoiced until a Client is linked. Once invoiced, the link is fixed.
    Expects: $project (nullable), $clients (id + business_name), $client (preselected, create only).
--}}
@php
    $client ??= null;
    $selectedClient = (string) old('client_id', $project ? $project->client_id : $client?->id);
    $clientLocked = $project && (int) ($project->invoices_count ?? 0) > 0;
@endphp
<form method="POST" action="{{ $project ? route('custom-projects.update', $project) : route('custom-projects.store') }}" class="notify-admin-form" data-project-form>
    @csrf
    @if($project) @method('PUT') @endif

    <div class="notify-form-row">
        <x-notify.form-field :label="__('custom_projects.name')" for="project-name" name="name" :required="true" class="notify-form-row__full">
            <input id="project-name" class="notify-input" name="name" required maxlength="255" value="{{ old('name', $project?->name) }}" @invalid('name', 'project-name')>
        </x-notify.form-field>

        <x-notify.form-field :label="__('custom_projects.client')" for="project-client" name="client_id" :optional="true" :hint="$clientLocked ? __('custom_projects.client_locked') : __('custom_projects.client_required_for_invoice')" class="notify-form-row__full">
            @if($clientLocked)
                <input type="hidden" name="client_id" value="{{ $project->client_id }}">
                <input id="project-client" class="notify-input" value="{{ $project->client?->business_name }}" readonly aria-describedby="project-client-hint">
            @else
                <select id="project-client" class="notify-input" name="client_id" @invalid('client_id', 'project-client', 'default', true)>
                    <option value="">{{ __('custom_projects.no_client_option') }}</option>
                    @foreach($clients as $option)
                        <option value="{{ $option->id }}" @selected($selectedClient === (string) $option->id)>{{ $option->business_name }}</option>
                    @endforeach
                </select>
            @endif
        </x-notify.form-field>

        <x-notify.form-field :label="__('custom_projects.status_label')" for="project-status" name="status" :required="true">
            <select id="project-status" class="notify-input" name="status" required @invalid('status', 'project-status')>
                @foreach(\App\Models\CustomProject::STATUSES as $state)
                    <option value="{{ $state }}" @selected(old('status', $project?->status ?? 'planned') === $state)>{{ __('custom_projects.status.'.$state) }}</option>
                @endforeach
            </select>
        </x-notify.form-field>

        <x-notify.form-field :label="__('custom_projects.value')" for="project-value" name="agreed_value_jod" :optional="true" :hint="__('custom_projects.value_note')">
            <x-notify.money-input id="project-value" name="agreed_value_jod" :value="old('agreed_value_jod', $project && $project->agreed_value_minor ? $project->agreedValueFormatted() : '')" :hint="true" />
        </x-notify.form-field>

        <x-notify.form-field :label="__('custom_projects.start_date')" for="project-start" name="start_date" :optional="true">
            <input id="project-start" class="notify-input" name="start_date" type="date" value="{{ old('start_date', $project?->start_date?->toDateString()) }}" @invalid('start_date', 'project-start')>
        </x-notify.form-field>

        <x-notify.form-field :label="__('custom_projects.target_date')" for="project-target" name="target_completion_date" :optional="true">
            <input id="project-target" class="notify-input" name="target_completion_date" type="date" value="{{ old('target_completion_date', $project?->target_completion_date?->toDateString()) }}" @invalid('target_completion_date', 'project-target')>
        </x-notify.form-field>

        <x-notify.form-field :label="__('custom_projects.notes')" for="project-notes" name="notes" :optional="true" class="notify-form-row__full">
            <textarea id="project-notes" class="notify-input" name="notes" rows="3" maxlength="5000" @invalid('notes', 'project-notes')>{{ old('notes', $project?->notes) }}</textarea>
        </x-notify.form-field>
    </div>

    <div class="notify-form-actions">
        <a href="{{ $project ? route('custom-projects.show', $project) : route('custom-projects.index') }}" class="notify-button notify-button--ghost">{{ __('custom_projects.cancel') }}</a>
        <button type="submit" class="notify-button notify-button--primary">{{ $project ? __('custom_projects.save') : __('custom_projects.create') }}</button>
    </div>
</form>
