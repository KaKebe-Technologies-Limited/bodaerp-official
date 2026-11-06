// ID card pages: draws each card's QR code, enlarges a card, prints one side or both, and saves the QR as a PNG.
// Markup comes from partials/id-card.php. Buttons: data-card-zoom, data-card-print="front|back|both",
// data-card-sheet-print, data-qr-download; the holder's signature box (data-sign-form).
(function () {
    'use strict';

    function drawQrCodes() {
        if (typeof QRCode === 'undefined') {
            console.error('QR code library missing: cards show no QR code.');
            return;
        }
        document.querySelectorAll('.qr-wrap[data-qr]').forEach(function (box) {
            if (box.querySelector('canvas, img:not(.qr-center-badge img)')) return;
            new QRCode(box, { text: box.dataset.qr, width: 200, height: 200, colorDark: '#1a1a2e', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.H });
        });
    }

    function zoomLayer() {
        let layer = document.getElementById('idZoomLayer');
        if (layer) return layer;
        layer = document.createElement('div');
        layer.id = 'idZoomLayer';
        layer.className = 'id-zoom-layer';
        layer.hidden = true;
        layer.setAttribute('role', 'dialog');
        layer.setAttribute('aria-modal', 'true');
        layer.setAttribute('aria-label', 'Enlarged ID card');
        layer.innerHTML = '<button type="button" class="id-zoom-close" aria-label="Close the enlarged card"><i class="fas fa-xmark" aria-hidden="true"></i></button>';
        document.body.appendChild(layer);
        layer.addEventListener('click', function (e) {
            if (e.target === layer || e.target.closest('.id-zoom-close')) closeZoom();
        });
        return layer;
    }

    let zoomOpener = null;
    function openZoom(cards, opener) {
        const layer = zoomLayer();
        layer.querySelectorAll('.id-zoom-wrap').forEach(function (w) { w.remove(); });
        const scale = Math.max(1.2, Math.min(2.6, (window.innerWidth - 40) / (3.375 * 96)));
        cards.forEach(function (card) {
            const wrap = document.createElement('div');
            wrap.className = 'id-zoom-wrap';
            wrap.style.setProperty('--zoom', scale.toFixed(2));
            wrap.style.setProperty('--card-colour', getComputedStyle(card).getPropertyValue('--card-colour'));
            const clone = card.cloneNode(true);
            clone.querySelectorAll('[id]').forEach(function (el) { el.removeAttribute('id'); });
            wrap.appendChild(clone);
            layer.appendChild(wrap);
        });
        zoomOpener = opener || null;
        layer.hidden = false;
        document.body.style.overflow = 'hidden';
        layer.querySelector('.id-zoom-close').focus();
    }
    function closeZoom() {
        const layer = document.getElementById('idZoomLayer');
        if (!layer || layer.hidden) return;
        layer.hidden = true;
        document.body.style.overflow = '';
        if (zoomOpener) zoomOpener.focus();
    }

    // @page cannot depend on a class, so the page size is added only while the print window is open.
    function pageSize(css) {
        const tag = document.createElement('style');
        tag.textContent = '@page { ' + css + ' }';
        document.head.appendChild(tag);
        return tag;
    }

    function printCard(side) {
        const cls = ['printing-card', 'print-' + side];
        document.body.classList.add.apply(document.body.classList, cls);
        const size = pageSize('size: 3.375in 2.125in; margin: 0;');
        const done = function () {
            document.body.classList.remove.apply(document.body.classList, cls);
            size.remove();
            window.removeEventListener('afterprint', done);
        };
        window.addEventListener('afterprint', done);
        window.print();
    }

    function downloadQr(button) {
        const box = document.querySelector('.qr-wrap[data-qr]');
        const canvas = box && box.querySelector('canvas');
        if (!canvas) {
            console.error('QR canvas missing: the QR library did not draw the code.');
            if (window.BodaUI) BodaUI.confirm({ title: 'QR code not ready', message: 'The QR code has not been drawn yet. Reload the page and try again.', okLabel: 'OK' });
            return;
        }
        // Redraw with a white margin so phones scan the saved image as easily as the printed one.
        const pad = 24, out = document.createElement('canvas');
        out.width = canvas.width + pad * 2; out.height = canvas.height + pad * 2;
        const ctx = out.getContext('2d');
        ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, out.width, out.height);
        ctx.drawImage(canvas, pad, pad);
        const link = document.createElement('a');
        link.download = (box.dataset.qrName || 'permit') + '-qr.png';
        link.href = out.toDataURL('image/png');
        document.body.appendChild(link);
        link.click();
        link.remove();
        if (button) button.blur();
    }

    document.addEventListener('click', function (e) {
        const zoom = e.target.closest('[data-card-zoom]');
        if (zoom) {
            const side = zoom.closest('.id-side');
            const cards = side ? [side.querySelector('.id-card')] : Array.from(document.querySelectorAll('.id-side .id-card'));
            openZoom(cards, zoom);
            return;
        }
        const print = e.target.closest('[data-card-print]');
        if (print) { printCard(print.dataset.cardPrint || 'both'); return; }
        if (e.target.closest('[data-card-sheet-print]')) {
            document.body.classList.add('printing-sheet');
            const size = pageSize('size: A4; margin: 0;');
            const done = function () { document.body.classList.remove('printing-sheet'); size.remove(); window.removeEventListener('afterprint', done); };
            window.addEventListener('afterprint', done);
            window.print();
            return;
        }
        const qr = e.target.closest('[data-qr-download]');
        if (qr) downloadQr(qr);
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeZoom(); });

    // Card editor (staff card page): the preview follows the form before it is saved.
    function cardDate(iso) {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
        return m ? m[3] + '.' + m[2] + '.' + m[1] : '–';
    }
    function previewEdit(el) {
        if (el.matches('[data-card-edit]')) {
            const key = el.dataset.cardEdit;
            let texts = {};
            if (key === 'name') {
                // Same split as id_card_name_parts(): first word surname, second given name, the rest other names.
                const words = el.value.toUpperCase().trim().split(/\s+/).filter(Boolean);
                texts = { surname: words[0] || '–', given: words[1] || '–', other: words.slice(2).join(' ') || '–' };
            } else {
                texts[key] = key === 'issued_on' ? cardDate(el.value) : el.value;
            }
            document.querySelectorAll('[data-card-text]').forEach(function (t) {
                if (Object.prototype.hasOwnProperty.call(texts, t.dataset.cardText)) t.textContent = texts[t.dataset.cardText];
            });
        } else if (el.matches('[data-card-toggle]')) {
            document.querySelectorAll('[data-card-part]').forEach(function (p) { if (p.dataset.cardPart === el.dataset.cardToggle) p.hidden = !el.checked; });
        } else if (el.matches('[data-card-photo]')) {
            const prop = el.dataset.cardPhoto === 'y' ? ['--photo-y', el.value + '%'] : ['--photo-zoom', String(el.value / 100)];
            document.querySelectorAll('.idc-front').forEach(function (c) { c.style.setProperty(prop[0], prop[1]); });
        }
    }
    ['input', 'change'].forEach(function (type) {
        document.addEventListener(type, function (e) {
            if (e.target.closest && e.target.closest('[data-card-editor]')) previewEdit(e.target);
        });
    });

    // Holder's signature box (staff card page): the rider signs with a finger, stylus or mouse. The drawing is
    // cropped to the ink and sent as a PNG in signature_drawn; a chosen file is sent instead when there is no drawing.
    function setupSignPad(form) {
        const canvas = form.querySelector('[data-sign-pad]');
        const output = form.querySelector('[data-sign-output]');
        const hint = form.querySelector('[data-sign-hint]');
        const fileInput = form.querySelector('[data-sign-file]');
        if (!canvas || !output) return;
        const ctx = canvas.getContext('2d');
        let drawing = false, inked = false, last = null;
        const reset = function () {
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            inked = false;
            if (hint) hint.hidden = false;
        };
        const point = function (e) {
            const box = canvas.getBoundingClientRect();
            return { x: (e.clientX - box.left) * canvas.width / box.width, y: (e.clientY - box.top) * canvas.height / box.height };
        };
        reset();
        ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0b1a3a'; ctx.lineWidth = 5;
        canvas.addEventListener('pointerdown', function (e) {
            drawing = true; last = point(e);
            canvas.setPointerCapture(e.pointerId);
            if (hint) hint.hidden = true;
            e.preventDefault();
        });
        canvas.addEventListener('pointermove', function (e) {
            if (!drawing) return;
            const p = point(e);
            ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke();
            last = p; inked = true;
        });
        ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (type) { canvas.addEventListener(type, function () { drawing = false; }); });
        const clear = form.querySelector('[data-sign-clear]');
        if (clear) clear.addEventListener('click', reset);

        const cropped = function () {
            const px = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
            let minX = canvas.width, minY = canvas.height, maxX = -1, maxY = -1;
            for (let y = 0; y < canvas.height; y++) {
                for (let x = 0; x < canvas.width; x++) {
                    const i = (y * canvas.width + x) * 4;
                    if (px[i] < 200 || px[i + 1] < 200 || px[i + 2] < 200) {
                        if (x < minX) minX = x; if (x > maxX) maxX = x;
                        if (y < minY) minY = y; if (y > maxY) maxY = y;
                    }
                }
            }
            if (maxX < 0) return '';
            const pad = 12;
            minX = Math.max(0, minX - pad); minY = Math.max(0, minY - pad);
            maxX = Math.min(canvas.width - 1, maxX + pad); maxY = Math.min(canvas.height - 1, maxY + pad);
            const out = document.createElement('canvas');
            out.width = Math.max(120, maxX - minX + 1); out.height = Math.max(30, maxY - minY + 1);
            const octx = out.getContext('2d');
            octx.fillStyle = '#ffffff'; octx.fillRect(0, 0, out.width, out.height);
            octx.drawImage(canvas, minX, minY, maxX - minX + 1, maxY - minY + 1, 0, 0, maxX - minX + 1, maxY - minY + 1);
            // Paper white becomes transparent so the card's security print shows through, as with ink on a card.
            const img = octx.getImageData(0, 0, out.width, out.height);
            for (let i = 0; i < img.data.length; i += 4) {
                const light = (img.data[i] + img.data[i + 1] + img.data[i + 2]) / 3;
                img.data[i + 3] = light > 235 ? 0 : Math.round(255 * Math.min(1, (235 - light) / 120));
            }
            octx.putImageData(img, 0, 0);
            return out.toDataURL('image/png');
        };
        form.addEventListener('submit', function (e) {
            output.value = inked ? cropped() : '';
            if (!output.value && !(fileInput && fileInput.files && fileInput.files.length)) {
                e.preventDefault();
                e.stopImmediatePropagation();
                if (window.BodaUI) BodaUI.confirm({ title: 'No signature yet', message: 'Ask the rider to sign in the box, or choose a photo of the signature.', okLabel: 'OK' });
            }
        }, true);
    }

    // A back-of-card sentence stays on one line: if it is wider than the card, its size is lowered in small steps.
    function fitLines(root) {
        (root || document).querySelectorAll('.idc-fit').forEach(function (line) {
            line.style.fontSize = '';
            let size = parseFloat(getComputedStyle(line).fontSize);
            const floor = size * 0.62;
            while (line.scrollWidth > line.clientWidth + 0.5 && size > floor) {
                size -= 0.15;
                line.style.fontSize = size + 'px';
            }
        });
    }

    function init() {
        drawQrCodes();
        fitLines();
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { fitLines(); });
        document.querySelectorAll('[data-sign-form]').forEach(setupSignPad);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
