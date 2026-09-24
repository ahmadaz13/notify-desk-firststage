// Shared interaction behaviour (P12): sheets, confirmation, "…" menus, busy submit, field errors.
// Server-rendered Blade stays the source of truth; this only opens/closes/focuses what is already there.

const FOCUSABLE = [
    'a[href]', 'button:not([disabled])', 'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])', 'textarea:not([disabled])', 'summary', '[tabindex]:not([tabindex="-1"])',
].join(',');
const FIELDS = 'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])';
const TEXT_TYPES = ['text', 'search', 'tel', 'url', 'email', 'number', 'password', 'date', 'time', 'datetime-local'];

const stack = []; // open sheets, innermost last: { sheet, opener }

const byId = (target) => (typeof target === 'string' ? document.getElementById(target) : target);
const isSheet = (el) => !!el && (el.matches('[data-sheet]') || el.classList.contains('notify-modal-backdrop'));
const panelOf = (sheet) => sheet.querySelector('[role="dialog"], [role="alertdialog"]') || sheet;
const visible = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
const coarse = () => window.matchMedia('(pointer: coarse)').matches;

function focusTarget(panel) {
    const invalid = panel.querySelector('[aria-invalid="true"]');
    if (invalid && visible(invalid)) { return invalid; }
    const preferred = panel.querySelector('[data-autofocus]');
    if (preferred) { return preferred; }
    const field = [...panel.querySelectorAll(FIELDS)].find(visible);
    // On touch devices do not pop the keyboard over the sheet: focus the dialog itself instead.
    if (field && !(coarse() && TEXT_TYPES.includes(field.type) || coarse() && field.tagName === 'TEXTAREA')) { return field; }
    return panel;
}

export function openSheet(target, opener) {
    const sheet = byId(target);
    if (!isSheet(sheet)) { return false; }
    if (!sheet.hidden) { return true; }
    closeMenus();
    sheet.hidden = false;
    stack.push({ sheet, opener: opener || document.activeElement });
    document.body.classList.add('notify-sheet-open');
    const panel = panelOf(sheet);
    focusTarget(panel).focus({ preventScroll: false });
    sheet.dispatchEvent(new CustomEvent('notify:sheet-open', { bubbles: true }));
    return true;
}

export function closeSheet(target) {
    const sheet = isSheet(byId(target)) ? byId(target) : byId(target)?.closest?.('[data-sheet], .notify-modal-backdrop');
    if (!sheet || sheet.hidden) { return; }
    sheet.hidden = true;
    const index = stack.findIndex((entry) => entry.sheet === sheet);
    const entry = index >= 0 ? stack.splice(index, 1)[0] : null;
    if (!stack.length) { document.body.classList.remove('notify-sheet-open'); }
    if (entry?.opener && document.body.contains(entry.opener) && typeof entry.opener.focus === 'function') {
        entry.opener.focus({ preventScroll: true });
    }
}

const topSheet = () => stack[stack.length - 1]?.sheet || null;

// ---------------------------------------------------------------- confirmation

let pending = null; // { form, submitter }

function askConfirm(form, submitter) {
    const source = submitter?.dataset.confirm ? submitter : form;
    const dialog = document.getElementById('notify-confirm');
    if (!dialog) { return false; }
    const accept = dialog.querySelector('[data-confirm-accept]');
    dialog.querySelector('[data-confirm-text]').textContent = source.dataset.confirm;
    const title = dialog.querySelector('#notify-confirm-title');
    title.textContent = source.dataset.confirmTitle || title.dataset.default || title.textContent;
    title.dataset.default ||= title.textContent;
    accept.textContent = source.dataset.confirmLabel || accept.dataset.default || accept.textContent;
    accept.dataset.default ||= accept.textContent;
    const danger = (source.dataset.confirmTone || 'danger') === 'danger';
    accept.classList.toggle('notify-button--danger', danger);
    accept.classList.toggle('notify-button--primary', !danger);
    panelOf(dialog).setAttribute('aria-describedby', 'notify-confirm-text');
    pending = { form, submitter };
    openSheet(dialog, submitter || form.querySelector('[type="submit"]'));
    dialog.querySelector('[data-sheet-close]')?.focus();
    return true;
}

