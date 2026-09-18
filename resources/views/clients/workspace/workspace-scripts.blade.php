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
        initGuidedSubscription();
        return;
    }

    var triggerRecordPayment = e.target.closest('[data-trigger-record-payment]');
    if (triggerRecordPayment) {
        e.preventDefault();
        openModal('modal-record-payment');
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

// 5. Guided Subscription Form Handlers
var guidedCatalog = null;
var guidedCatalogLoaded = false;

function initGuidedSubscription() {
    var form = document.getElementById('guided-subscription-form');
    if (!form) return;

    var catalogUrl = form.getAttribute('data-catalog-url');
    var productSelect = document.getElementById('guided-sub-product');
    var planSelect = document.getElementById('guided-sub-plan');
    var intervalSelect = document.getElementById('guided-sub-interval');
    var paymentTermsSelect = document.getElementById('guided-sub-payment-terms');
    var countSelect = document.getElementById('guided-sub-installments-count');
    var dueDaySelect = document.getElementById('guided-sub-due-day');
    var startDateInput = document.getElementById('guided-sub-start-date');
    var submitBtn = document.getElementById('guided-sub-submit-btn');
    var previewCard = document.getElementById('guided-sub-preview-card');
    var errorDiv = document.getElementById('guided-sub-error');

    if (!guidedCatalogLoaded) {
        fetch(catalogUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success || !data.catalog) return;
            guidedCatalog = data.catalog;
            guidedCatalogLoaded = true;

            productSelect.innerHTML = '<option value="">اختر المنتج...</option>';
            guidedCatalog.forEach(function(prod) {
                var opt = document.createElement('option');
                opt.value = prod.id;
                opt.textContent = prod.name_ar || prod.name_en;
                productSelect.appendChild(opt);
            });
        })
        .catch(function(err) { console.error('Failed to load catalog:', err); });
    }

    function onProductChange() {
        var prodId = parseInt(productSelect.value, 10);
        planSelect.innerHTML = '<option value="">اختر الباقة...</option>';
        planSelect.disabled = true;
        intervalSelect.innerHTML = '<option value="">اختر المدة...</option>';
        intervalSelect.disabled = true;
        hidePreview();

        if (!prodId || !guidedCatalog) return;
        var prod = guidedCatalog.find(function(p) { return p.id === prodId; });
        if (!prod || !prod.plans || !prod.plans.length) return;

        prod.plans.forEach(function(plan) {
            var opt = document.createElement('option');
            opt.value = plan.id;
            opt.textContent = plan.name_ar || plan.name_en;
            planSelect.appendChild(opt);
        });
        planSelect.disabled = false;
    }

    function onPlanChange() {
        var prodId = parseInt(productSelect.value, 10);
        var planId = parseInt(planSelect.value, 10);
        intervalSelect.innerHTML = '<option value="">اختر المدة...</option>';
        intervalSelect.disabled = true;
        hidePreview();

        if (!prodId || !planId || !guidedCatalog) return;
        var prod = guidedCatalog.find(function(p) { return p.id === prodId; });
        if (!prod) return;
        var plan = prod.plans.find(function(pl) { return pl.id === planId; });
        if (!plan || !plan.available_intervals) return;

        plan.available_intervals.forEach(function(interval) {
            var opt = document.createElement('option');
            opt.value = interval;
            opt.textContent = interval === 'annual' ? 'سنوي' : 'شهري';
            intervalSelect.appendChild(opt);
        });
        intervalSelect.disabled = false;
    }

    function onIntervalChange() {
        var interval = intervalSelect.value;
        var annualContainer = document.getElementById('guided-annual-terms-container');
        if (annualContainer) {
            annualContainer.style.display = (interval === 'annual') ? 'block' : 'none';
        }
        syncPaymentTerms();
        fetchPreview();
    }

    function syncPaymentTerms() {
        var isAnnual = intervalSelect.value === 'annual';
        var isInstallments = isAnnual && paymentTermsSelect && paymentTermsSelect.value === 'installments';
        var installConfig = document.getElementById('guided-installments-config');
        if (installConfig) {
            installConfig.style.display = isInstallments ? 'grid' : 'none';
        }
    }

    function hidePreview() {
        if (previewCard) previewCard.style.display = 'none';
        if (errorDiv) { errorDiv.style.display = 'none'; errorDiv.textContent = ''; }
        if (submitBtn) submitBtn.disabled = true;
    }

    var previewDebounceTimer = null;
    function fetchPreview() {
        var prodId = productSelect.value;
        var planId = planSelect.value;
        var interval = intervalSelect.value;

        if (!prodId || !planId || !interval) {
            hidePreview();
            return;
        }

        clearTimeout(previewDebounceTimer);
        previewDebounceTimer = setTimeout(function() {
            var previewUrl = form.getAttribute('data-preview-url');
            var payload = {
                product_id: prodId,
                plan_id: planId,
                billing_interval: interval,
                payment_terms: interval === 'annual' ? (paymentTermsSelect ? paymentTermsSelect.value : 'full') : 'full',
                installments_count: countSelect ? countSelect.value : 1,
                installment_due_day: dueDaySelect ? dueDaySelect.value : 1,
                start_date: startDateInput ? startDateInput.value : null
            };

            var csrfToken = form.querySelector('input[name="_token"]')?.value;

            fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json().then(function(data) { return { status: res.status, data: data }; }); })
            .then(function(result) {
                if (result.status >= 400 || !result.data.success) {
                    var errorMsg = result.data.message || (result.data.errors ? Object.values(result.data.errors)[0][0] : 'حدث خطأ في جلب التسعير');
                    if (errorDiv) {
                        errorDiv.textContent = errorMsg;
                        errorDiv.style.display = 'block';
                    }
                    if (previewCard) previewCard.style.display = 'none';
                    if (submitBtn) submitBtn.disabled = true;
                    return;
                }

                if (errorDiv) errorDiv.style.display = 'none';
                var d = result.data;
                document.getElementById('preview-unit-price').textContent = d.unit_price_formatted + ' د.أ';
                document.getElementById('preview-setup-fee').textContent = d.setup_fee_formatted + ' د.أ';
                document.getElementById('preview-tax').textContent = d.tax_formatted + ' د.أ';
                document.getElementById('preview-total').textContent = d.total_formatted + ' د.أ';

                var scheduleContainer = document.getElementById('preview-schedule-container');
                var scheduleTbody = document.getElementById('preview-schedule-tbody');
                if (d.schedule && d.schedule.length > 0) {
                    scheduleTbody.innerHTML = '';
                    d.schedule.forEach(function(item) {
                        var tr = document.createElement('tr');
                        tr.innerHTML = '<td style="padding:4px">' + item.sequence + '</td>'
                                     + '<td style="padding:4px">' + item.due_date + '</td>'
                                     + '<td style="padding:4px;font-weight:700">' + item.amount_due_formatted + ' د.أ</td>';
                        scheduleTbody.appendChild(tr);
                    });
                    scheduleContainer.style.display = 'block';
                } else {
                    scheduleContainer.style.display = 'none';
                }

                previewCard.style.display = 'block';
                submitBtn.disabled = false;
            })
            .catch(function(err) {
                console.error(err);
                if (errorDiv) {
                    errorDiv.textContent = 'تعذر الاتصال بالخادم لاحتساب التسعير.';
                    errorDiv.style.display = 'block';
                }
                if (submitBtn) submitBtn.disabled = true;
            });
        }, 150);
    }

    productSelect.onchange = onProductChange;
    planSelect.onchange = onPlanChange;
    intervalSelect.onchange = onIntervalChange;
    if (paymentTermsSelect) paymentTermsSelect.onchange = function() {
        syncPaymentTerms();
        fetchPreview();
    };
    if (countSelect) countSelect.onchange = fetchPreview;
    if (dueDaySelect) dueDaySelect.onchange = fetchPreview;
    if (startDateInput) startDateInput.onchange = fetchPreview;

    form.onsubmit = function() {
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'جاري الحفظ والاعتماد...';
        }
    };
}
</script>
