// BodaERP public website behaviour: mobile menu, cookie notice, form submit state,
// show/hide password, and FAQ search. Shared by every public page (no inline handlers).
(function () {
    'use strict';

    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.getElementById('siteNav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && nav.classList.contains('open')) { nav.classList.remove('open'); toggle.setAttribute('aria-expanded', 'false'); toggle.focus(); }
        });
    }

    var notice = document.querySelector('[data-cookie-notice]');
    if (notice) {
        var seen = null;
        try { seen = window.localStorage.getItem('bodaerp-cookie-notice'); } catch (err) { seen = null; }
        if (!seen) notice.classList.add('show');
        var accept = notice.querySelector('[data-cookie-accept]');
        if (accept) accept.addEventListener('click', function () {
            try { window.localStorage.setItem('bodaerp-cookie-notice', String(Date.now())); } catch (err) { /* private mode: the notice simply shows again next visit */ }
            notice.classList.remove('show');
        });
    }

    document.querySelectorAll('form[data-loading]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!form.checkValidity()) return;
            if (form.dataset.submitted === '1') { e.preventDefault(); return; }
            form.dataset.submitted = '1';
            var btn = form.querySelector('[type="submit"]');
            if (btn) {
                btn.classList.add('is-loading');
                btn.setAttribute('aria-busy', 'true');
                btn.dataset.label = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> ' + (form.dataset.loading || 'Please wait');
            }
        });
    });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-loading]').forEach(function (form) {
            form.dataset.submitted = '';
            var btn = form.querySelector('[type="submit"]');
            if (btn && btn.dataset.label) { btn.innerHTML = btn.dataset.label; btn.classList.remove('is-loading'); btn.removeAttribute('aria-busy'); }
        });
    });

    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        var input = document.getElementById(btn.getAttribute('data-toggle-password'));
        if (!input) return;
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            var icon = btn.querySelector('i');
            if (icon) icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
    });

    // Home page hero slider. Changes every data-interval seconds while nobody is using it. Hovering or focusing
    // it holds the current slide; using the arrows, the arrow keys or a swipe hands control to the visitor and the
    // automatic change stops for the rest of the visit. Reduced-motion devices never change by themselves.
    var hero = document.querySelector('[data-hero]');
    if (hero) {
        var slides = hero.querySelectorAll('.hero-slide');
        var live = hero.querySelector('.hero-slides');
        var seconds = parseInt(hero.getAttribute('data-interval'), 10) || 0;
        var visitorInControl = window.matchMedia('(prefers-reduced-motion: reduce)').matches || !seconds;
        var current = 0, timer = 0, held = false;
        function show(index) {
            index = (index + slides.length) % slides.length;
            slides.forEach(function (slide, i) {
                var on = i === index;
                slide.classList.toggle('is-active', on);
                if (on) { slide.removeAttribute('aria-hidden'); slide.inert = false; }
                else { slide.setAttribute('aria-hidden', 'true'); slide.inert = true; }
                var img = slide.querySelector('img[loading="lazy"]');
                if (on && img) img.loading = 'eager';
            });
            current = index;
        }
        function schedule() {
            clearTimeout(timer);
            var playing = !visitorInControl && !held && slides.length > 1 && !document.hidden;
            if (live) live.setAttribute('aria-live', playing ? 'off' : 'polite');
            if (playing) timer = setTimeout(function () { show(current + 1); schedule(); }, seconds * 1000);
        }
        function step(delta) { visitorInControl = true; show(current + delta); schedule(); }
        var prev = hero.querySelector('[data-hero-prev]'), next = hero.querySelector('[data-hero-next]');
        if (prev) prev.addEventListener('click', function () { step(-1); });
        if (next) next.addEventListener('click', function () { step(1); });
        hero.addEventListener('mouseenter', function () { held = true; schedule(); });
        hero.addEventListener('mouseleave', function () { held = hero.contains(document.activeElement); schedule(); });
        hero.addEventListener('focusin', function () { held = true; schedule(); });
        hero.addEventListener('focusout', function (e) { if (!hero.contains(e.relatedTarget)) { held = hero.matches(':hover'); schedule(); } });
        hero.addEventListener('keydown', function (e) {
            if (e.target.closest('a, input')) return;
            if (e.key === 'ArrowRight') { e.preventDefault(); step(1); }
            if (e.key === 'ArrowLeft') { e.preventDefault(); step(-1); }
        });
        var touchX = null;
        hero.addEventListener('touchstart', function (e) { touchX = e.touches[0].clientX; }, { passive: true });
        hero.addEventListener('touchend', function (e) {
            if (touchX === null) return;
            var dx = e.changedTouches[0].clientX - touchX;
            touchX = null;
            if (Math.abs(dx) > 50) step(dx < 0 ? 1 : -1);
        });
        document.addEventListener('visibilitychange', schedule);
        schedule();
    }

    var faqSearch = document.querySelector('[data-faq-search]');
    if (faqSearch) {
        var faqItems = document.querySelectorAll('.faq-item');
        var empty = document.querySelector('[data-faq-empty]');
        faqSearch.addEventListener('input', function () {
            var q = faqSearch.value.trim().toLowerCase();
            var shown = 0;
            faqItems.forEach(function (question) {
                var match = q === '' || question.textContent.toLowerCase().indexOf(q) !== -1;
                question.hidden = !match;
                if (match) shown++;
            });
            if (empty) empty.hidden = shown !== 0;
        });
    }
})();

