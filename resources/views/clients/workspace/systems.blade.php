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

    @if($errors->credentials->any())
        <div class="notify-credentials__errors" role="alert">
            @foreach($errors->credentials->all() as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    @forelse($sys['rows'] as $row)
        @php($credential = $row['credential'])
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
                        <form method="POST" action="{{ route('clients.system-access.destroy', [$client, $row['system']]) }}" data-confirm="{{ __('notify.client_hub.systems.revoke_confirm', ['system' => $row['name']]) }}" data-revoke-access>
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

                        @if($panel['can_reveal'])
                            <div class="notify-credential__actions"
                                 data-credential-actions
                                 data-reveal-url="{{ route('clients.credentials.reveal', [$client, $credential]) }}"
                                 data-copied-url="{{ route('clients.credentials.copied', [$client, $credential]) }}">
                                <button type="button" class="notify-button notify-button--soft notify-button--sm" data-credential-reveal
                                        data-label-reveal="{{ __('notify.credentials.reveal') }}" data-label-hide="{{ __('notify.credentials.hide') }}">{{ __('notify.credentials.reveal') }}</button>
                                <button type="button" class="notify-button notify-button--soft notify-button--sm" data-credential-copy
                                        data-label-copied="{{ __('notify.credentials.copied') }}">{{ __('notify.credentials.copy') }}</button>
                                <button type="button" class="notify-button notify-button--primary notify-button--sm" data-credential-send="modal-credential-send-{{ $credential->id }}">{{ __('notify.credentials.send') }}</button>
                                <p class="notify-credential__status" data-credential-status role="status" hidden></p>
                            </div>
                        @endif
                    @else
                        <p class="notify-system__meta"><x-notify.icon name="key-round" :size="14" /> {{ __('notify.credentials.not_set') }}</p>
                    @endif

                    @if($panel['can_manage'])
                        <details class="notify-credential__edit" @if(! $credential && $errors->credentials->any() && (int) old('product_id') === $row['system']->id) open @endif>
                            <summary>{{ $credential ? __('notify.credentials.edit') : __('notify.credentials.add') }}</summary>
                            @include('clients.workspace.credential-form', ['credential' => $credential, 'system' => $row['system']])

                            @if($credential)
                                <form method="POST" action="{{ route('clients.credentials.destroy', [$client, $credential]) }}" class="notify-credential__delete"
                                      data-confirm="{{ __('notify.credentials.delete_confirm', ['system' => $row['name']]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="notify-button notify-button--ghost notify-button--sm">{{ __('notify.credentials.delete') }}</button>
                                </form>
                            @endif
                        </details>
                    @endif
                </div>
            @endif
        </article>

        @if($credential && $row['show_credential'] && $panel['can_reveal'])
            <div class="notify-modal-backdrop" id="modal-credential-send-{{ $credential->id }}" hidden>
                <div class="notify-modal-card notify-action-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-credential-send-{{ $credential->id }}-title">
                    <div class="notify-modal-header">
                        <h3 id="modal-credential-send-{{ $credential->id }}-title">{{ __('notify.credentials.send_title') }} · {{ $row['name'] }}</h3>
                        <button type="button" class="notify-icon-button" data-close-action-modal aria-label="{{ __('notify.credentials.cancel') }}"><x-notify.icon name="x" /></button>
                    </div>
                    <form class="notify-action-form notify-credential__send" data-credential-send-form
                          data-send-url="{{ route('clients.credentials.send', [$client, $credential]) }}">
                        <div class="field">
                            <label for="credential-recipient-{{ $credential->id }}">{{ __('notify.credentials.recipient') }}</label>
                            <input id="credential-recipient-{{ $credential->id }}" class="touch-input" type="tel" name="recipient" inputmode="tel" dir="ltr" maxlength="50" required value="{{ $panel['recipient'] }}">
                        </div>
                        <div class="field">
                            <span class="notify-field-label">{{ __('notify.credentials.preview') }}</span>
                            <pre class="notify-credential__preview" dir="auto">{{ $credentialService->previewMessage($credential) }}</pre>
                            <small class="notify-muted">{{ __('notify.credentials.preview_hint') }}</small>
                        </div>
                        <p class="notify-credential__status" data-credential-status role="alert" hidden></p>
                        <div class="notify-form-footer">
                            <button type="button" class="notify-button notify-button--ghost" data-close-action-modal>{{ __('notify.credentials.cancel') }}</button>
                            <button type="submit" class="notify-button notify-button--primary">{{ __('notify.credentials.confirm_send') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @empty
        <p class="notify-ws-card__empty">{{ __('notify.client_hub.systems.empty') }}</p>
    @endforelse

    @if($sys['can_add_credentials'] && $panel['addable']->isNotEmpty())
        <details class="notify-credential__edit notify-credential__add" @if($errors->credentials->any() && $panel['addable']->pluck('id')->contains((int) old('product_id'))) open @endif>
            <summary>{{ __('notify.credentials.add_for_system') }}</summary>
            @include('clients.workspace.credential-form', ['credential' => null, 'system' => null, 'addable' => $panel['addable']])
        </details>
    @endif
</section>

@if($sys['can_grant'] && $sys['grantable']->isNotEmpty())
    <div class="notify-modal-backdrop" id="modal-grant-access" hidden>
        <div class="notify-modal-card notify-action-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-grant-access-title">
            <div class="notify-modal-header">
                <div>
                    <h3 id="modal-grant-access-title">{{ __('notify.client_hub.systems.grant_title') }}</h3>
                    <p class="notify-sheet-hint">{{ __('notify.client_hub.systems.grant_hint') }}</p>
                </div>
                <button type="button" class="notify-icon-button" data-close-action-modal aria-label="{{ __('notify.client_hub.actions.cancel') }}"><x-notify.icon name="x" /></button>
            </div>
            <form method="POST" action="{{ route('clients.system-access.store', $client) }}" class="notify-action-form" data-grant-access-form>
                @csrf
                <fieldset class="notify-choice-list">
                    <legend class="notify-visually-hidden">{{ __('notify.client_hub.systems.title') }}</legend>
                    @foreach($sys['grantable'] as $system)
                        <label class="notify-choice">
                            <input type="checkbox" name="system_ids[]" value="{{ $system->id }}">
                            <span>{{ \App\ViewModels\ClientPresenter::systemName($system) }}</span>
                        </label>
                    @endforeach
                </fieldset>
                <label class="notify-field">
                    <span>{{ __('notify.client_hub.systems.note') }}</span>
                    <input type="text" name="note" maxlength="1000">
                </label>
                <div class="notify-modal-footer">
                    <button type="button" class="notify-button notify-button--ghost" data-close-action-modal>{{ __('notify.client_hub.actions.cancel') }}</button>
                    <button type="submit" class="notify-button notify-button--primary">{{ __('notify.client_hub.systems.grant_submit') }}</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endif

@if($panel['can_reveal'] && $panel['credentials']->isNotEmpty())
    @include('clients.workspace.credential-script')
@endif
