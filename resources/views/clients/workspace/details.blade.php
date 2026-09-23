<div class="notify-workspace-details-content">
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
        <a href="{{ route('clients.edit', $client->id) }}" class="notify-button notify-button--soft">
            <span>{{ __('notify.clients.edit') }}</span>
        </a>
    </div>

    {{-- Overview facts --}}
    @include('clients.workspace.overview')

    {{-- Contacts list and Add Contact form --}}
    @include('clients.workspace.contacts')

    {{-- Notes --}}
    @include('clients.workspace.notes')
</div>
