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

// Global trigger for call outcome modal
document.addEventListener('click', function(e) {
    var trigger = e.target.closest('[data-trigger-call-outcome]');
    if (trigger) {
        e.preventDefault();
        var outcomeOpener = document.querySelector('[data-contact-outcome-open]');
        if (outcomeOpener) {
            outcomeOpener.click();
        }
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

document.querySelectorAll('[data-paid-subscription-form]').forEach(function(form) {
    var annualTerms = form.querySelector('[data-annual-payment-terms]');
    var paymentTerms = form.querySelector('[data-payment-terms]');
    var installmentFields = form.querySelector('[data-installment-fields]');
    var installmentInputs = installmentFields ? installmentFields.querySelectorAll('input, select') : [];

    function syncBillingTerms() {
        var selectedPrice = form.querySelector('input[name="plan_price_id"]:checked');
        var isAnnual = selectedPrice && selectedPrice.getAttribute('data-billing-interval') === 'annual';
        var usesInstallments = isAnnual && paymentTerms && paymentTerms.value === 'installments';

        if (annualTerms) annualTerms.hidden = !isAnnual;
        if (installmentFields) installmentFields.hidden = !usesInstallments;
        if (paymentTerms) paymentTerms.disabled = !isAnnual;
        installmentInputs.forEach(function(input) {
            input.disabled = !usesInstallments;
            input.required = usesInstallments;
        });
    }

    form.querySelectorAll('input[name="plan_price_id"]').forEach(function(input) {
        input.addEventListener('change', syncBillingTerms);
    });
    if (paymentTerms) paymentTerms.addEventListener('change', syncBillingTerms);
    syncBillingTerms();
});
</script>
