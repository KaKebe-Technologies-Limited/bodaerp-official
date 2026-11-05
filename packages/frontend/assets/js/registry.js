// Government rider registry: opens the details modal from the JSON block on the page.
// Values are written with textContent, so a rider name can never inject markup.
(function () {
    'use strict';
    var source = document.getElementById('registryData');
    var modalEl = document.getElementById('riderModal');
    if (!source || !modalEl || !window.bootstrap) return;
    var riders = {};
    try { riders = JSON.parse(source.textContent) || {}; } catch (err) { console.error('Registry data could not be read', err); return; }
    var title = document.getElementById('riderModalTitle');
    var fields = document.getElementById('riderModalFields');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-registry-rider]');
        if (!btn) return;
        var rider = riders[btn.getAttribute('data-registry-rider')];
        if (!rider) return;
        title.textContent = rider.name;
        fields.replaceChildren();
        Object.keys(rider).forEach(function (label) {
            if (label === 'id' || label === 'name') return;
            var wrap = document.createElement('div');
            wrap.className = 'col-sm-6';
            var dt = document.createElement('dt');
            dt.className = 'small text-muted fw-normal';
            dt.textContent = label;
            var dd = document.createElement('dd');
            dd.className = 'fw-semibold mb-0';
            dd.textContent = rider[label] || 'Not recorded';
            wrap.append(dt, dd);
            fields.append(wrap);
        });
        modal.show();
    });
})();
