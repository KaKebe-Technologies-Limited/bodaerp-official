// BodaERP - Core JavaScript (loaded once per page from partials/scripts-footer.php)
if (window.__bodaMainLoaded) console.warn('main.js was included twice; its event handlers are registered only once.');

if (!window.__bodaMainLoaded) document.addEventListener('DOMContentLoaded', function() {
    if (window.bootstrap) document.querySelectorAll('[data-boda-toast]').forEach(el => bootstrap.Toast.getOrCreateInstance(el).show());
    
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const setDrawer = (open) => {
        if (!sidebar) return;
        sidebar.classList.toggle('open', open);
        if (overlay) overlay.classList.toggle('active', open);
        if (toggleBtn) toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) { const first = sidebar.querySelector('a'); if (first) first.focus(); }
    };
    if (toggleBtn) toggleBtn.addEventListener('click', (e) => { e.stopPropagation(); setDrawer(!sidebar.classList.contains('open')); });
    if (overlay) overlay.addEventListener('click', () => setDrawer(false));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) { setDrawer(false); if (toggleBtn) toggleBtn.focus(); }
    });
    document.querySelectorAll('.nav-tree a').forEach(link => {
        link.addEventListener('click', () => { if (window.innerWidth < 992) setDrawer(false); });
    });
    const closeBtn = document.querySelector('[data-sidebar-close]');
    if (closeBtn) closeBtn.addEventListener('click', () => { setDrawer(false); if (toggleBtn) toggleBtn.focus(); });

    // Sidebar groups: each dropdown stays open until its own button closes it (owner, 2026-11-06), and the
    // open set is remembered between pages. A folded sidebar unfolds first, then opens the group.
    const root = document.documentElement;
    const foldBtn = document.querySelector('[data-sidebar-fold]');
    const setFolded = (folded) => {
        root.classList.toggle('sidebar-folded', folded);
        if (foldBtn) {
            foldBtn.setAttribute('aria-pressed', folded ? 'true' : 'false');
            foldBtn.setAttribute('aria-label', folded ? 'Show the menu with labels' : 'Fold the menu to icons');
        }
        try { localStorage.setItem('bodaerp-sidebar-folded', folded ? '1' : ''); } catch (storageError) { console.warn('Menu choice not saved', storageError); }
    };
    if (foldBtn) {
        setFolded(root.classList.contains('sidebar-folded'));
        foldBtn.addEventListener('click', () => setFolded(!root.classList.contains('sidebar-folded')));
    }
    const groups = document.querySelectorAll('[data-nav-group]');
    const openGroup = (btn, open) => {
        const list = document.getElementById(btn.getAttribute('aria-controls'));
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.closest('.nav-node').classList.toggle('is-open', open);
        if (list) list.hidden = !open;
    };
    const openKey = 'bodaerp-nav-open';
    let remembered = [];
    try { remembered = JSON.parse(localStorage.getItem(openKey) || '[]'); } catch (storageError) { console.warn('Menu state not readable', storageError); }
    const saveOpen = () => {
        const ids = Array.from(groups).filter(g => g.getAttribute('aria-expanded') === 'true').map(g => g.getAttribute('aria-controls'));
        try { localStorage.setItem(openKey, JSON.stringify(ids)); } catch (storageError) { console.warn('Menu state not saved', storageError); }
    };
    groups.forEach(g => { if (remembered.indexOf(g.getAttribute('aria-controls')) !== -1) openGroup(g, true); });
    groups.forEach(btn => btn.addEventListener('click', () => {
        if (root.classList.contains('sidebar-folded') && window.innerWidth >= 992) { setFolded(false); openGroup(btn, true); saveOpen(); return; }
        openGroup(btn, btn.getAttribute('aria-expanded') !== 'true');
        saveOpen();
    }));

    document.querySelectorAll('.alert-success:not([data-keep]), .alert-info[data-autohide]').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 6000);
    });

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href.length <= 1) return;
            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    document.querySelectorAll('.current-year').forEach(el => {
        el.textContent = new Date().getFullYear();
    });

    document.querySelectorAll('textarea[data-maxlength]').forEach(textarea => {
        const max = parseInt(textarea.dataset.maxlength);
        const counter = document.createElement('small');
        counter.className = 'text-muted d-block text-end mt-1';
        counter.textContent = `0 / ${max}`;
        textarea.parentNode.appendChild(counter);
        
        textarea.addEventListener('input', function() {
            const len = this.value.length;
            counter.textContent = `${len} / ${max}`;
            if (len > max) {
                counter.style.color = '#dc3545';
            } else {
                counter.style.color = '';
            }
        });
    });

    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.closest('.input-group').querySelector('input');
            if (input.type === 'password') {
                input.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                input.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });

});