// ---------------------------------------------------------------- "…" menus

function closeMenus(except) {
    document.querySelectorAll('details[data-menu][open], details.notify-menu[open]').forEach((menu) => {
        if (menu !== except) { menu.open = false; }
    });
}

function menuItems(menu) {
    return [...menu.querySelectorAll('.notify-menu__panel [role="menuitem"], .notify-menu__panel .notify-menu__item')]
        .filter((item) => !item.disabled && visible(item));
}

function placeMenu(menu) {
    const panel = menu.querySelector('.notify-menu__panel');
    if (!panel) { return; }
    panel.classList.remove('is-flipped', 'is-above');
    const rect = panel.getBoundingClientRect();
    const width = document.documentElement.clientWidth;
    if (rect.left < 8 || rect.right > width - 8) { panel.classList.add('is-flipped'); }
    if (rect.bottom > window.innerHeight - 8 && rect.height < menu.getBoundingClientRect().top) { panel.classList.add('is-above'); }
}

// ---------------------------------------------------------------- busy submit

function markBusy(form, submitter) {
    // After the submit event the entry list is already built, so the submitter's name/value is kept.
    setTimeout(() => {
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach((button) => {
            button.disabled = true;
        });
        if (submitter) { submitter.classList.add('is-busy'); }
    }, 0);
}

function resetBusy() {
    document.querySelectorAll('form[aria-busy="true"]').forEach((form) => {
        form.removeAttribute('aria-busy');
        form.querySelectorAll('button[disabled].is-busy, button[disabled], input[type="submit"][disabled]').forEach((button) => {
            if (!button.hasAttribute('data-keep-disabled')) { button.disabled = false; }
            button.classList.remove('is-busy');
        });
    });
}

// ---------------------------------------------------------------- field errors

function wireInvalidFields(root = document) {
    root.querySelectorAll('[data-form-field][data-invalid]').forEach((field) => {
        const error = field.querySelector('.notify-form-field__error');
        field.querySelectorAll(FIELDS).forEach((control) => {
            control.setAttribute('aria-invalid', 'true');
            if (error?.id && !(control.getAttribute('aria-describedby') || '').includes(error.id)) {
                control.setAttribute('aria-describedby', `${control.getAttribute('aria-describedby') || ''} ${error.id}`.trim());
            }
        });
    });
}

// ---------------------------------------------------------------- wiring

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-open-sheet]');
    if (opener) {
        event.preventDefault();
        const menu = opener.closest('details[data-menu], details.notify-menu');
        if (menu) { menu.open = false; }
        openSheet(opener.getAttribute('data-open-sheet'), menu ? menu.querySelector('summary') : opener);
        return;
    }

    if (event.target.closest('[data-confirm-accept]') && pending) {
        const { form, submitter } = pending;
        pending = null;
        form.dataset.confirmed = '1';
        closeSheet('notify-confirm');
        if (typeof form.requestSubmit === 'function') { form.requestSubmit(submitter || undefined); } else { form.submit(); }
        return;
    }

    const closer = event.target.closest('[data-sheet-close], [data-close-action-modal]');
    if (closer) {
        event.preventDefault();
        if (closer.closest('#notify-confirm')) { pending = null; }
        closeSheet(closer.closest('[data-sheet], .notify-modal-backdrop'));
        return;
    }

    if (event.target.classList?.contains('notify-modal-backdrop')) {
        closeSheet(event.target);
        return;
    }

    const insideMenu = event.target.closest('details[data-menu], details.notify-menu');
    closeMenus(insideMenu);
    if (insideMenu && event.target.closest('.notify-menu__item') && !event.target.closest('form')) {
        insideMenu.open = false;
    }
});

