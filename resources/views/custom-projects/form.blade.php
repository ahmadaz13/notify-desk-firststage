<form method="POST" action="{{ $project ? route('custom-projects.update', $project) : route('custom-projects.store') }}">
    @csrf
    @if($project) @method('PUT') @endif
    @unless($project)
        <label for="project-client">{{ __('custom_projects.client') }} *</label>
        <select id="project-client" name="client_id" required class="p5-select">
            <option value="">{{ __('custom_projects.choose_client') }}</option>
            @foreach($clients as $option)
                <option value="{{ $option->id }}" @selected(old('client_id', $client?->id) == $option->id)>{{ $option->business_name }}</option>
            @endforeach
        </select>
        @error('client_id')<div class="p5-field-error">{{ $message }}</div>@enderror
    @endunless
    <div class="p5-form-grid" style="margin-block:14px">
        <div class="p5-field"><label for="project-name">{{ __('custom_projects.name') }} *</label>
            <input id="project-name" name="name" required class="p5-input" value="{{ old('name', $project?->name) }}">
            @error('name')<div class="p5-field-error">{{ $message }}</div>@enderror
        </div>
        <div class="p5-field"><label for="project-value">{{ __('custom_projects.value') }}</label>
            <input id="project-value" name="agreed_value_jod" inputmode="decimal" class="p5-input" value="{{ old('agreed_value_jod', $project?->agreedValueFormatted()) }}">
            @error('agreed_value_jod')<div class="p5-field-error">{{ $message }}</div>@enderror
        </div>
        <div class="p5-field"><label for="project-status">{{ __('custom_projects.status_label') }} *</label>
            <select id="project-status" name="status" required class="p5-select">
                @foreach(\App\Models\CustomProject::STATUSES as $state)
                    <option value="{{ $state }}" @selected(old('status', $project?->status ?? 'planned') === $state)>{{ __('custom_projects.status.'.$state) }}</option>
                @endforeach
            </select>
        </div>
        <div class="p5-field"><label for="project-start">{{ __('custom_projects.start_date') }}</label>
            <input id="project-start" name="start_date" type="date" class="p5-input" value="{{ old('start_date', $project?->start_date?->toDateString()) }}">
        </div>
        <div class="p5-field"><label for="project-target">{{ __('custom_projects.target_date') }}</label>
            <input id="project-target" name="target_completion_date" type="date" class="p5-input" value="{{ old('target_completion_date', $project?->target_completion_date?->toDateString()) }}">
            @error('target_completion_date')<div class="p5-field-error">{{ $message }}</div>@enderror
        </div>
        <div class="p5-field"><label for="project-notes">{{ __('custom_projects.notes') }}</label>
            <textarea id="project-notes" name="notes" rows="3" class="p5-textarea">{{ old('notes', $project?->notes) }}</textarea>
        </div>
    </div>
    <p>{{ __('custom_projects.value_note') }}</p>
    <div style="display:flex;gap:10px;justify-content:flex-end">
        <a href="{{ $project ? route('custom-projects.show', $project) : route('custom-projects.index') }}" class="p5-btn p5-btn-ghost">{{ __('custom_projects.cancel') }}</a>
        <button type="submit" class="p5-btn p5-btn-primary">{{ $project ? __('custom_projects.save') : __('custom_projects.create') }}</button>
    </div>
</form>
