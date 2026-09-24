{{-- Client Workspace behaviour (P10): focused sheets, deep links, confirmations. Workflow forms are unchanged. --}}
<script>
(function () {
    var lastOpener = null;
    var openSheetId = @js($openSheet ?? null);
    // Today work items link with hash anchors; land them on the matching action (§22).
    var hashSheets = {
        '#call': 'modal-record-call',
        '#payment': 'modal-record-payment',
        '#follow-up': 'modal-follow-up',
        '#installation': 'modal-complete-installation',
        '#appointments': 'modal-appointment-result',
    };
    var hashTargets = { '#review': '[data-client-review]', '#collapsible-management': '#client-next-step' };

    window.openModal = function (id, opener) {
        var modal = document.getElementById(id);
        if (!modal) { return false; }
        lastOpener = opener || document.activeElement;
        modal.hidden = false;
        document.body.classList.add('notify-sheet-open');
        var focusTarget = modal.querySelector('input:not([type=hidden]):not([disabled]), select, textarea, button:not([data-close-action-modal])');
        if (focusTarget) { focusTarget.focus({ preventScroll: true }); }
        return true;
    };

    window.closeModal = function (modal) {
        if (!modal) { return; }
        modal.hidden = true;
        if (!document.querySelector('.notify-modal-backdrop:not([hidden])')) {
            document.body.classList.remove('notify-sheet-open');
        }
        if (lastOpener && document.body.contains(lastOpener)) { lastOpener.focus({ preventScroll: true }); }
    };

    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-open-sheet]');
        if (opener) {
            event.preventDefault();
            var menu = opener.closest('details.notify-menu');
            if (menu) { menu.open = false; }
            openModal(opener.getAttribute('data-open-sheet'), opener);
            return;
        }

        var closer = event.target.closest('[data-close-action-modal]');
        if (closer) {
            event.preventDefault();
            closeModal(closer.closest('.notify-modal-backdrop'));
            return;
        }

        if (event.target.classList.contains('notify-modal-backdrop')) {
            closeModal(event.target);
            return;
        }

        document.querySelectorAll('details.notify-menu[open]').forEach(function (menu) {
            if (!menu.contains(event.target)) { menu.open = false; }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') { return; }
        document.querySelectorAll('.notify-modal-backdrop:not([hidden])').forEach(closeModal);
        document.querySelectorAll('details.notify-menu[open]').forEach(function (menu) {
            menu.open = false;
            menu.querySelector('summary').focus();
        });
    });

    // Destructive inline actions ask once in the app's own dialog.
    var pendingForm = null;
    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form[data-confirm]');
        if (!form || form.dataset.confirmed === '1') { return; }
        event.preventDefault();
        pendingForm = form;
        document.querySelector('[data-confirm-text]').textContent = form.getAttribute('data-confirm');
        openModal('modal-confirm', event.submitter || form.querySelector('[type=submit]'));
    }, true);
    document.addEventListener('click', function (event) {
        if (!event.target.closest('[data-confirm-accept]') || !pendingForm) { return; }
        pendingForm.dataset.confirmed = '1';
        pendingForm.submit();
    });

    // Opened from Today (P11): workspace actions return to the same Today view once they succeed.
    var params = new URLSearchParams(window.location.search);
    var returnTo = params.get('from');
    if (returnTo === 'today' || returnTo === 'work') {
        document.querySelectorAll('.notify-modal-backdrop form[method="POST"], [data-client-review] form[method="POST"]').forEach(function (form) {
            [['_return_to', returnTo], ['_return_scope', params.get('scope') === 'my' ? 'my' : 'all']].forEach(function (pair) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = pair[0];
                input.value = pair[1];
                form.appendChild(input);
            });
        });
    }

    var initial = openSheetId || hashSheets[window.location.hash];
    if (initial && !openModal(initial)) {
        initial = null;
    }
    if (!initial && hashTargets[window.location.hash]) {
        var target = document.querySelector(hashTargets[window.location.hash]);
        if (target) { target.scrollIntoView({ block: 'start' }); }
    }
})();

// 1. Record Call outcome conditional fields
document.querySelectorAll('[data-call-outcome-form]').forEach(function(form) {
    var outcomeRadios = form.querySelectorAll('input[name="result"]');
    var conditionalGroups = form.querySelectorAll('[data-for-outcome]');
    var noteContainer = form.querySelector('[data-note-container]');
    var noteRequired = form.querySelector('[data-note-required]');

    function syncCallOutcome() {
        var checked = form.querySelector('input[name="result"]:checked');
        var val = checked ? checked.value : null;

        conditionalGroups.forEach(function(group) {
            group.hidden = (group.getAttribute('data-for-outcome') !== val);
        });

        if (noteContainer) {
            noteContainer.hidden = !val || (val === 'no_answer_busy');
        }
        if (noteRequired) {
            noteRequired.hidden = (val !== 'not_interested');
        }
    }

    outcomeRadios.forEach(function(r) { r.addEventListener('change', syncCallOutcome); });
    syncCallOutcome();
});

// 2. Appointment Result conditional fields
document.querySelectorAll('[data-appointment-result-form]').forEach(function(form) {
    var decisionRadios = form.querySelectorAll('input[name="first_decision"]');
    var decisionGroups = form.querySelectorAll('[data-for-decision]');
    var attendedRadios = form.querySelectorAll('input[name="attended_choice"]');
    var attendedGroups = form.querySelectorAll('[data-for-attended-choice]');

    function syncDecision() {
        var checkedDecision = form.querySelector('input[name="first_decision"]:checked');
        var decVal = checkedDecision ? checkedDecision.value : null;

        decisionGroups.forEach(function(group) {
            group.hidden = (group.getAttribute('data-for-decision') !== decVal);
        });

        syncAttendedChoice();
    }

    function syncAttendedChoice() {
        var checkedChoice = form.querySelector('input[name="attended_choice"]:checked');
        var choiceVal = checkedChoice ? checkedChoice.value : null;

        attendedGroups.forEach(function(group) {
            group.hidden = (group.getAttribute('data-for-attended-choice') !== choiceVal);
        });
    }

    decisionRadios.forEach(function(r) { r.addEventListener('change', syncDecision); });
    attendedRadios.forEach(function(r) { r.addEventListener('change', syncAttendedChoice); });
    syncDecision();
});

// 3. Follow-up Outcome conditional fields
document.querySelectorAll('[data-followup-outcome-form]').forEach(function(form) {
    var outcomeRadios = form.querySelectorAll('input[name="outcome"]');
    var conditionalGroups = form.querySelectorAll('[data-for-followup-outcome]');

    function syncFollowUpOutcome() {
        var checked = form.querySelector('input[name="outcome"]:checked');
        var val = checked ? checked.value : null;

        conditionalGroups.forEach(function(group) {
            group.hidden = (group.getAttribute('data-for-followup-outcome') !== val);
        });
    }

    outcomeRadios.forEach(function(r) { r.addEventListener('change', syncFollowUpOutcome); });
    syncFollowUpOutcome();
});

// 4. Close Client reason note requirement
document.querySelectorAll('[data-close-client-form]').forEach(function(form) {
    var reasonSelect = form.querySelector('[data-close-reason-select]');
    var noteRequired = form.querySelector('[data-close-note-required]');

    function syncCloseReason() {
        if (noteRequired && reasonSelect) {
            noteRequired.hidden = (reasonSelect.value !== 'other');
        }
    }

    if (reasonSelect) {
        reasonSelect.addEventListener('change', syncCloseReason);
        syncCloseReason();
    }
});

</script>
