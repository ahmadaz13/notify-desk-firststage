{{--
    P5 minimal credentials surface (§18.3). The final card design is P10.
    SECURITY: this view never renders a secret or note value. Plaintext only arrives through the
    explicit POST reveal/send actions and is re-masked after 30 seconds.
--}}
@php
    $panel = $credentialPanel;
    $mask = '••••••••';
@endphp

@if(($panel['can_manage'] || $panel['can_reveal']) && ($panel['systems']->isNotEmpty() || ($panel['can_manage'] && $panel['addable']->isNotEmpty())))
<section class="notify-workspace-card notify-credentials" id="sec-credentials" aria-labelledby="credentials-title">
    <div class="notify-workspace-card__head">
        <div>
            <h2 class="notify-workspace-card__title" id="credentials-title">{{ __('notify.credentials.title') }}</h2>
            <p class="notify-muted notify-credentials__meta">{{ __('notify.credentials.meta') }}</p>
        </div>
    </div>

    <div class="notify-workspace-card__body">
        @if($errors->credentials->any())
            <div class="notify-credentials__errors" role="alert">
                @foreach($errors->credentials->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif

        <div class="notify-credentials__list">
            @foreach($panel['systems'] as $system)
                @php
                    $credential = $panel['credentials']->get($system->id);
                    $systemName = $credentialService->systemName($system);
                @endphp
                <article class="notify-credential" data-credential-card>
                    <h3 class="notify-credential__system">{{ $systemName }}</h3>

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
                        <p class="notify-muted">{{ __('notify.credentials.not_set') }}</p>
                    @endif

                    @if($panel['can_manage'])
                        <details class="notify-credential__edit" @if(! $credential && $errors->credentials->any() && (int) old('product_id') === $system->id) open @endif>
                            <summary>{{ $credential ? __('notify.credentials.edit') : __('notify.credentials.add') }}</summary>
                            @include('clients.workspace.credential-form', ['credential' => $credential, 'system' => $system])

                            @if($credential)
                                <form method="POST" action="{{ route('clients.credentials.destroy', [$client, $credential]) }}" class="notify-credential__delete"
                                      onsubmit="return confirm(@js(__('notify.credentials.delete_confirm', ['system' => $systemName])))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="notify-button notify-button--ghost notify-button--sm">{{ __('notify.credentials.delete') }}</button>
                                </form>
                            @endif
                        </details>
                    @endif
                </article>

                @if($credential && $panel['can_reveal'])
                    <div class="notify-modal-backdrop" id="modal-credential-send-{{ $credential->id }}" hidden>
                        <div class="notify-modal-card notify-action-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-credential-send-{{ $credential->id }}-title">
                            <div class="notify-modal-header">
                                <h3 id="modal-credential-send-{{ $credential->id }}-title">{{ __('notify.credentials.send_title') }} · {{ $systemName }}</h3>
                                <button type="button" class="notify-icon-button" data-close-action-modal aria-label="{{ __('notify.credentials.cancel') }}">×</button>
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
            @endforeach
        </div>

        @if($panel['can_manage'] && $panel['addable']->isNotEmpty())
            <details class="notify-credential__edit notify-credential__add" @if($errors->credentials->any() && $panel['addable']->pluck('id')->contains((int) old('product_id'))) open @endif>
                <summary>{{ __('notify.credentials.add_for_system') }}</summary>
                @include('clients.workspace.credential-form', ['credential' => null, 'system' => null, 'addable' => $panel['addable']])
            </details>
        @endif
    </div>
</section>

@if($panel['can_reveal'] && $panel['credentials']->isNotEmpty())
<script>
(function () {
    var MASK = @js($mask);
    var csrf = @js(csrf_token());
    var errorText = @js(__('notify.credentials.error'));
    var copyFailedText = @js(__('notify.credentials.copy_failed'));
    // Plaintext lives only in memory while revealed and is dropped when re-masked.
    var revealed = new WeakMap();

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(body || {})
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok) { var error = new Error('request'); error.data = data; throw error; }
                return data;
            });
        });
    }

    function status(scope, text) {
        var el = scope.querySelector('[data-credential-status]');
        if (!el) { return; }
        el.textContent = text || '';
        el.hidden = !text;
    }

    function mask(card) {
        var state = revealed.get(card);
        if (state) { clearTimeout(state.timer); }
        revealed.delete(card);
        card.querySelector('[data-secret-display]').textContent = MASK;
        var noteRow = card.querySelector('[data-note-row]');
        noteRow.querySelector('[data-note-display]').textContent = '';
        noteRow.hidden = true;
        var button = card.querySelector('[data-credential-reveal]');
        if (button) { button.textContent = button.dataset.labelReveal; }
    }

    function fetchSecret(card) {
        var state = revealed.get(card);
        if (state) { return Promise.resolve(state.data); }
        var actions = card.querySelector('[data-credential-actions]');
        return post(actions.dataset.revealUrl).then(function (data) {
            revealed.set(card, { data: data, timer: setTimeout(function () { mask(card); }, 30000) });
            return data;
        });
    }

    document.addEventListener('click', function (event) {
        var revealButton = event.target.closest('[data-credential-reveal]');
        if (revealButton) {
            var card = revealButton.closest('[data-credential-card]');
            if (revealed.has(card)) { mask(card); return; }
            status(card, '');
            fetchSecret(card).then(function (data) {
                card.querySelector('[data-secret-display]').textContent = data.secret;
                if (data.note) {
                    var noteRow = card.querySelector('[data-note-row]');
                    noteRow.querySelector('[data-note-display]').textContent = data.note;
                    noteRow.hidden = false;
                }
                revealButton.textContent = revealButton.dataset.labelHide;
            }).catch(function () { status(card, errorText); });
            return;
        }

        var copyButton = event.target.closest('[data-credential-copy]');
        if (copyButton) {
            var copyCard = copyButton.closest('[data-credential-card]');
            var actions = copyCard.querySelector('[data-credential-actions]');
            status(copyCard, '');
            fetchSecret(copyCard).then(function (data) {
                if (!navigator.clipboard || !window.isSecureContext) { throw new Error('clipboard'); }
                return navigator.clipboard.writeText(data.secret);
            }).then(function () {
                status(copyCard, copyButton.dataset.labelCopied);
                return post(actions.dataset.copiedUrl);
            }).catch(function () { status(copyCard, copyFailedText); });
            return;
        }

        var sendButton = event.target.closest('[data-credential-send]');
        if (sendButton && typeof openModal === 'function') {
            event.preventDefault();
            openModal(sendButton.dataset.credentialSend);
        }
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-credential-send-form]');
        if (!form) { return; }
        event.preventDefault();
        status(form, '');
        // Open the tab inside the user gesture so it is not popup-blocked, then point it at WhatsApp.
        var target = window.open('about:blank', '_blank');
        post(form.dataset.sendUrl, { recipient: form.elements.recipient.value }).then(function (data) {
            if (target) { target.opener = null; target.location.href = data.url; } else { window.location.href = data.url; }
            if (typeof closeModal === 'function') { closeModal(form.closest('.notify-modal-backdrop')); }
        }).catch(function (error) {
            if (target) { target.close(); }
            var message = error && error.data && error.data.errors && error.data.errors.recipient ? error.data.errors.recipient[0] : errorText;
            status(form, message);
        });
    });
})();
</script>
@endif
@endif
