<script>
function switchTab(tabName) {
    var target = document.getElementById('sec-' + tabName) || document.getElementById('tab-' + tabName) || document.getElementById(tabName);
    if (target) {
        var details = target.closest('details');
        if (details) details.open = true;
        target.style.display = 'block';
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    document.querySelectorAll('.tab-pane').forEach(function(pane) {
        if (pane !== target && !pane.closest('details')) {
            pane.style.display = 'none';
        }
    });

    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-ghost');
        btn.classList.remove('is-active');
    });

    var targetBtn = document.getElementById('tab-btn-' + tabName);
    if (targetBtn) {
        targetBtn.classList.remove('btn-ghost');
        targetBtn.classList.add('btn-primary');
        targetBtn.classList.add('is-active');
    }
}

function toggleAccordion(tabName) {
    var targetPane = document.getElementById('tab-' + tabName) || document.getElementById('sec-' + tabName);
    var targetHeader = document.getElementById('acc-header-' + tabName);
    if (!targetPane) return;

    var isOpen = targetPane.style.display === 'block';
    if (isOpen) {
        targetPane.style.display = 'none';
        if (targetHeader) {
            targetHeader.classList.remove('active');
            var chevron = targetHeader.querySelector('.chevron-icon');
            if (chevron) chevron.style.transform = 'rotate(0deg)';
        }
    } else {
        switchTab(tabName);
        targetHeader?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function calculateOfferFinal() {
    var p = parseFloat(document.getElementById('offer_price').value) || 0;
    var d = parseFloat(document.getElementById('offer_discount').value) || 0;
    document.getElementById('offer_final').value = Math.max(0, p - d).toFixed(2);
}

function initContactOutcome(root) {
    var panel = root.querySelector('[data-contact-outcome-panel]');
    var overlay = root.querySelector('[data-contact-outcome-overlay]');
    var note = root.querySelector('[data-contact-note]');
    var noteMark = root.querySelector('[data-note-required]');
    var fieldGroups = root.querySelectorAll('[data-outcome-fields]');
    var openers = root.querySelectorAll('[data-contact-outcome-open]');
    var closers = root.querySelectorAll('[data-contact-outcome-close], [data-contact-outcome-overlay]');
    var radios = root.querySelectorAll('input[name="result"]');

    function setOpen(open) {
        panel.hidden = !open;
        overlay.hidden = !open;
        document.documentElement.classList.toggle('notify-panel-open', open);
    }

    function setGroupRequired(group, required) {
        group.querySelectorAll('input, select, textarea').forEach(function(input) {
            if (['appointment_date', 'appointment_time', 'follow_up_date_time'].includes(input.name)) {
                input.required = required;
            }
        });
    }

    function syncOutcome() {
        var selected = root.querySelector('input[name="result"]:checked');
        var value = selected ? selected.value : 'no_contact';

        fieldGroups.forEach(function(group) {
            var active = group.getAttribute('data-outcome-fields') === value;
            group.hidden = !active;
            setGroupRequired(group, active);
        });

        var noteRequired = value === 'not_interested';
        if (note) {
            note.required = noteRequired;
        }
        if (noteMark) {
            noteMark.hidden = !noteRequired;
        }
    }

    openers.forEach(function(button) {
        button.addEventListener('click', function() {
            setOpen(true);
            syncOutcome();
        });
    });

    closers.forEach(function(button) {
        button.addEventListener('click', function() {
            setOpen(false);
        });
    });

    radios.forEach(function(radio) {
        radio.addEventListener('change', syncOutcome);
    });

    syncOutcome();
}

document.querySelectorAll('[data-contact-outcome-root]').forEach(initContactOutcome);

// Modal Open / Close Helpers
function openModal(id) {
    var modal = document.getElementById(id);
    if (modal) {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modal) {
    if (modal) {
        modal.hidden = true;
        document.body.style.overflow = '';
    }
}

// Global triggers for Phase 5 action modals
document.addEventListener('click', function(e) {
    var triggerCall = e.target.closest('[data-trigger-call-outcome]');
    if (triggerCall) {
        e.preventDefault();
        var modalCall = document.getElementById('modal-record-call');
        if (modalCall) {
            openModal('modal-record-call');
        } else {
            var outcomeOpener = document.querySelector('[data-contact-outcome-open]');
            if (outcomeOpener) outcomeOpener.click();
        }
        return;
    }

    var triggerApt = e.target.closest('[data-trigger-create-appointment]');
    if (triggerApt) {
        e.preventDefault();
        openModal('modal-create-appointment');
        return;
    }

    var triggerResult = e.target.closest('[data-trigger-appointment-result]');
    if (triggerResult) {
        e.preventDefault();
        openModal('modal-appointment-result');
        return;
    }

    var triggerInstall = e.target.closest('[data-trigger-installation-modal]');
    if (triggerInstall) {
        e.preventDefault();
        openModal('modal-complete-installation');
        return;
    }

    var triggerFollowup = e.target.closest('[data-trigger-followup-modal]');
    if (triggerFollowup) {
        e.preventDefault();
        openModal('modal-follow-up');
        return;
    }

    var triggerClose = e.target.closest('[data-trigger-close-client]');
    if (triggerClose) {
        e.preventDefault();
        openModal('modal-close-client');
        return;
    }

    var triggerStartSub = e.target.closest('[data-trigger-start-subscription]');
    if (triggerStartSub) {
        e.preventDefault();
        openModal('modal-start-subscription');
        return;
    }

    var triggerRecordPayment = e.target.closest('[data-trigger-record-payment]');
    if (triggerRecordPayment) {
        e.preventDefault();
        openModal('modal-record-payment');
        return;
    }

    var triggerScheduleInstall = e.target.closest('[data-trigger-schedule-installation]');
    if (triggerScheduleInstall) {
        e.preventDefault();
        openModal('modal-schedule-installation');
        return;
    }

    var triggerReopen = e.target.closest('[data-trigger-reopen-client]');
    if (triggerReopen) {
        e.preventDefault();
        openModal('modal-reopen-client');
        return;
    }

    var closer = e.target.closest('[data-close-action-modal]');
    if (closer) {
        e.preventDefault();
        closeModal(closer.closest('.notify-modal-backdrop'));
        return;
    }

    // Click on backdrop background
    if (e.target.classList.contains('notify-modal-backdrop')) {
        closeModal(e.target);
        return;
    }
});

// Escape key closes modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.notify-modal-backdrop:not([hidden])').forEach(closeModal);
    }
});

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

// Global anchor scroll handler that opens parent <details>
document.addEventListener('click', function(e) {
    var link = e.target.closest('a[href^="#"], [data-scroll-to]');
    if (!link) return;

    var targetId = link.getAttribute('data-scroll-to') || link.getAttribute('href');
    if (!targetId || targetId === '#' || !targetId.startsWith('#')) return;

    var targetElem = document.querySelector(targetId);
    if (targetElem) {
        e.preventDefault();
        var details = targetElem.closest('details');
        if (details) {
            details.open = true;
        }
        targetElem.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});

</script>