// Demo sign-in shortcuts (only rendered on a developer's own machine) and password strength meter.
(function () {
    'use strict';
    var roleColors = { super: '--role-super', city: '--role-city', stage: '--role-stage', rider: '--role-rider', gov: '--role-gov' };
    var roleButtons = document.querySelectorAll('.role-btn[data-demo-role]');
    function pickDemo(btn, moveFocus) {
        var email = document.getElementById('email');
        var pass = document.getElementById('password');
        if (!email || !pass) return;
        email.value = btn.getAttribute('data-demo-email');
        pass.value = btn.getAttribute('data-demo-pass');
        var role = btn.getAttribute('data-demo-role');
        roleButtons.forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-demo-role') === role ? 'true' : 'false'); });
        var submit = document.querySelector('.btn-login');
        if (submit && roleColors[role]) submit.style.setProperty('--login-btn', 'var(' + roleColors[role] + ')');
        if (moveFocus) submit ? submit.focus() : pass.focus();
    }
    document.querySelectorAll('[data-demo-email]').forEach(function (btn) {
        btn.addEventListener('click', function () { pickDemo(btn, true); });
    });
    if (roleButtons.length) {
        var emailInput = document.getElementById('email');
        if (emailInput && emailInput.value === '') pickDemo(roleButtons[0], false);
        var keyRoles = { '1': 'super', '2': 'stage', '3': 'city', '4': 'rider', '5': 'gov' };
        document.addEventListener('keydown', function (e) {
            if (e.ctrlKey || e.altKey || e.metaKey || /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
            var btn = keyRoles[e.key] && document.querySelector('.role-btn[data-demo-role="' + keyRoles[e.key] + '"]');
            if (btn) pickDemo(btn, true);
        });
    }
    // Roadside check on the verify page: the location is only read when the officer ticks the box.
    document.querySelectorAll('[data-officer-location]').forEach(function (box) {
        var form = box.closest('form');
        var status = form.querySelector('[data-officer-location-status]');
        box.addEventListener('change', function () {
            ['lat', 'lng', 'acc'].forEach(function (n) { form.elements[n].value = ''; });
            if (!box.checked) { status.textContent = ''; return; }
            if (!navigator.geolocation) { status.textContent = 'This phone cannot share its location. The check is saved without it.'; box.checked = false; return; }
            status.textContent = 'Finding your location...';
            navigator.geolocation.getCurrentPosition(function (pos) {
                form.elements.lat.value = pos.coords.latitude.toFixed(6);
                form.elements.lng.value = pos.coords.longitude.toFixed(6);
                form.elements.acc.value = Math.round(pos.coords.accuracy);
                status.textContent = 'Location added (within about ' + Math.round(pos.coords.accuracy) + ' m).';
            }, function (err) {
                console.warn('Location not available', err);
                status.textContent = 'Location not available (' + (err.code === 1 ? 'permission refused' : 'no signal') + '). The check is saved without it.';
                box.checked = false;
            }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 });
        });
    });

    document.querySelectorAll('[data-strength-for]').forEach(function (meter) {
        var input = document.getElementById(meter.getAttribute('data-strength-for'));
        var bar = meter.querySelector('span');
        if (!input || !bar) return;
        input.addEventListener('input', function () {
            var v = input.value, score = 0;
            if (v.length >= 8) score++;
            if (v.length >= 12) score++;
            if (/[a-z]/i.test(v) && /\d/.test(v)) score++;
            if (/[^a-z0-9]/i.test(v)) score++;
            bar.style.width = (score * 25) + '%';
            bar.style.background = score >= 3 ? '#15803d' : (score === 2 ? '#b45309' : '#b91c1c');
        });
    });
})();
