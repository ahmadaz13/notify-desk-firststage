{{--
    D. Systems & access (§6, §19.5, §19.10). Each System shows its access (Free/Paid); credential-capable
    Systems carry their login details in place (P5 rules unchanged).
    SECURITY: no secret or note value is ever rendered; plaintext only arrives through the explicit POST
    reveal/send actions and is re-masked after 30 seconds (see credential-script).
--}}
@php
    $sys = $workspace->systems;
    $panel = $credentialPanel;
    $mask = '••••••••';
@endphp
@if($sys['rows'] || $sys['can_grant'])
<section class="notify-card notify-ws-card" aria-labelledby="client-systems-title" id="sec-systems" data-client-systems>
    <header class="notify-ws-card__head">
        <h2 class="notify-ws-card__title" id="client-systems-title"><x-notify.icon name="package" :size="18" /> {{ __('notify.client_hub.systems.title') }}</h2>
        @if($sys['can_grant'] && $sys['grantable']->isNotEmpty())
            <button type="button" class="notify-button notify-button--ghost notify-button--sm" data-open-sheet="modal-grant-access" aria-haspopup="dialog" data-grant-access>
                <x-notify.icon name="plus" :size="16" /><span>{{ __('notify.client_hub.actions.grant_access') }}</span>
            </button>
        @endif
    </header>

    @forelse($sys['rows'] as $row)
        @php
            $credential = $row['credential'];
            $credentialSheet = $credential ? 'credential-edit-'.$credential->id : 'credential-add-'.$row['system']->id;
        @endphp
        <article class="notify-system" data-system-row="{{ $row['system']->id }}" @if($row['show_credential']) data-credential-card @endif>
            <div class="notify-system__top">
                <h3 class="notify-system__name @if($row['show_credential']) notify-credential__system @endif">{{ $row['name'] }}</h3>
                <div class="notify-system__chips">
                    @if($row['access'] === 'paid')
                        <span class="notify-status notify-status--success"><x-notify.icon name="check" :size="14" />{{ __('notify.client_hub.systems.paid') }}</span>
                    @elseif($row['access'] === 'free')
                        <span class="notify-status notify-status--info"><x-notify.icon name="circle" :size="14" />{{ __('notify.client_hub.systems.free') }}</span>
                    @else
                        <span class="notify-status notify-status--neutral">{{ __('notify.client_hub.systems.credential_only') }}</span>
                    @endif
                    @if($row['can_revoke'])
                        <form method="POST" action="{{ route('clients.system-access.destroy', [$client, $row['system']]) }}"
                              data-confirm="{{ __('notify.client_hub.systems.revoke_confirm', ['system' => $row['name']]) }}"
                              data-confirm-label="{{ __('notify.client_hub.actions.revoke_access') }}" data-revoke-access>
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="notify-button notify-button--ghost notify-button--sm">{{ __('notify.client_hub.actions.revoke_access') }}</button>
                        </form>
                    @endif
                </div>
            </div>
            @if($row['granted'] && $row['access'])
                <p class="notify-system__meta">{{ __('notify.client_hub.systems.granted_on', ['date' => $row['granted']]) }}</p>
            @endif

            @if($row['show_credential'])
                <div class="notify-system__credential notify-credentials">
                    @if($credential)
                        <dl class="notify-credential__facts">
                            <div>
                                <dt>{{ __('notify.credentials.login_url') }}</dt>
                                <dd dir="ltr">@if($credential->login_url)<a href="{{ $credential->login_url }}" target="_blank" rel="noopener noreferrer">{{ $credential->login_url }}</a>@else — @endif</dd>
                            </div>
                            <div>
                                <dt>{{ __('notify.credentials.username') }}</dt>
                                <dd dir="ltr">{{ $credential->username ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('notify.credentials.password') }}</dt>
                                <dd dir="ltr"><span class="notify-credential__secret" data-secret-display aria-live="polite">{{ $mask }}</span></dd>
                            </div>
                            <div data-note-row hidden>
                                <dt>{{ __('notify.credentials.note') }}</dt>
                                <dd data-note-display></dd>
                            </div>
                            @if($credential->last_revealed_at)
                                <div>
                                    <dt>{{ __('notify.credentials.last_revealed') }}</dt>
                                    <dd dir="ltr">{{ $credential->last_revealed_at->timezone('Asia/Amman')->format('Y-m-d H:i') }}</dd>
                                </div>
                            @endif
                        </dl>
                    @else
                        <p class="notify-system__meta"><x-notify.icon name="key-round" :size="14" /> {{ __('notify.credentials.not_set') }}</p>
                    @endif

                    @if(($credential && $panel['can_reveal']) || $panel['can_manage'])
                        <div class="notify-credential__actions"
                             @if($credential && $panel['can_reveal'])
                                 data-credential-actions
                                 data-reveal-url="{{ route('clients.credentials.reveal', [$client, $credential]) }}"
                                 data-copied-url="{{ route('clients.credentials.copied', [$client, $credential]) }}"
                             @endif>
                            @if($credential && $panel['can_reveal'])
                                <button type="button" class="notify-button notify-button--soft notify-button--sm" data-credential-reveal
                                        data-label-reveal="{{ __('notify.credentials.reveal') }}" data-label-hide="{{ __('notify.credentials.hide') }}">{{ __('notify.credentials.reveal') }}</button>
                                <button type="button" class="notify-button notify-button--soft notify-button--sm" data-credential-copy
                                        data-label-copied="{{ __('notify.credentials.copied') }}">{{ __('notify.credentials.copy') }}</button>
                                <button type="button" class="notify-button notify-button--primary notify-button--sm" data-credential-send="modal-credential-send-{{ $credential->id }}" aria-haspopup="dialog">{{ __('notify.credentials.send') }}</button>
                            @endif
                            @if($panel['can_manage'])
                                <button type="button" class="notify-button notify-button--ghost notify-button--sm" data-open-sheet="{{ $credentialSheet }}" aria-haspopup="dialog" data-credential-edit>
                                    {{ $credential ? __('notify.credentials.edit') : __('notify.credentials.add') }}
                                </button>
                            @endif
                            <p class="notify-credential__status" data-credential-status role="status" hidden></p>
                        </div>
                    @endif
                </div>
            @endif
        </article>

        @if($row['show_credential'] && $panel['can_manage'])
            @include('clients.workspace.credential-form', ['credential' => $credential, 'system' => $row['system']])
        @endif

        @if($credential && $row['show_credential'] && $panel['can_reveal'])
            <x-notify.sheet :id="'modal-credential-send-'.$credential->id" size="sm" :title="__('notify.credentials.send_title')" :subtitle="$row['name']">
                <form id="credential-send-form-{{ $credential->id }}" class="notify-sheet__section notify-credential__send" data-credential-send-form
                      data-send-url="{{ route('clients.credentials.send', [$client, $credential]) }}" data-no-busy>
                    <x-notify.form-field :label="__('notify.credentials.recipient')" :for="'credential-recipient-'.$credential->id">
                        <input id="credential-recipient-{{ $credential->id }}" class="notify-input" type="tel" name="recipient" inputmode="tel" dir="ltr" maxlength="50" required value="{{ $panel['recipient'] }}">
                    </x-notify.form-field>
                    <div class="notify-form-field">
                        <span class="notify-form-field__label">{{ __('notify.credentials.preview') }}</span>
                        <pre class="notify-credential__preview" dir="auto">{{ $credentialService->previewMessage($credential) }}</pre>
                        <p class="notify-form-field__hint">{{ __('notify.credentials.preview_hint') }}</p>
                    </div>
                    <p class="notify-form-alert" data-credential-status role="alert" hidden></p>
                </form>
                <x-slot:footer>
                    <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.credentials.cancel') }}</button>
                    <button type="submit" form="credential-send-form-{{ $credential->id }}" class="notify-button notify-button--primary">{{ __('notify.credentials.confirm_send') }}</button>
                </x-slot:footer>
            </x-notify.sheet>
        @endif
    @empty
        <p class="notify-ws-card__empty">{{ __('notify.client_hub.systems.empty') }}</p>
    @endforelse

    @if($sys['can_add_credentials'] && $panel['addable']->isNotEmpty())
        <button type="button" class="notify-button notify-button--ghost notify-button--sm notify-credential__add-trigger" data-open-sheet="credential-add-other" aria-haspopup="dialog">
            <x-notify.icon name="plus" :size="16" /><span>{{ __('notify.credentials.add_for_system') }}</span>
        </button>
        @include('clients.workspace.credential-form', ['credential' => null, 'system' => null, 'addable' => $panel['addable']])
    @endif
