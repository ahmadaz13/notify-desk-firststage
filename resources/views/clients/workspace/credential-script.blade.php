{{--
    P5 credential reveal/copy/send behaviour (§18.3), unchanged. Plaintext is fetched only by the explicit
    POST actions, kept in memory while shown and re-masked after 30 seconds.
--}}
@php($mask = '••••••••')
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