document.addEventListener('toggle', (event) => {
    const menu = event.target;
    if (!(menu instanceof HTMLDetailsElement) || !(menu.matches('[data-menu], .notify-menu'))) { return; }
    const trigger = menu.querySelector('summary');
    trigger?.setAttribute('aria-expanded', menu.open ? 'true' : 'false');
    if (menu.open) {
        closeMenus(menu);
        placeMenu(menu);
    }
}, true);

document.addEventListener('keydown', (event) => {
    const openMenu = document.querySelector('details[data-menu][open], details.notify-menu[open]');

    if (event.key === 'Escape') {
        if (openMenu) {
            openMenu.open = false;
            openMenu.querySelector('summary')?.focus();
            event.preventDefault();
            return;
        }
        const sheet = topSheet();
        if (sheet) {
            if (sheet.id === 'notify-confirm') { pending = null; }
            closeSheet(sheet);
            event.preventDefault();
        }
        return;
    }

    if (openMenu && ['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key) && openMenu.contains(document.activeElement)) {
        const items = menuItems(openMenu);
        if (!items.length) { return; }
        const current = items.indexOf(document.activeElement);
        const next = {
            ArrowDown: current < 0 ? 0 : (current + 1) % items.length,
            ArrowUp: current <= 0 ? items.length - 1 : current - 1,
            Home: 0,
            End: items.length - 1,
        }[event.key];
        items[next].focus();
        event.preventDefault();
        return;
    }

    if (event.key === 'Tab') {
        const sheet = topSheet();
        if (!sheet) { return; }
        const focusables = [...panelOf(sheet).querySelectorAll(FOCUSABLE)].filter(visible);
        if (!focusables.length) { event.preventDefault(); return; }
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        if (event.shiftKey && (document.activeElement === first || !panelOf(sheet).contains(document.activeElement))) {
            last.focus();
            event.preventDefault();
        } else if (!event.shiftKey && (document.activeElement === last || !panelOf(sheet).contains(document.activeElement))) {
            first.focus();
            event.preventDefault();
        }
    }
});

// Confirmation runs first (capture), then busy state for submissions that really go ahead.
document.addEventListener('submit', (event) => {
    const form = event.target;
    const submitter = event.submitter || null;
    const needsConfirm = (submitter && submitter.dataset.confirm) || form.dataset.confirm;
    if (needsConfirm && form.dataset.confirmed !== '1') {
        if (askConfirm(form, submitter)) { event.preventDefault(); event.stopImmediatePropagation(); }
    }
}, true);

document.addEventListener('submit', (event) => {
    const form = event.target;
    delete form.dataset.confirmed;
    // getAttribute: a field named "method" would shadow form.method.
    if (event.defaultPrevented || (form.getAttribute('method') || 'get').toLowerCase() !== 'post' || form.hasAttribute('data-no-busy')) { return; }
    markBusy(form, event.submitter || null);
});

// Back/forward cache restores a page with its submit buttons still disabled.
window.addEventListener('pageshow', (event) => { if (event.persisted) { resetBusy(); } });

function boot() {
    wireInvalidFields();
    const reopen = document.querySelector('[data-sheet-reopen]');
    if (reopen) {
        openSheet(reopen);
        return;
    }
    // Full-page forms: take the user to the first invalid field.
    const invalid = document.querySelector('main [aria-invalid="true"]');
    if (invalid && visible(invalid)) {
        invalid.scrollIntoView({ block: 'center' });
        invalid.focus({ preventScroll: true });
    }
}

const NotifyUI = { openSheet, closeSheet, hasOpenSheet: () => stack.length > 0 };
window.NotifyUI = NotifyUI;
// P10 workspace scripts call these names.
window.openModal = (id, opener) => openSheet(id, opener);
window.closeModal = (sheet) => closeSheet(sheet);

boot();

export default NotifyUI;
