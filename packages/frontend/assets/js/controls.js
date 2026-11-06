// BodaERP form controls and dialogs, shared by the public site, the sign-in pages and every portal.
// Browsers draw <select>, date, colour, range, file and number fields, confirm()/prompt(), title tooltips and
// validation bubbles in their own style, which differs between phones and computers. This file replaces each
// one with a control in the BodaERP style. The original element stays in the form, hidden, so the value is
// still posted under the same name and page scripts that read or set .value keep working.
//
//   BodaUI.confirm({ title, message, okLabel, cancelLabel, tone: 'danger', typed: 'DELETE' })  -> Promise<boolean>
//   BodaUI.showText({ title, message, text })                                              -> copy box
//   <select>                      listbox; a filter box appears when there are more than 10 options
//   <input type="date">           text field (day/month/year) with a calendar
//   <input type="range">          slider     <input type="color">  swatch picker     <input type="number">  number field
//   <input type="file">           drop zone with the chosen files listed (data-max-mb="5" checks the size)
//   <input list="id">             suggestions from that <datalist>
//   title="..."                   tooltip on hover and keyboard focus
//   [data-faq-toggle]             accordion button for the FAQ
//   <select data-native> / <input data-native>   left as the browser draws it (none at the moment)
(function () {
    'use strict';
    if (window.BodaUI) return;

    let uid = 0;
    const nextId = (prefix) => prefix + '-' + (++uid);
    const enhancers = new WeakMap();
    const isHidden = (el) => !el || !(el.offsetWidth || el.offsetHeight || el.getClientRects().length);

    function labelFor(el) {
        if (el.getAttribute('aria-label')) return el.getAttribute('aria-label').trim();
        const lab = el.id ? document.querySelector('label[for="' + CSS.escape(el.id) + '"]') : null;
        const wrap = el.closest('label');
        const text = (lab || wrap) ? (lab || wrap).textContent : (el.getAttribute('placeholder') || el.name || '');
        return text.replace(/\*/g, '').replace(/\s+/g, ' ').trim();
    }

    // Keeps the visible control in step when a page script writes .value on the hidden original element.
    function watchValue(el, onChange) {
        const proto = Object.getPrototypeOf(el);
        const desc = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value') || Object.getOwnPropertyDescriptor(proto, 'value');
        Object.defineProperty(el, 'value', {
            configurable: true,
            get() { return desc.get.call(this); },
            set(v) { desc.set.call(this, v); onChange(); }
        });
    }

    function fire(el, type) { el.dispatchEvent(new Event(type, { bubbles: true })); }

    function placePopup(popup, anchor, minWidth) {
        const r = anchor.getBoundingClientRect();
        const vw = document.documentElement.clientWidth, vh = window.innerHeight;
        popup.style.minWidth = Math.min(Math.max(r.width, minWidth || 0), vw - 16) + 'px';
        popup.style.maxWidth = (vw - 16) + 'px';
        popup.style.left = '0px'; popup.style.top = '0px';
        const pw = popup.offsetWidth, ph = popup.offsetHeight;
        const below = vh - r.bottom, above = r.top;
        let top = (below < ph + 8 && above > below) ? r.top - ph - 4 : r.bottom + 4;
        top = Math.max(8, Math.min(top, vh - ph - 8));
        let left = Math.min(r.left, vw - pw - 8);
        popup.style.left = Math.max(8, left) + 'px';
        popup.style.top = top + 'px';
    }

    const openPopups = new Set();
    function closeAllPopups(except) { openPopups.forEach(p => { if (p !== except) p.close(false); }); }
    document.addEventListener('pointerdown', (e) => {
        openPopups.forEach(p => { if (!p.root.contains(e.target)) p.close(false); });
    }, true);
    const reposition = () => openPopups.forEach(p => p.place());
    window.addEventListener('resize', reposition);
    window.addEventListener('scroll', reposition, true);
    let host = null;
    function dialogHost() {
        if (host) return host;
        host = document.createElement('div');
        host.className = 'bx-dialog-host';
        host.hidden = true;
        host.innerHTML = '<div class="bx-dialog-backdrop" data-bx-cancel></div>'
            + '<div class="bx-dialog" role="alertdialog" aria-modal="true" tabindex="-1">'
            + '<div class="bx-dialog-icon" aria-hidden="true"><i class="fas"></i></div>'
            + '<h2 class="bx-dialog-title"></h2><p class="bx-dialog-text"></p>'
            + '<div class="bx-dialog-extra"></div>'
            + '<div class="bx-dialog-actions"><button type="button" class="btn btn-light" data-bx-cancel></button><button type="button" class="btn" data-bx-ok></button></div>'
            + '</div>';
        document.body.appendChild(host);
        const titleId = nextId('bx-dlg-title'), textId = nextId('bx-dlg-text');
        host.querySelector('.bx-dialog-title').id = titleId;
        host.querySelector('.bx-dialog-text').id = textId;
        host.querySelector('.bx-dialog').setAttribute('aria-labelledby', titleId);
        host.querySelector('.bx-dialog').setAttribute('aria-describedby', textId);
        return host;
    }

    let dialogQueue = Promise.resolve();
    function openDialog(opts) {
        const run = () => new Promise(resolve => {
            const h = dialogHost();
            const box = h.querySelector('.bx-dialog');
            const ok = h.querySelector('[data-bx-ok]');
            const cancel = h.querySelector('button[data-bx-cancel]');
            const extra = h.querySelector('.bx-dialog-extra');
            const danger = opts.tone === 'danger';
            const infoOnly = opts.kind === 'text';
            h.querySelector('.bx-dialog-title').textContent = opts.title || (danger ? 'Are you sure you want to do this?' : 'Please confirm');
            h.querySelector('.bx-dialog-text').textContent = opts.message || '';
            h.querySelector('.bx-dialog-icon i').className = 'fas ' + (infoOnly ? 'fa-link' : danger ? 'fa-triangle-exclamation' : 'fa-circle-question');
            box.classList.toggle('is-danger', danger);
            ok.className = 'btn ' + (danger ? 'btn-danger' : 'btn-primary');
            ok.textContent = opts.okLabel || (infoOnly ? 'Copy' : 'Continue');
            ok.disabled = false;
            cancel.textContent = opts.cancelLabel || (infoOnly ? 'Close' : 'Cancel');
            extra.innerHTML = '';
            let typedInput = null;
            if (opts.typed) {
                const id = nextId('bx-typed');
                extra.innerHTML = '<label class="form-label" for="' + id + '"></label><input class="form-control" id="' + id + '" autocomplete="off" spellcheck="false">';
                extra.querySelector('label').textContent = 'Type ' + opts.typed + ' to confirm';
                typedInput = extra.querySelector('input');
                ok.disabled = true;
                typedInput.addEventListener('input', () => { ok.disabled = typedInput.value.trim() !== opts.typed; });
            }
            let textBox = null;
            if (infoOnly) {
                textBox = document.createElement('input');
                textBox.className = 'form-control';
                textBox.readOnly = true;
                textBox.value = opts.text || '';
                textBox.setAttribute('aria-label', opts.title || 'Text to copy');
                extra.appendChild(textBox);
            }
            const returnTo = document.activeElement;
            // Inside an open Bootstrap modal the dialog must live in that modal, or its focus trap pulls focus back.
            const container = document.querySelector('.modal.show') || document.body;
            if (h.parentNode !== container) container.appendChild(h);
            const restoreInert = [];
            const makeInert = (el) => { if (el !== h && !el.inert && el.tagName !== 'SCRIPT') { el.inert = true; restoreInert.push(el); } };
            Array.from(container.children).forEach(makeInert);
            if (container !== document.body) Array.from(document.body.children).forEach(el => { if (el !== container) makeInert(el); });
            h.hidden = false;
            document.documentElement.classList.add('bx-dialog-open');
            requestAnimationFrame(() => h.classList.add('is-open'));

            function finish(answer) {
                document.removeEventListener('keydown', onKey, true);
                h.removeEventListener('click', onClick);
                h.classList.remove('is-open');
                h.hidden = true;
                document.documentElement.classList.remove('bx-dialog-open');
                restoreInert.forEach(el => { el.inert = false; });
                if (returnTo && returnTo.focus && document.contains(returnTo)) returnTo.focus();
                resolve(answer);
            }
            function onClick(e) {
                if (e.target.closest('[data-bx-cancel]')) { finish(false); return; }
                if (e.target.closest('[data-bx-ok]')) {
                    if (ok.disabled) return;
                    if (infoOnly) {
                        textBox.select();
                        const copied = () => { ok.textContent = 'Copied'; };
                        if (navigator.clipboard) navigator.clipboard.writeText(textBox.value).then(copied, (err) => { console.warn('Clipboard refused', err); ok.textContent = 'Press Ctrl+C to copy'; });
                        else ok.textContent = 'Press Ctrl+C to copy';
                        return;
                    }
                    ok.disabled = true;
                    finish(true);
                }
            }
            function onKey(e) {
                if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); finish(false); return; }
                if (e.key === 'Tab') {
                    const items = Array.from(box.querySelectorAll('button, input')).filter(el => !el.disabled && !isHidden(el));
                    if (!items.length) return;
                    const first = items[0], last = items[items.length - 1];
                    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
                }
            }
            document.addEventListener('keydown', onKey, true);
            h.addEventListener('click', onClick);
            // A destructive action never has the confirm button focused first: Enter must not delete by accident.
            setTimeout(() => { (typedInput || textBox || (danger ? cancel : ok)).focus(); if (textBox) textBox.select(); }, 30);
        });
        const result = dialogQueue.then(run);
        dialogQueue = result.catch((err) => { console.error('Dialog failed', err); });
        return result;
    }
    function enhanceSelect(select) {
        if (enhancers.has(select) || select.multiple || select.hasAttribute('data-native') || select.size > 1) return;
        const wrap = document.createElement('div');
        wrap.className = 'bx-select';
        wrap.setAttribute('data-custom-control', 'select');
        const trigger = document.createElement('button');
        trigger.type = 'button';
        // Spacing and width utilities belong on the wrapper, the look of the field on the button.
        const layout = /^(m[trblxyse]?-|w-|flex-|d-|col|order-|align-self-|float-)/;
        const classes = (select.className || 'form-select').split(/\s+/).filter(Boolean);
        wrap.className += ' ' + classes.filter(c => layout.test(c)).join(' ');
        trigger.className = classes.filter(c => !layout.test(c)).join(' ') + ' bx-trigger';
        if (!/form-select/.test(trigger.className)) trigger.className += ' form-select';
        trigger.id = (select.id || nextId('bx-sel')) + '-control';
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        const valueSpan = document.createElement('span');
        valueSpan.className = 'bx-value';
        trigger.appendChild(valueSpan);
        const popup = document.createElement('div');
        popup.className = 'bx-popup';
        popup.hidden = true;
        const listId = nextId('bx-list');
        popup.innerHTML = '<div class="bx-filter" hidden><i class="fas fa-magnifying-glass" aria-hidden="true"></i><input type="text" autocomplete="off" spellcheck="false" placeholder="Type to filter"></div>'
            + '<ul class="bx-options" role="listbox" tabindex="-1" id="' + listId + '"></ul><p class="bx-empty" hidden>No matches</p><p class="bx-more" hidden></p>';
        trigger.setAttribute('aria-controls', listId);
        const list = popup.querySelector('ul');
        const filterBox = popup.querySelector('.bx-filter');
        const filter = filterBox.querySelector('input');
        filter.setAttribute('role', 'combobox');
        filter.setAttribute('aria-controls', listId);
        filter.setAttribute('aria-autocomplete', 'list');
        filter.setAttribute('aria-expanded', 'true');

        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        wrap.appendChild(trigger);
        wrap.appendChild(popup);
        select.classList.add('bx-native-hidden');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
        if (select.style.cssText) { wrap.style.cssText = select.style.cssText; trigger.style.width = '100%'; }

        const lab = select.id ? document.querySelector('label[for="' + CSS.escape(select.id) + '"]') : null;
        if (lab) {
            if (!lab.id) lab.id = nextId('bx-lab');
            lab.htmlFor = trigger.id;
            trigger.setAttribute('aria-labelledby', lab.id + ' ' + trigger.id);
            list.setAttribute('aria-labelledby', lab.id);
            filter.setAttribute('aria-label', 'Filter ' + labelFor(lab).toLowerCase());
        } else {
            const name = select.getAttribute('aria-label') || labelFor(select) || 'Choose an option';
            trigger.setAttribute('aria-label', name);
            list.setAttribute('aria-label', name);
            filter.setAttribute('aria-label', 'Filter ' + name.toLowerCase());
        }

        const LIMIT = 200;
        let items = [];      // {opt, li}
        let active = -1;
        let typed = '', typedAt = 0;

        function optionText(opt) { return (opt.label || opt.textContent || '').replace(/\s+/g, ' ').trim(); }
        function refresh() {
            const opt = select.options[select.selectedIndex];
            const text = opt ? optionText(opt) : '';
            valueSpan.textContent = text || ' ';
            trigger.classList.toggle('is-placeholder', !opt || opt.value === '');
            const off = select.disabled || !!select.closest('fieldset[disabled]');
            trigger.disabled = off;
            wrap.classList.toggle('is-disabled', off);
            wrap.hidden = select.hidden;
            if (select.hasAttribute('aria-invalid')) trigger.setAttribute('aria-invalid', select.getAttribute('aria-invalid')); else trigger.removeAttribute('aria-invalid');
            const described = select.getAttribute('aria-describedby');
            if (described) trigger.setAttribute('aria-describedby', described); else trigger.removeAttribute('aria-describedby');
            if (!popup.hidden) build();
        }
        function build() {
            const q = filter.value.trim().toLowerCase();
            list.innerHTML = '';
            items = [];
            let shown = 0, matching = 0;
            let groupEl = null, groupOf = null;
            Array.from(select.options).forEach((opt, index) => {
                if (opt.hidden) return;
                const text = optionText(opt);
                if (q && text.toLowerCase().indexOf(q) === -1) return;
                matching++;
                if (shown >= LIMIT) return;
                const parent = opt.parentElement.tagName === 'OPTGROUP' ? opt.parentElement : null;
                if (parent !== groupOf) {
                    groupOf = parent;
                    if (parent) {
                        groupEl = document.createElement('li');
                        groupEl.setAttribute('role', 'presentation');
                        const gid = nextId('bx-grp');
                        groupEl.innerHTML = '<span class="bx-group-label" id="' + gid + '"></span><ul role="group" aria-labelledby="' + gid + '"></ul>';
                        groupEl.firstChild.textContent = parent.label;
                        list.appendChild(groupEl);
                    } else groupEl = null;
                }
                const li = document.createElement('li');
                li.id = nextId('bx-opt');
                li.setAttribute('role', 'option');
                li.dataset.index = String(index);
                li.textContent = text || ' ';
                const chosen = index === select.selectedIndex;
                li.setAttribute('aria-selected', chosen ? 'true' : 'false');
                if (opt.disabled) li.setAttribute('aria-disabled', 'true');
                (groupEl ? groupEl.querySelector('ul') : list).appendChild(li);
                items.push({ opt, li, index });
                shown++;
            });
            popup.querySelector('.bx-empty').hidden = items.length > 0;
            const more = popup.querySelector('.bx-more');
            more.hidden = matching <= shown;
            more.textContent = 'Showing ' + shown + ' of ' + matching + '. Type to narrow the list.';
            const sel = items.findIndex(it => it.index === select.selectedIndex);
            setActive(sel >= 0 ? sel : firstEnabled(0, 1));
        }
        function firstEnabled(from, dir) {
            for (let i = from; i >= 0 && i < items.length; i += dir) if (!items[i].opt.disabled) return i;
            return -1;
        }
        function setActive(i) {
            items.forEach(it => it.li.classList.remove('is-active'));
            active = i;
            const focusEl = filterBox.hidden ? list : filter;
            if (i < 0 || !items[i]) { focusEl.removeAttribute('aria-activedescendant'); return; }
            items[i].li.classList.add('is-active');
            focusEl.setAttribute('aria-activedescendant', items[i].li.id);
            const li = items[i].li, top = li.offsetTop, bottom = top + li.offsetHeight;
            if (top < list.scrollTop) list.scrollTop = top;
            else if (bottom > list.scrollTop + list.clientHeight) list.scrollTop = bottom - list.clientHeight;
        }
        function choose(index) {
            const opt = select.options[index];
            if (!opt || opt.disabled) return;
            const changed = select.selectedIndex !== index;
            select.selectedIndex = index;
            refresh();
            if (changed) { fire(select, 'input'); fire(select, 'change'); }
        }
        const pop = {
            root: wrap,
            place() { if (!popup.hidden) placePopup(popup, trigger, 200); },
            close(returnFocus) {
                if (popup.hidden) return;
                popup.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
                trigger.removeAttribute('aria-activedescendant');
                openPopups.delete(pop);
                if (returnFocus) trigger.focus();
            }
        };
        function open() {
            if (trigger.disabled || !popup.hidden) return;
            closeAllPopups(pop);
            filterBox.hidden = select.options.length <= 10;
            filter.value = '';
            popup.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            build();
            openPopups.add(pop);
            pop.place();
            (filterBox.hidden ? list : filter).focus();
        }
        function move(delta, toEdge) {
            if (!items.length) return;
            let i;
            if (toEdge) i = delta < 0 ? firstEnabled(0, 1) : firstEnabled(items.length - 1, -1);
            else {
                i = active < 0 ? (delta > 0 ? -1 : items.length) : active;
                const step = delta > 0 ? 1 : -1;
                let n = Math.abs(delta);
                let next = i;
                while (n > 0) {
                    const cand = firstEnabled(Math.min(Math.max(next + step, 0), items.length - 1), step);
                    if (cand < 0 || cand === next) break;
                    next = cand; n--;
                }
                i = next < 0 ? active : next;
            }
            setActive(i);
        }
        function typeahead(ch) {
            const now = Date.now();
            typed = (now - typedAt > 700 ? '' : typed) + ch.toLowerCase();
            typedAt = now;
            const opts = Array.from(select.options);
            const start = popup.hidden ? select.selectedIndex : (items[active] ? items[active].index : -1);
            const order = opts.slice(start + 1).concat(opts.slice(0, start + 1));
            const found = order.find(o => !o.disabled && !o.hidden && optionText(o).toLowerCase().startsWith(typed));
            if (!found) return;
            if (popup.hidden) choose(found.index);
            else { const i = items.findIndex(it => it.index === found.index); if (i >= 0) setActive(i); }
        }

        trigger.addEventListener('click', () => { popup.hidden ? open() : pop.close(true); });
        trigger.addEventListener('keydown', (e) => {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key) || (e.altKey && e.key === 'ArrowDown')) {
                e.preventDefault(); open(); return;
            }
            if (e.key === 'Home' || e.key === 'End') { e.preventDefault(); open(); move(e.key === 'Home' ? -1 : 1, true); return; }
            if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) { e.preventDefault(); typeahead(e.key); }
        });
        function popupKeys(e) {
            const inFilter = e.target === filter;
            switch (e.key) {
                case 'ArrowDown': e.preventDefault(); move(1); break;
                case 'ArrowUp': e.preventDefault(); if (e.altKey) { if (items[active]) choose(items[active].index); pop.close(true); } else move(-1); break;
                case 'PageDown': e.preventDefault(); move(10); break;
                case 'PageUp': e.preventDefault(); move(-10); break;
                case 'Home': if (!inFilter) { e.preventDefault(); move(-1, true); } break;
                case 'End': if (!inFilter) { e.preventDefault(); move(1, true); } break;
                case 'Enter': e.preventDefault(); if (items[active]) choose(items[active].index); pop.close(true); break;
                case ' ': if (!inFilter) { e.preventDefault(); if (items[active]) choose(items[active].index); pop.close(true); } break;
                case 'Escape': e.preventDefault(); e.stopPropagation(); pop.close(true); break;
                case 'Tab': pop.close(false); trigger.focus(); break;
                default: if (!inFilter && e.key.length === 1 && !e.ctrlKey && !e.metaKey) typeahead(e.key);
            }
        }
        popup.addEventListener('keydown', popupKeys);
        filter.addEventListener('input', build);
        list.addEventListener('click', (e) => {
            const li = e.target.closest('[role="option"]');
            if (!li || li.getAttribute('aria-disabled') === 'true') return;
            choose(Number(li.dataset.index));
            pop.close(true);
        });
        list.addEventListener('pointermove', (e) => {
            const li = e.target.closest('[role="option"]');
            if (li) { const i = items.findIndex(it => it.li === li); if (i >= 0 && i !== active) setActive(i); }
        });
        if (lab) lab.addEventListener('click', (e) => { e.preventDefault(); trigger.focus(); });

        new MutationObserver(refresh).observe(select, { childList: true, subtree: true, attributes: true, characterData: true, attributeFilter: ['disabled', 'hidden', 'label', 'aria-invalid', 'aria-describedby'] });
        enhancers.set(select, { refresh, focus: () => trigger.focus(), proxy: trigger });
        refresh();
    }

    // Page scripts set a select with .value, .selectedIndex or option.selected; none of those fire an event.
    (function patchSelectSetters() {
        const notify = (sel) => { const en = sel && enhancers.get(sel); if (en) en.refresh(); };
        [[HTMLSelectElement.prototype, 'value'], [HTMLSelectElement.prototype, 'selectedIndex']].forEach(([proto, prop]) => {
            const d = Object.getOwnPropertyDescriptor(proto, prop);
            Object.defineProperty(proto, prop, { configurable: true, enumerable: d.enumerable, get: d.get, set(v) { d.set.call(this, v); notify(this); } });
        });
        const od = Object.getOwnPropertyDescriptor(HTMLOptionElement.prototype, 'selected');
        Object.defineProperty(HTMLOptionElement.prototype, 'selected', { configurable: true, enumerable: od.enumerable, get: od.get, set(v) { od.set.call(this, v); notify(this.closest('select')); } });
    })();
    document.addEventListener('reset', (e) => { setTimeout(() => refreshAll(e.target), 0); });
    function refreshAll(root) {
        (root || document).querySelectorAll('select, input').forEach(el => { const en = enhancers.get(el); if (en) en.refresh(); });
    }
    const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const pad = (n) => String(n).padStart(2, '0');
    const iso = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    const fromIso = (s) => { const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || ''); if (!m) return null; const d = new Date(+m[1], +m[2] - 1, +m[3]); return d.getMonth() === +m[2] - 1 ? d : null; };
    const show = (d) => d ? pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear() : '';
    function parseTyped(s) {
        s = s.trim();
        if (!s) return '';
        let m = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(s);
        if (m) return fromIso(m[1] + '-' + pad(m[2]) + '-' + pad(m[3]));
        m = /^(\d{1,2})[\/.\- ](\d{1,2})[\/.\- ](\d{2}|\d{4})$/.exec(s);
        if (!m) return null;
        let y = +m[3];
        if (m[3].length === 2) y += y > (new Date().getFullYear() % 100) + 1 ? 1900 : 2000;
        return fromIso(y + '-' + pad(m[2]) + '-' + pad(m[1]));
    }

    function enhanceDate(input) {
        if (enhancers.has(input) || input.hasAttribute('data-native')) return;
        const initial = input.value;
        const min = fromIso(input.getAttribute('min')), max = fromIso(input.getAttribute('max'));
        const wrap = document.createElement('div');
        wrap.className = 'bx-date';
        wrap.setAttribute('data-custom-control', 'date');
        input.parentNode.insertBefore(wrap, input);
        const field = document.createElement('input');
        field.type = 'text';
        field.className = (input.className || 'form-control') + ' bx-date-field';
        field.id = input.id || nextId('bx-date');
        field.placeholder = 'dd/mm/yyyy';
        field.setAttribute('inputmode', 'numeric');
        field.autocomplete = 'off';
        if (input.required) field.required = true;
        if (input.getAttribute('aria-label')) field.setAttribute('aria-label', input.getAttribute('aria-label'));
        input.id = field.id + '-value';
        input.type = 'hidden';
        input.value = initial;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'bx-date-btn';
        btn.innerHTML = '<i class="fas fa-calendar-days" aria-hidden="true"></i>';
        btn.setAttribute('aria-label', 'Choose ' + (labelFor(field) || 'date').toLowerCase() + ' from a calendar');
        btn.setAttribute('aria-haspopup', 'dialog');
        btn.setAttribute('aria-expanded', 'false');
        const popup = document.createElement('div');
        popup.className = 'bx-popup bx-calendar';
        popup.hidden = true;
        popup.setAttribute('role', 'dialog');
        popup.setAttribute('aria-modal', 'false');
        popup.setAttribute('aria-label', 'Choose a date');
        popup.innerHTML = '<div class="bx-cal-head">'
            + '<button type="button" data-step="-12" aria-label="Previous year"><i class="fas fa-angles-left" aria-hidden="true"></i></button>'
            + '<button type="button" data-step="-1" aria-label="Previous month"><i class="fas fa-angle-left" aria-hidden="true"></i></button>'
            + '<h3 class="bx-cal-title" aria-live="polite"></h3>'
            + '<button type="button" data-step="1" aria-label="Next month"><i class="fas fa-angle-right" aria-hidden="true"></i></button>'
            + '<button type="button" data-step="12" aria-label="Next year"><i class="fas fa-angles-right" aria-hidden="true"></i></button></div>'
            + '<table class="bx-cal-grid" role="grid"><thead><tr>' + ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'].map((d, i) => '<th scope="col" abbr="' + ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][i] + '">' + d + '</th>').join('') + '</tr></thead><tbody></tbody></table>'
            + '<div class="bx-cal-foot"><button type="button" class="btn btn-sm btn-light" data-today>Today</button><button type="button" class="btn btn-sm btn-light" data-clear>Clear</button></div>';
        const grid = popup.querySelector('tbody');
        const title = popup.querySelector('.bx-cal-title');
        title.id = nextId('bx-cal-title');
        popup.querySelector('table').setAttribute('aria-labelledby', title.id);
        wrap.appendChild(input);
        wrap.appendChild(field);
        wrap.appendChild(btn);
        wrap.appendChild(popup);
        let focusDate = null;

        const outOfRange = (d) => (min && d < min) || (max && d > max);
        function sync() {
            const d = fromIso(input.value);
            if (document.activeElement !== field || !field.value) field.value = show(d);
            delete field.dataset.dateInvalid;
        }
        watchValue(input, sync);
        function render() {
            const year = focusDate.getFullYear(), month = focusDate.getMonth();
            title.textContent = MONTHS[month] + ' ' + year;
            const first = new Date(year, month, 1);
            const start = new Date(year, month, 1 - ((first.getDay() + 6) % 7));
            const chosen = input.value, today = iso(new Date());
            grid.innerHTML = '';
            for (let w = 0; w < 6; w++) {
                const tr = document.createElement('tr');
                for (let d = 0; d < 7; d++) {
                    const day = new Date(start.getFullYear(), start.getMonth(), start.getDate() + w * 7 + d);
                    const td = document.createElement('td');
                    td.setAttribute('role', 'gridcell');
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = String(day.getDate());
                    b.dataset.date = iso(day);
                    b.setAttribute('aria-label', day.getDate() + ' ' + MONTHS[day.getMonth()] + ' ' + day.getFullYear());
                    b.tabIndex = iso(day) === iso(focusDate) ? 0 : -1;
                    if (day.getMonth() !== month) b.classList.add('is-outside');
                    if (iso(day) === today) b.classList.add('is-today');
                    if (iso(day) === chosen) { b.classList.add('is-selected'); td.setAttribute('aria-selected', 'true'); }
                    if (outOfRange(day)) { b.disabled = true; b.setAttribute('aria-disabled', 'true'); }
                    td.appendChild(b);
                    tr.appendChild(td);
                }
                grid.appendChild(tr);
            }
        }
        function focusDay() { const b = grid.querySelector('[data-date="' + iso(focusDate) + '"]'); if (b) b.focus(); }
        function moveTo(d) {
            if (min && d < min) d = new Date(min); if (max && d > max) d = new Date(max);
            const rerender = d.getMonth() !== focusDate.getMonth() || d.getFullYear() !== focusDate.getFullYear();
            focusDate = d;
            if (rerender) render(); else grid.querySelectorAll('button').forEach(b => { b.tabIndex = b.dataset.date === iso(d) ? 0 : -1; });
            focusDay();
        }
        function pick(value) {
            input.value = value;
            field.value = show(fromIso(value));
            clearError(field);
            fire(input, 'input'); fire(input, 'change');
            pop.close(true);
        }
        const pop = {
            root: wrap,
            place() { if (!popup.hidden) placePopup(popup, field, 288); },
            close(returnFocus) {
                if (popup.hidden) return;
                popup.hidden = true;
                btn.setAttribute('aria-expanded', 'false');
                openPopups.delete(pop);
                if (returnFocus) btn.focus();
            }
        };
        function open() {
            if (field.disabled || !popup.hidden) return;
            closeAllPopups(pop);
            focusDate = fromIso(input.value) || (max && new Date() > max ? new Date(max) : (min && new Date() < min ? new Date(min) : new Date()));
            render();
            popup.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
            openPopups.add(pop);
            pop.place();
            focusDay();
        }
        btn.addEventListener('click', () => { popup.hidden ? open() : pop.close(true); });
        field.addEventListener('keydown', (e) => { if (e.key === 'ArrowDown' && e.altKey) { e.preventDefault(); open(); } });
        field.addEventListener('change', () => {
            const d = parseTyped(field.value);
            if (d === '') { input.value = ''; fire(input, 'change'); return; }
            if (d && !outOfRange(d)) { input.value = iso(d); field.value = show(d); fire(input, 'change'); return; }
            field.dataset.dateInvalid = d ? 'range' : 'format';
            showError(field, d ? rangeMessage() : 'Enter the date as day/month/year, for example 25/12/1990.');
        });
        function rangeMessage() {
            if (min && max) return 'Choose a date between ' + show(min) + ' and ' + show(max) + '.';
            return min ? 'Choose a date on or after ' + show(min) + '.' : 'Choose a date on or before ' + show(max) + '.';
        }
        popup.addEventListener('click', (e) => {
            const step = e.target.closest('[data-step]');
            if (step) { const n = +step.dataset.step; focusDate = new Date(focusDate.getFullYear(), focusDate.getMonth() + n, 1); render(); step.focus(); return; }
            if (e.target.closest('[data-today]')) { const t = new Date(); if (!outOfRange(t)) pick(iso(t)); return; }
            if (e.target.closest('[data-clear]')) { pick(''); return; }
            const day = e.target.closest('[data-date]');
            if (day && !day.disabled) pick(day.dataset.date);
        });
        popup.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); pop.close(true); return; }
            if (e.key === 'Tab') {
                const items = Array.from(popup.querySelectorAll('button')).filter(b => !b.disabled && (b.tabIndex === 0 || !b.dataset.date));
                const first = items[0], last = items[items.length - 1];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
                return;
            }
            if (!e.target.dataset.date) return;
            const f = focusDate, y = f.getFullYear(), m = f.getMonth(), d = f.getDate();
            const map = {
                ArrowLeft: new Date(y, m, d - 1), ArrowRight: new Date(y, m, d + 1), ArrowUp: new Date(y, m, d - 7), ArrowDown: new Date(y, m, d + 7),
                Home: new Date(y, m, d - ((f.getDay() + 6) % 7)), End: new Date(y, m, d + (6 - (f.getDay() + 6) % 7)),
                PageUp: e.shiftKey ? new Date(y - 1, m, d) : new Date(y, m - 1, d), PageDown: e.shiftKey ? new Date(y + 1, m, d) : new Date(y, m + 1, d)
            };
            if (map[e.key]) { e.preventDefault(); moveTo(map[e.key]); }
        });
        enhancers.set(input, { refresh: sync, focus: () => field.focus(), proxy: field });
        enhancers.set(field, { refresh: sync, focus: () => field.focus(), proxy: field });
        sync();
    }
    function enhanceRange(input) {
        if (enhancers.has(input) || input.hasAttribute('data-native')) return;
        const min = Number(input.min || 0), max = Number(input.max || 100), step = Number(input.step || 1) || 1;
        const initial = input.value;
        const slider = document.createElement('div');
        slider.className = 'bx-slider';
        slider.setAttribute('data-custom-control', 'range');
        slider.setAttribute('role', 'slider');
        slider.tabIndex = 0;
        slider.setAttribute('aria-valuemin', String(min));
        slider.setAttribute('aria-valuemax', String(max));
        slider.setAttribute('aria-label', input.getAttribute('aria-label') || labelFor(input) || 'Value');
        slider.innerHTML = '<span class="bx-slider-track"><span class="bx-slider-fill"></span></span><span class="bx-slider-thumb"></span>';
        input.parentNode.insertBefore(slider, input);
        input.type = 'hidden';
        input.value = initial;
        const fill = slider.querySelector('.bx-slider-fill'), thumb = slider.querySelector('.bx-slider-thumb');
        const unit = /percent/i.test(slider.getAttribute('aria-label')) ? '%' : '';
        function sync() {
            const v = Math.min(max, Math.max(min, Number(input.value) || 0));
            const pct = ((v - min) / (max - min || 1)) * 100;
            fill.style.width = pct + '%';
            thumb.style.left = pct + '%';
            slider.setAttribute('aria-valuenow', String(v));
            slider.setAttribute('aria-valuetext', v + unit);
        }
        watchValue(input, sync);
        function set(v) {
            v = Math.round((Math.min(max, Math.max(min, v)) - min) / step) * step + min;
            if (String(v) === input.value) return;
            input.value = String(v);
            fire(input, 'input');
        }
        slider.addEventListener('keydown', (e) => {
            const v = Number(input.value) || 0;
            const keys = { ArrowRight: v + step, ArrowUp: v + step, ArrowLeft: v - step, ArrowDown: v - step, PageUp: v + step * 10, PageDown: v - step * 10, Home: min, End: max };
            if (e.key in keys) { e.preventDefault(); set(keys[e.key]); fire(input, 'change'); }
        });
        function fromPointer(e) {
            const r = slider.getBoundingClientRect();
            set(min + ((e.clientX - r.left) / r.width) * (max - min));
        }
        slider.addEventListener('pointerdown', (e) => {
            if (input.disabled) return;
            slider.setPointerCapture(e.pointerId);
            slider.classList.add('is-dragging');
            slider.focus();
            fromPointer(e);
            const moveH = (ev) => fromPointer(ev);
            const up = () => { slider.classList.remove('is-dragging'); slider.removeEventListener('pointermove', moveH); fire(input, 'change'); };
            slider.addEventListener('pointermove', moveH);
            slider.addEventListener('pointerup', up, { once: true });
            slider.addEventListener('pointercancel', up, { once: true });
        });
        enhancers.set(input, { refresh: sync, focus: () => slider.focus(), proxy: slider });
        sync();
    }
    function enhanceColor(input) {
        if (enhancers.has(input) || input.hasAttribute('data-native')) return;
        const initial = input.value;
        const wrap = document.createElement('div');
        wrap.className = 'bx-color';
        wrap.setAttribute('data-custom-control', 'color');
        input.parentNode.insertBefore(wrap, input);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'bx-swatch-btn';
        btn.setAttribute('aria-haspopup', 'dialog');
        btn.setAttribute('aria-expanded', 'false');
        const name = input.getAttribute('aria-label') || labelFor(input) || 'colour';
        btn.innerHTML = '<span class="bx-swatch" aria-hidden="true"></span>';
        const popup = document.createElement('div');
        popup.className = 'bx-popup bx-color-pop';
        popup.hidden = true;
        popup.setAttribute('role', 'dialog');
        popup.setAttribute('aria-label', name);
        const root = getComputedStyle(document.documentElement);
        const brand = ['--brand', '--brand-dark', '--accent'].map(v => root.getPropertyValue(v).trim()).filter(v => /^#[0-9a-f]{6}$/i.test(v));
        const presets = Array.from(new Set(brand.concat(['#001740', '#056CFE', '#E2262F', '#0F766E', '#15803D', '#B45309', '#1F2937', '#64748B', '#FFFFFF', '#000000'].map(c => c.toUpperCase()))));
        popup.innerHTML = '<p class="bx-color-note">Pick a colour, or type a code such as #056CFE in the box beside it.</p><div class="bx-swatches"></div>';
        const grid = popup.querySelector('.bx-swatches');
        presets.forEach(c => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'bx-swatch-choice';
            b.style.setProperty('--swatch', c);
            b.dataset.color = c;
            b.setAttribute('aria-label', c);
            grid.appendChild(b);
        });
        wrap.appendChild(input);
        wrap.appendChild(btn);
        wrap.appendChild(popup);
        input.type = 'hidden';
        input.value = initial;
        function sync() {
            const v = /^#[0-9a-f]{6}$/i.test(input.value) ? input.value : '#000000';
            btn.querySelector('.bx-swatch').style.background = v;
            btn.setAttribute('aria-label', name + ': ' + v.toUpperCase() + '. Choose another colour');
            grid.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', b.dataset.color.toLowerCase() === v.toLowerCase() ? 'true' : 'false'));
        }
        watchValue(input, sync);
        const pop = {
            root: wrap,
            place() { if (!popup.hidden) placePopup(popup, btn, 240); },
            close(returnFocus) { if (popup.hidden) return; popup.hidden = true; btn.setAttribute('aria-expanded', 'false'); openPopups.delete(pop); if (returnFocus) btn.focus(); }
        };
        btn.addEventListener('click', () => {
            if (!popup.hidden) { pop.close(true); return; }
            closeAllPopups(pop);
            popup.hidden = false; btn.setAttribute('aria-expanded', 'true'); openPopups.add(pop); pop.place();
            (grid.querySelector('[aria-pressed="true"]') || grid.querySelector('button')).focus();
        });
        popup.addEventListener('click', (e) => {
            const b = e.target.closest('[data-color]');
            if (!b) return;
            input.value = b.dataset.color;
            fire(input, 'input'); fire(input, 'change');
            pop.close(true);
        });
        popup.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); pop.close(true); return; }
            const all = Array.from(grid.querySelectorAll('button'));
            const i = all.indexOf(document.activeElement);
            const cols = 5;
            const to = { ArrowRight: i + 1, ArrowLeft: i - 1, ArrowDown: i + cols, ArrowUp: i - cols, Home: 0, End: all.length - 1 }[e.key];
            if (to !== undefined && i >= 0) { e.preventDefault(); all[Math.max(0, Math.min(all.length - 1, to))].focus(); }
            if (e.key === 'Tab') { e.preventDefault(); pop.close(true); }
        });
        enhancers.set(input, { refresh: sync, focus: () => btn.focus(), proxy: btn });
        sync();
    }
    // A text field with the numeric keyboard: the browser's spinner arrows and scroll-to-change are gone, and
    // the range is checked by the validation below with a plain message.
    function enhanceNumber(input) {
        if (input.hasAttribute('data-native') || input.dataset.number) return;
        const step = input.getAttribute('step');
        const decimals = step === 'any' || (step && step.indexOf('.') !== -1);
        input.dataset.number = decimals ? 'decimal' : 'integer';
        if (input.hasAttribute('min')) input.dataset.min = input.getAttribute('min');
        if (input.hasAttribute('max')) input.dataset.max = input.getAttribute('max');
        input.type = 'text';
        input.setAttribute('inputmode', decimals ? 'decimal' : 'numeric');
        input.autocomplete = 'off';
    }
    function enhanceFile(input) {
        if (enhancers.has(input) || input.hasAttribute('data-native') || input.classList.contains('d-none')) return;
        const multiple = input.multiple;
        const zone = document.createElement('div');
        zone.className = 'bx-file';
        zone.setAttribute('data-custom-control', 'file');
        input.parentNode.insertBefore(zone, input);
        const accept = (input.getAttribute('accept') || '').split(',').map(s => s.trim().toLowerCase()).filter(Boolean);
        const maxMb = Number(input.dataset.maxMb || 0);
        const hint = accept.length ? accept.filter(a => a.startsWith('.') || a.indexOf('/') > 0).map(a => a.startsWith('.') ? a.slice(1).toUpperCase() : (a.split('/')[1] || '').replace('vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'XLSX').replace('jpeg', 'JPG').toUpperCase()).filter((v, i, arr) => v && arr.indexOf(v) === i).join(', ') : '';
        const btnId = nextId('bx-file-btn');
        zone.innerHTML = '<i class="fas fa-cloud-arrow-up bx-file-icon" aria-hidden="true"></i>'
            + '<div class="bx-file-text"><button type="button" class="btn btn-sm btn-outline-primary" id="' + btnId + '"></button><span class="bx-file-drop"> or drop ' + (multiple ? 'them' : 'it') + ' here</span>'
            + '<small class="bx-file-hint"></small></div><ul class="bx-file-list"></ul>';
        const button = zone.querySelector('button');
        button.textContent = multiple ? 'Choose files' : 'Choose a file';
        zone.querySelector('.bx-file-hint').textContent = [hint, maxMb ? 'up to ' + maxMb + ' MB' + (multiple ? ' each' : '') : ''].filter(Boolean).join(', ');
        const listEl = zone.querySelector('.bx-file-list');
        zone.insertBefore(input, zone.firstChild);
        input.classList.add('bx-native-hidden');
        input.tabIndex = -1;
        const lab = input.id ? document.querySelector('label[for="' + CSS.escape(input.id) + '"]') : null;
        if (lab) { if (!lab.id) lab.id = nextId('bx-lab'); button.setAttribute('aria-describedby', lab.id); lab.htmlFor = btnId; }
        else button.setAttribute('aria-label', (input.getAttribute('aria-label') || 'Choose a file'));

        function allowed(file) {
            if (!accept.length) return true;
            const name = file.name.toLowerCase(), type = (file.type || '').toLowerCase();
            return accept.some(a => a.startsWith('.') ? name.endsWith(a) : a.endsWith('/*') ? type.startsWith(a.slice(0, -1)) : type === a);
        }
        function setFiles(files) {
            const dt = new DataTransfer();
            const problems = [];
            Array.from(files).slice(0, multiple ? undefined : 1).forEach(f => {
                if (!allowed(f)) problems.push(f.name + ' is not an accepted file type' + (hint ? ' (' + hint + ')' : '') + '.');
                else if (maxMb && f.size > maxMb * 1048576) problems.push(f.name + ' is larger than ' + maxMb + ' MB.');
                else dt.items.add(f);
            });
            input.files = dt.files;
            render(problems);
            internalChange = true;
            fire(input, 'change');
            internalChange = false;
        }
        function render(problems) {
            listEl.innerHTML = '';
            Array.from(input.files || []).forEach((f, i) => {
                const li = document.createElement('li');
                li.innerHTML = '<i class="fas fa-file" aria-hidden="true"></i><span class="bx-file-name"></span><small></small><button type="button" class="bx-file-remove"><i class="fas fa-xmark" aria-hidden="true"></i></button>';
                li.querySelector('.bx-file-name').textContent = f.name;
                li.querySelector('small').textContent = f.size >= 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(f.size / 1024)) + ' KB';
                const rm = li.querySelector('button');
                rm.setAttribute('aria-label', 'Remove ' + f.name);
                rm.dataset.index = String(i);
                listEl.appendChild(li);
            });
            (problems || []).forEach(p => {
                const li = document.createElement('li');
                li.className = 'is-error';
                li.setAttribute('role', 'alert');
                li.innerHTML = '<i class="fas fa-circle-exclamation" aria-hidden="true"></i><span></span>';
                li.querySelector('span').textContent = p;
                listEl.appendChild(li);
            });
            zone.classList.toggle('has-files', (input.files || []).length > 0);
            if ((input.files || []).length) clearError(button);
        }
        button.addEventListener('click', () => input.click());
        let internalChange = false;
        // Files picked in the browser's own window go through the same type and size checks as dropped ones.
        input.addEventListener('change', () => { if (!internalChange) setFiles(Array.from(input.files || [])); });
        listEl.addEventListener('click', (e) => {
            const rm = e.target.closest('.bx-file-remove');
            if (!rm) return;
            const keep = Array.from(input.files).filter((f, i) => i !== Number(rm.dataset.index));
            setFiles(keep);
            button.focus();
        });
        ['dragenter', 'dragover'].forEach(t => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.add('is-over'); }));
        ['dragleave', 'drop'].forEach(t => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.remove('is-over'); }));
        zone.addEventListener('drop', (e) => { if (e.dataTransfer && e.dataTransfer.files.length) setFiles(e.dataTransfer.files); });
        enhancers.set(input, { refresh: () => render([]), focus: () => button.focus(), proxy: button });
    }
    function enhanceSuggest(input) {
        if (enhancers.has(input)) return;
        const source = document.getElementById(input.getAttribute('list'));
        if (!source) return;
        const values = Array.from(source.options).map(o => o.value).filter(Boolean);
        input.removeAttribute('list');
        if (!document.querySelector('[list="' + source.id + '"]')) source.remove();
        const wrap = document.createElement('div');
        wrap.className = 'bx-suggest';
        wrap.setAttribute('data-custom-control', 'suggest');
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        const listId = nextId('bx-sug');
        const popup = document.createElement('div');
        popup.className = 'bx-popup';
        popup.hidden = true;
        popup.innerHTML = '<ul class="bx-options" role="listbox" id="' + listId + '"></ul>';
        wrap.appendChild(popup);
        const list = popup.firstChild;
        list.setAttribute('aria-label', 'Suggestions');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', listId);
        input.autocomplete = 'off';
        let active = -1;
        const pop = {
            root: wrap,
            place() { if (!popup.hidden) placePopup(popup, input, 200); },
            close() { if (popup.hidden) return; popup.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); openPopups.delete(pop); }
        };
        function build() {
            const q = input.value.trim().toLowerCase();
            const hits = values.filter(v => v.toLowerCase().indexOf(q) !== -1 && v.toLowerCase() !== q).slice(0, 50);
            list.innerHTML = '';
            hits.forEach(v => { const li = document.createElement('li'); li.setAttribute('role', 'option'); li.id = nextId('bx-sopt'); li.textContent = v; li.setAttribute('aria-selected', 'false'); list.appendChild(li); });
            active = -1;
            if (!hits.length) { pop.close(); return; }
            if (popup.hidden) { closeAllPopups(pop); popup.hidden = false; input.setAttribute('aria-expanded', 'true'); openPopups.add(pop); }
            pop.place();
        }
        function setActive(i) {
            const lis = list.querySelectorAll('li');
            lis.forEach(li => { li.classList.remove('is-active'); li.setAttribute('aria-selected', 'false'); });
            active = i;
            if (lis[i]) { lis[i].classList.add('is-active'); lis[i].setAttribute('aria-selected', 'true'); input.setAttribute('aria-activedescendant', lis[i].id); lis[i].scrollIntoView({ block: 'nearest' }); }
        }
        function take(li) { input.value = li.textContent; fire(input, 'input'); fire(input, 'change'); pop.close(); input.focus(); }
        input.addEventListener('focus', build);
        input.addEventListener('input', (e) => { if (e.isTrusted) build(); });
        input.addEventListener('keydown', (e) => {
            const lis = list.querySelectorAll('li');
            if (e.key === 'ArrowDown') { e.preventDefault(); if (popup.hidden) build(); else setActive(Math.min(active + 1, lis.length - 1)); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(Math.max(active - 1, 0)); }
            else if (e.key === 'Enter' && !popup.hidden && lis[active]) { e.preventDefault(); take(lis[active]); }
            else if (e.key === 'Escape' && !popup.hidden) { e.preventDefault(); e.stopPropagation(); pop.close(); }
            else if (e.key === 'Tab') pop.close();
        });
        list.addEventListener('pointerdown', (e) => { const li = e.target.closest('li'); if (li) { e.preventDefault(); take(li); } });
        enhancers.set(input, { refresh: () => {}, focus: () => input.focus(), proxy: input });
    }
    function enhanceSearch(input) {
        if (input.dataset.bxSearch) return;
        input.dataset.bxSearch = '1';
        const wrap = document.createElement('div');
        wrap.className = 'bx-search';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'bx-search-clear';
        clear.hidden = !input.value;
        clear.setAttribute('aria-label', 'Clear search');
        clear.innerHTML = '<i class="fas fa-xmark" aria-hidden="true"></i>';
        wrap.appendChild(clear);
        input.addEventListener('input', () => { clear.hidden = !input.value; });
        clear.addEventListener('click', () => { input.value = ''; clear.hidden = true; fire(input, 'input'); input.focus(); });
    }
    function enhanceTextarea(t) {
        if (t.dataset.bxGrow || t.hasAttribute('data-rich-text') || t.hasAttribute('data-native')) return;
        t.dataset.bxGrow = '1';
        const grow = () => {
            if (isHidden(t)) return;
            t.style.height = 'auto';
            t.style.height = Math.min(t.scrollHeight + 2, window.innerHeight * 0.6) + 'px';
            t.style.overflowY = t.scrollHeight + 2 > window.innerHeight * 0.6 ? 'auto' : 'hidden';
        };
        t.addEventListener('input', grow);
        t.addEventListener('focus', grow);
        grow();
    }
    let tip = null, tipFor = null, tipTimer = 0;
    function tipEl() {
        if (tip) return tip;
        tip = document.createElement('div');
        tip.className = 'bx-tooltip';
        tip.id = 'bx-tooltip';
        tip.setAttribute('role', 'tooltip');
        tip.hidden = true;
        document.body.appendChild(tip);
        return tip;
    }
    function adoptTitle(el) {
        if (!el.hasAttribute('title') || el.closest('svg') || el.tagName === 'IFRAME' || el.tagName === 'ABBR' || el.tagName === 'LINK' || el.tagName === 'STYLE') return;
        const text = el.getAttribute('title').trim();
        el.removeAttribute('title');
        if (!text) return;
        el.setAttribute('data-tip', text);
        const hasName = el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.textContent.trim() || (el.tagName === 'IMG' && el.alt);
        if (!hasName) el.setAttribute('aria-label', text);
    }
    function showTip(el) {
        const t = tipEl();
        const text = el.getAttribute('data-tip');
        // A tip that only repeats the label you can already read (an unfolded menu item) is not shown.
        if (!text || (el.innerText || '').trim() === text) return;
        tipFor = el;
        t.textContent = text;
        t.hidden = false;
        const name = (el.getAttribute('aria-label') || el.textContent || '').trim();
        if (name !== text) {
            const ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            if (ids.indexOf('bx-tooltip') === -1) { ids.push('bx-tooltip'); el.setAttribute('aria-describedby', ids.join(' ')); el.dataset.tipDescribed = '1'; }
        }
        const r = el.getBoundingClientRect();
        const vw = document.documentElement.clientWidth;
        const tw = t.offsetWidth, th = t.offsetHeight;
        let top = r.top - th - 8;
        t.classList.toggle('is-below', top < 4);
        if (top < 4) top = r.bottom + 8;
        t.style.top = top + 'px';
        t.style.left = Math.max(6, Math.min(r.left + r.width / 2 - tw / 2, vw - tw - 6)) + 'px';
    }
    function hideTip() {
        clearTimeout(tipTimer);
        if (!tip || tip.hidden) return;
        tip.hidden = true;
        if (tipFor && tipFor.dataset.tipDescribed) {
            const ids = (tipFor.getAttribute('aria-describedby') || '').split(/\s+/).filter(id => id && id !== 'bx-tooltip');
            if (ids.length) tipFor.setAttribute('aria-describedby', ids.join(' ')); else tipFor.removeAttribute('aria-describedby');
            delete tipFor.dataset.tipDescribed;
        }
        tipFor = null;
    }
    document.addEventListener('mouseover', (e) => {
        const titled = e.target.closest && e.target.closest('[title]');
        if (titled) adoptTitle(titled);
        const el = e.target.closest && e.target.closest('[data-tip]');
        if (el === tipFor) return;
        hideTip();
        if (el) tipTimer = setTimeout(() => showTip(el), 350);
    });
    document.addEventListener('focusin', (e) => {
        const titled = e.target.closest && e.target.closest('[title]');
        if (titled) adoptTitle(titled);
        hideTip();
        const el = e.target.closest && e.target.closest('[data-tip]');
        if (el && el.matches(':focus-visible')) showTip(el);
    });
    document.addEventListener('focusout', hideTip);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hideTip(); });
    window.addEventListener('scroll', hideTip, true);
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-faq-toggle]');
        if (!btn) return;
        const panel = document.getElementById(btn.getAttribute('aria-controls'));
        const open = btn.getAttribute('aria-expanded') !== 'true';
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (panel) panel.hidden = !open;
        btn.closest('.faq-item').classList.toggle('is-open', open);
    });
    function fieldProxy(el) { const en = enhancers.get(el); return en ? en.proxy : el; }
    function errorAnchor(el) {
        const proxy = fieldProxy(el);
        let anchor = proxy.closest('.input-group, .input-box, .bx-select, .bx-date, .bx-file, .bx-color, .bx-search, .bx-suggest, .form-check') || proxy;
        // In a row of controls (colour swatch beside its code, field beside a button) the message goes under the row.
        const row = anchor.parentElement;
        if (row && row.tagName !== 'FORM' && !row.classList.contains('row')) {
            const st = getComputedStyle(row);
            if (st.display.indexOf('flex') !== -1 && st.flexDirection.indexOf('row') === 0) anchor = row;
        }
        return anchor;
    }
    function showError(el, message) {
        const proxy = fieldProxy(el);
        const anchor = errorAnchor(el);
        let note = proxy.id ? document.getElementById(proxy.id + '-error') : null;
        if (!note) {
            note = document.createElement('div');
            note.className = 'bx-field-error';
            note.id = (proxy.id || (proxy.id = nextId('bx-field'))) + '-error';
            anchor.insertAdjacentElement('afterend', note);
        }
        note.innerHTML = '<i class="fas fa-circle-exclamation" aria-hidden="true"></i><span></span>';
        note.querySelector('span').textContent = message;
        [el, proxy].forEach(target => {
            target.setAttribute('aria-invalid', 'true');
            const ids = (target.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            if (ids.indexOf(note.id) === -1) { ids.push(note.id); target.setAttribute('aria-describedby', ids.join(' ')); }
        });
        proxy.classList.add('is-invalid');
        el.dataset.bxInvalid = '1';
    }
    function clearError(el) {
        const proxy = fieldProxy(el);
        const note = proxy.id ? document.getElementById(proxy.id + '-error') : null;
        [el, proxy].forEach(target => {
            target.removeAttribute('aria-invalid');
            if (note) {
                const ids = (target.getAttribute('aria-describedby') || '').split(/\s+/).filter(id => id && id !== note.id);
                if (ids.length) target.setAttribute('aria-describedby', ids.join(' ')); else target.removeAttribute('aria-describedby');
            }
        });
        if (note) note.remove();
        proxy.classList.remove('is-invalid');
        delete el.dataset.bxInvalid;
    }
    function problemWith(el) {
        if (el.disabled || el.type === 'submit' || el.type === 'button' || el.tagName === 'FIELDSET' || el.tagName === 'OUTPUT' || el.tagName === 'OBJECT') return '';
        const name = labelFor(fieldProxy(el)) || labelFor(el) || 'This field';
        if (el.dataset.dateInvalid === 'format') return 'Enter the date as day/month/year, for example 25/12/1990.';
        if (el.dataset.dateInvalid === 'range') return 'Choose a date inside the allowed range.';
        const v = el.validity;
        if (v.valueMissing) {
            if (el.type === 'checkbox') return 'Tick this box to continue.';
            if (el.type === 'radio') return 'Choose one of the options.';
            if (el.tagName === 'SELECT') return 'Choose an option for ' + name.toLowerCase() + '.';
            if (el.type === 'file') return 'Choose a file to upload.';
            return name + ' is required.';
        }
        if (el.dataset.number && el.value.trim() !== '') {
            const raw = el.value.trim().replace(/,/g, '');
            const ok = el.dataset.number === 'decimal' ? /^-?\d+(\.\d+)?$/.test(raw) : /^-?\d+$/.test(raw);
            if (!ok) return el.dataset.number === 'decimal' ? 'Enter a number, for example 2500 or 12.5.' : 'Enter a whole number.';
            const n = Number(raw);
            if (el.dataset.min !== undefined && n < Number(el.dataset.min)) return 'Use ' + el.dataset.min + ' or more.';
            if (el.dataset.max !== undefined && n > Number(el.dataset.max)) return 'Use ' + el.dataset.max + ' or less.';
        }
        if (v.typeMismatch) return el.type === 'email' ? 'Enter an email address like name@bodaerp.site.' : el.type === 'url' ? 'Enter a full web address starting with https://' : 'Check the format of ' + name.toLowerCase() + '.';
        if (v.tooShort) return 'Use at least ' + el.minLength + ' characters (you have ' + el.value.length + ').';
        if (v.tooLong) return 'Use at most ' + el.maxLength + ' characters.';
        if (v.patternMismatch) return el.getAttribute('data-pattern-message') || el.getAttribute('data-tip') || ('Check the format of ' + name.toLowerCase() + '.');
        if (v.rangeUnderflow) return 'Use ' + el.min + ' or more.';
        if (v.rangeOverflow) return 'Use ' + el.max + ' or less.';
        if (v.customError) return el.validationMessage;
        if (v.badInput) return 'Check the value of ' + name.toLowerCase() + '.';
        return '';
    }
    function checkField(el) {
        const msg = problemWith(el);
        if (msg) showError(el, msg); else if (el.dataset.bxInvalid) clearError(el);
        return !msg;
    }
    function isRendered(el) { return !isHidden(fieldProxy(el)); }
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-skip-validation')) return;
        let firstBad = null;
        const radiosSeen = new Set();
        Array.from(form.elements).forEach(el => {
            if (!el.willValidate && !el.dataset.number && !el.dataset.dateInvalid) return;
            if (!isRendered(el) && !(el.type === 'hidden' && enhancers.has(el))) return;
            if (el.type === 'radio') { if (radiosSeen.has(el.name)) return; radiosSeen.add(el.name); }
            if (!checkField(el) && !firstBad) firstBad = el;
        });
        if (firstBad) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const en = enhancers.get(firstBad);
            (en ? en.focus : () => firstBad.focus())();
            fieldProxy(firstBad).scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }, true);
    document.addEventListener('focusout', (e) => {
        const el = e.target;
        if (!el.form || !el.dataset) return;
        const original = el.id && el.id.endsWith('-control') ? document.getElementById(el.id.slice(0, -8)) : el;
        if (original && original.dataset.bxInvalid) checkField(original);
    });
    ['input', 'change'].forEach(type => document.addEventListener(type, (e) => {
        const el = e.target;
        if (el.dataset && el.dataset.bxInvalid) checkField(el);
    }));
    function enhance(root) {
        if (!root.querySelectorAll) return;
        root.querySelectorAll('form').forEach(f => { f.noValidate = true; });
        if (root.tagName === 'FORM') root.noValidate = true;
        root.querySelectorAll('input[type="number"]').forEach(enhanceNumber);
        root.querySelectorAll('select').forEach(enhanceSelect);
        root.querySelectorAll('input[type="date"]').forEach(enhanceDate);
        root.querySelectorAll('input[type="range"]').forEach(enhanceRange);
        root.querySelectorAll('input[type="color"]').forEach(enhanceColor);
        root.querySelectorAll('input[type="file"]').forEach(enhanceFile);
        root.querySelectorAll('input[list]').forEach(enhanceSuggest);
        root.querySelectorAll('input[type="search"]').forEach(enhanceSearch);
        root.querySelectorAll('textarea').forEach(enhanceTextarea);
        root.querySelectorAll('[title]').forEach(adoptTitle);
    }
    function start() {
        enhance(document);
        new MutationObserver(muts => muts.forEach(m => m.addedNodes.forEach(n => {
            if (n.nodeType !== 1 || (n.closest && n.closest('[data-custom-control], .bx-dialog-host, .bx-tooltip'))) return;
            if (n.matches && n.matches('select, input, textarea, form, [title]')) enhance(n.parentNode || n); else enhance(n);
        }))).observe(document.body, { childList: true, subtree: true });
        // Text areas inside a modal have no height until the modal opens.
        document.addEventListener('shown.bs.modal', (e) => e.target.querySelectorAll('textarea[data-bx-grow]').forEach(t => t.dispatchEvent(new Event('focus'))));
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();

    window.BodaUI = {
        confirm: (opts) => openDialog(Object.assign({ kind: 'confirm' }, opts || {})),
        showText: (opts) => openDialog(Object.assign({ kind: 'text' }, opts || {})),
        refresh: refreshAll,
        enhance: (root) => enhance(root || document),
        showError, clearError
    };
})();