function formatCurrency(amount) {
    return 'UGX ' + Number(amount).toLocaleString('en-US');
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('en-UG', { day: '2-digit', month: 'short', year: 'numeric' });
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer') || createToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast toast-${type} fade show`;
    toast.role = 'alert';
    toast.style.animation = 'slideInRight 0.4s ease forwards';
    toast.innerHTML = `
        <div class="toast-body d-flex align-items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 6000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

// Shared behaviours, driven by data attributes rather than inline handlers:
//   <form data-confirm="Delete Jane Akello?" data-confirm-ok="Delete" data-confirm-title="..." data-confirm-typed="DELETE">  confirm dialog (controls.js)
//   <select data-autosubmit>                                           submits its form on change
//   <button data-print>                                                prints the page
//   <input type="checkbox" data-check-all="ids[]">                     ticks every ids[] checkbox in the form
//   <button data-copy="text"> or <button data-copy-from="elementId">   copies text to the clipboard
//   <div data-click-target="inputId">                                  clicking or Enter opens that file input
//   <input type="file" data-file-name-target="spanId">                 shows the chosen file name
//   <input data-preview-target="imgId" data-preview-base="/app">       shows the typed path in an image
//   <img data-fallback-src="..."> or <img data-hide-broken>            handles an image that fails to load
//   <button data-call="fn" data-call-args='[1]'>                       calls a function listed in PAGE_ACTIONS
//   <input data-duplicate-check="nin" data-duplicate-url="...">        warns on leaving the field if the value is already registered
//   <select data-amount-target="inputId"> with <option data-amount>     shows the chosen option's amount in that input
// Every POST form locks its submit button while the request runs, so a double tap cannot pay twice.
(function () {
    'use strict';
    if (window.__bodaMainLoaded) return;

    // Named explicitly so a data-call attribute can never reach eval, fetch or any other global.
    // tests/check_portability.php fails when markup names a function missing from this list.
    const PAGE_ACTIONS = new Set([
        'exportTableCSV', 'exportTableExcel', 'exportTablePDF',
        'onSliderInput', 'openAdd', 'openAddCity', 'openAddStage', 'openEdit', 'openEditCity', 'openEditStage',
        'openReview', 'prepareSubmit', 'reconcilePending', 'resetSplit', 'submitPayNow',
        'updateCsSplitTotal', 'updateDefaultSplitTotal', 'updateSplitTotal'
    ]);

    function runPageAction(name, el) {
        if (!PAGE_ACTIONS.has(name) || typeof window[name] !== 'function') {
            console.error('Page action not available:', name);
            return undefined;
        }
        let args = [];
        const rawArgs = el.getAttribute('data-call-args');
        if (rawArgs) {
            try {
                args = JSON.parse(rawArgs);
            } catch (parseError) {
                console.error('Page action has unreadable arguments:', name, parseError);
                return undefined;
            }
        }
        return window[name].apply(el, args);
    }

    function replaceBrokenImage(img) {
        if (img.hasAttribute('data-hide-broken')) { img.style.display = 'none'; return; }
        const fallback = img.getAttribute('data-fallback-src');
        if (fallback && img.getAttribute('src') !== fallback) img.setAttribute('src', fallback);
    }
    // Image errors do not bubble, so this listens in the capture phase.
    document.addEventListener('error', function (e) {
        if (e.target instanceof HTMLImageElement) replaceBrokenImage(e.target);
    }, true);
    // This script loads last, so some images have already failed by the time it runs.
    document.querySelectorAll('img[data-hide-broken], img[data-fallback-src]').forEach(img => {
        if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) replaceBrokenImage(img);
    });

    document.addEventListener('input', function (e) {
        if (!e.target.getAttribute) return;
        const name = e.target.getAttribute('data-call-input');
        if (name) runPageAction(name, e.target);
        const preview = document.getElementById(e.target.getAttribute('data-preview-target') || '');
        if (preview) {
            const path = e.target.value.trim();
            preview.src = (path.startsWith('/') ? (e.target.getAttribute('data-preview-base') || '') : '') + path;
        }
    });

    // One dialog for the whole platform (controls.js). If it failed to load, the action does not go ahead.
    function confirmDialog(message, okLabel, danger, extra) {
        if (!window.BodaUI) { console.error('controls.js is missing: confirmation refused for', message); return Promise.resolve(false); }
        return window.BodaUI.confirm(Object.assign({ message: message, okLabel: okLabel, tone: danger ? 'danger' : 'default' }, extra || {}));
    }
    window.bodaConfirm = confirmDialog;

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        const submitAction = form.getAttribute('data-call-submit');
        if (submitAction && runPageAction(submitAction, form) === false) { e.preventDefault(); return; }
        let message = form.getAttribute('data-confirm');
        if (message && form.dataset.confirmed !== '1') {
            e.preventDefault();
            // Bulk forms: {count} is the number of ticked rows, {action} the chosen bulk action.
            if (message.indexOf('{count}') !== -1) {
                const ticked = form.querySelectorAll('input[type="checkbox"]:checked:not([data-check-all])').length;
                if (!ticked) { confirmDialog('Tick at least one row in the list, then choose the action again.', 'OK', false, { title: 'Nothing is selected' }); return; }
                const bulk = form.querySelector('select[name="bulk"], select[name="bulk_action"], select[name="op"]');
                const chosen = bulk && bulk.selectedOptions[0] ? bulk.selectedOptions[0].textContent.trim() : 'this action';
                message = message.replace('{count}', String(ticked)).replace('{action}', chosen);
            }
            const danger = /delete|remove|suspend|revoke|reset|discard/i.test(message);
            confirmDialog(message, form.getAttribute('data-confirm-ok') || (danger ? 'Yes, continue' : 'Continue'), danger, {
                title: form.getAttribute('data-confirm-title') || undefined,
                typed: form.getAttribute('data-confirm-typed') || undefined
            }).then(yes => {
                if (!yes) return;
                form.dataset.confirmed = '1';
                if (e.submitter && e.submitter.name) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden'; hidden.name = e.submitter.name; hidden.value = e.submitter.value;
                    form.appendChild(hidden);
                }
                form.requestSubmit ? form.requestSubmit() : form.submit();
            });
            return;
        }
        // getAttribute, not form.method: a field named "method" (Collect payments has one) shadows the property.
        if ((form.getAttribute('method') || '').toLowerCase() !== 'post' || form.hasAttribute('data-no-lock') || e.defaultPrevented) return;
        if (form.dataset.busy === '1') { e.preventDefault(); return; }
        form.dataset.busy = '1';
        const btn = e.submitter || form.querySelector('[type="submit"]');
        if (btn && btn.tagName === 'BUTTON') {
            btn.dataset.label = btn.innerHTML;
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>' + (btn.getAttribute('data-busy-label') || 'Working');
            if (btn.name) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden'; hidden.name = btn.name; hidden.value = btn.value;
                form.appendChild(hidden);
            }
        }
    });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-busy="1"]').forEach(form => {
            form.dataset.busy = ''; form.dataset.confirmed = '';
            form.querySelectorAll('button[aria-busy="true"]').forEach(b => { b.disabled = false; b.removeAttribute('aria-busy'); if (b.dataset.label) b.innerHTML = b.dataset.label; });
        });
    });

    document.addEventListener('change', function (e) {
        const el = e.target;
        if (el.matches && el.matches('[data-check-all]')) {
            const form = el.closest('form') || document;
            form.querySelectorAll('input[type="checkbox"][name="' + el.getAttribute('data-check-all') + '"]').forEach(cb => { cb.checked = el.checked; });
        }
        if (el.matches && el.matches('select[data-amount-target]')) {
            const target = document.getElementById(el.getAttribute('data-amount-target'));
            const chosen = el.selectedOptions[0];
            if (target && chosen && chosen.dataset.amount) target.value = chosen.dataset.amount;
        }
        if (el.matches && el.matches('[data-autosubmit]') && el.form) el.form.requestSubmit ? el.form.requestSubmit() : el.form.submit();
        if (el.matches && el.matches('input[type="file"][data-file-name-target]')) {
            const out = document.getElementById(el.getAttribute('data-file-name-target'));
            if (out) out.textContent = el.files && el.files[0] ? el.files[0].name : '';
        }
    });

    document.addEventListener('click', function (e) {
        const actionEl = e.target.closest('[data-call]');
        if (actionEl) {
            if (actionEl.tagName === 'A') e.preventDefault();
            runPageAction(actionEl.getAttribute('data-call'), actionEl);
            return;
        }
        const copyBtn = e.target.closest('[data-copy], [data-copy-from]');
        if (copyBtn) {
            const copySource = document.getElementById(copyBtn.getAttribute('data-copy-from') || '');
            const text = copySource ? copySource.textContent.trim() : copyBtn.getAttribute('data-copy');
            const done = () => { const old = copyBtn.innerHTML; copyBtn.innerHTML = '<i class="fas fa-check me-1" aria-hidden="true"></i>Copied'; setTimeout(() => { copyBtn.innerHTML = old; }, 1600); };
            const manualCopy = () => window.BodaUI && window.BodaUI.showText({ title: 'Copy this text', message: 'Your browser did not allow automatic copying. Select the text and copy it.', text: text });
            if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, (err) => { console.warn('Clipboard refused', err); manualCopy(); });
            else manualCopy();
            return;
        }
        const logout = e.target.closest('[data-logout]');
        if (logout) { e.preventDefault(); const f = document.getElementById('logoutForm'); if (f) f.submit(); return; }
        const printBtn = e.target.closest('[data-print]');
        if (printBtn) { e.preventDefault(); window.print(); return; }
        const opener = e.target.closest('[data-click-target]');
        if (opener) { const input = document.getElementById(opener.getAttribute('data-click-target')); if (input) input.click(); }
    });
    // Registration: say straight away when a NIN, phone or plate is already registered (the server checks again on submit).
    document.addEventListener('focusout', function (e) {
        const input = e.target;
        if (!input.matches || !input.matches('[data-duplicate-check]')) return;
        const note = document.getElementById(input.getAttribute('aria-describedby') || '');
        const value = input.value.trim();
        if (!note) return;
        if (value.length < 4 || value === input.dataset.checkedValue) return;
        input.dataset.checkedValue = value;
        const url = input.getAttribute('data-duplicate-url') + '?field=' + encodeURIComponent(input.getAttribute('data-duplicate-check')) + '&value=' + encodeURIComponent(value);
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : { matches: [] })
            .then(result => {
                const matches = result.matches || [];
                note.textContent = matches.length ? 'Already registered at ' + matches.map(m => m.where + (m.status === 'removed' ? ' (removed record)' : '')).join('; ') + '.' : '';
                note.classList.toggle('is-warning', matches.length > 0);
                const reveal = document.getElementById(input.getAttribute('data-duplicate-reveal') || '');
                if (reveal) reveal.classList.toggle('d-none', matches.length === 0);
            })
            .catch(err => { console.warn('Duplicate check failed', err); note.textContent = ''; });
    });

    document.addEventListener('keydown', function (e) {
        const opener = e.target.closest && e.target.closest('[data-click-target]');
        if (opener && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); const input = document.getElementById(opener.getAttribute('data-click-target')); if (input) input.click(); }
    });
})();
window.__bodaMainLoaded = true;