</section>

@if($sys['can_grant'] && $sys['grantable']->isNotEmpty())
    @formscope('modal-grant-access')
    <x-notify.sheet id="modal-grant-access" :title="__('notify.client_hub.systems.grant_title')" :subtitle="__('notify.client_hub.systems.grant_hint')"
        :action="route('clients.system-access.store', $client)" :form-attributes="['data-grant-access-form' => true]">
        <x-notify.form-field :label="__('notify.client_hub.systems.title')" name="system_ids" :required="true" :group="true">
            <div class="notify-choices notify-choices--stack">
                @foreach($sys['grantable'] as $system)
                    <label class="notify-choice-chip">
                        <input type="checkbox" name="system_ids[]" value="{{ $system->id }}" @checked(in_array($system->id, array_map('intval', (array) \App\Support\FormState::oldFor('modal-grant-access')('system_ids', [])), true))>
                        <span>{{ \App\ViewModels\ClientPresenter::systemName($system) }}</span>
                    </label>
                @endforeach
            </div>
        </x-notify.form-field>
        <x-notify.form-field :label="__('notify.client_hub.systems.note')" for="grant-access-note" name="note" :optional="true">
            <input id="grant-access-note" class="notify-input" type="text" name="note" maxlength="1000" value="{{ \App\Support\FormState::oldFor('modal-grant-access')('note') }}" @invalid('note', 'grant-access-note')>
        </x-notify.form-field>
        <x-slot:footer>
            <button type="button" class="notify-button notify-button--ghost" data-sheet-close>{{ __('notify.client_hub.actions.cancel') }}</button>
            <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_hub.systems.grant_submit') }}</button>
        </x-slot:footer>
    </x-notify.sheet>
    @endformscope
@endif
@endif

@if($panel['can_reveal'] && $panel['credentials']->isNotEmpty())
    @include('clients.workspace.credential-script')
@endif
