{{-- Client Workspace behaviour (P10/P12). Generic sheet/confirm/menu behaviour is shared (resources/js/interactions.js);
     this keeps only workspace specifics: deep-link sheets, the Today return context and conditional fields. --}}
<script>
(function () {
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

    // Opened from Today (P11): workspace actions return to the same Today view once they succeed.
    var params = new URLSearchParams(window.location.search);
    var returnTo = params.get('from');
    if (returnTo === 'today' || returnTo === 'work') {
        document.querySelectorAll('[data-sheet] form[method="POST"], [data-client-review] form[method="POST"]').forEach(function (form) {
            [['_return_to', returnTo], ['_return_scope', params.get('scope') === 'my' ? 'my' : 'all']].forEach(function (pair) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = pair[0];
                input.value = pair[1];
                form.appendChild(input);
            });
        });
    }

    // Deep links open after the shared module has booted; a validation reopen always wins (P12).
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.NotifyUI || window.NotifyUI.hasOpenSheet()) { return; }
        var initial = openSheetId || hashSheets[window.location.hash];
        if (initial && window.NotifyUI.openSheet(initial)) { return; }
        if (hashTargets[window.location.hash]) {
            var target = document.querySelector(hashTargets[window.location.hash]);
            if (target) { target.scrollIntoView({ block: 'start' }); }
        }
    });
})();

// Conditional sections (P12): a hidden branch is also disabled, so only the chosen branch is submitted.
// (Before P12 hidden defaults were posted too — e.g. the "no answer" callback time overrode a
// "call back later" date the user had picked.)
function showSection(group, visible) {
    group.hidden = !visible;
    group.querySelectorAll('input, select, textarea').forEach(function (field) { field.disabled = !visible; });
}

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
            showSection(group, group.getAttribute('data-for-outcome') === val);
        });

        if (noteContainer) {
            showSection(noteContainer, !!val && val !== 'no_answer_busy');
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
            showSection(group, group.getAttribute('data-for-decision') === decVal);
        });

        syncAttendedChoice();
    }

    function syncAttendedChoice() {
        var checkedChoice = form.querySelector('input[name="attended_choice"]:checked');
        var choiceVal = checkedChoice ? checkedChoice.value : null;
        var attendedOpen = !!form.querySelector('[data-for-decision="attended"]:not([hidden])');

        attendedGroups.forEach(function(group) {
            showSection(group, attendedOpen && group.getAttribute('data-for-attended-choice') === choiceVal);
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
            showSection(group, group.getAttribute('data-for-followup-outcome') === val);
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
