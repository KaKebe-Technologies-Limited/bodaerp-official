<?php
require_once __DIR__ . '/../../../packages/backend/auth.php';
require_role(['super_admin']);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = strtoupper(trim($_POST['city_id'] ?? ''));
    $c = (int) ($_POST['split_city'] ?? 0);
    $a = (int) ($_POST['split_assoc'] ?? 0);
    $p = (int) ($_POST['split_platform'] ?? 0);
    $g = (int) ($_POST['split_government'] ?? 0);
    if ($c + $a + $p + $g !== 100) {
        $errors[] = 'Splits must total 100% before saving.';
    } else {
        runQuery("UPDATE cities SET revenue_split_city=?, revenue_split_association=?, revenue_split_platform=?, revenue_split_government=? WHERE id=?", [$c,$a,$p,$g,$id]);
        audit_log('UPDATE', 'city', $id, 'Revenue split updated');
        redirect('/pages/superadmin/revenue-splits.php');
    }
}

$active = 'revenue-splits';
$cities = fetchAll("SELECT * FROM cities ORDER BY name");

$cityCount = count($cities);
$avgCity = $cityCount ? round(array_sum(array_column($cities, 'revenue_split_city')) / $cityCount) : 0;
$avgAssoc = $cityCount ? round(array_sum(array_column($cities, 'revenue_split_association')) / $cityCount) : 0;
$avgPlatform = $cityCount ? round(array_sum(array_column($cities, 'revenue_split_platform')) / $cityCount) : 0;
$avgGovernment = $cityCount ? round(array_sum(array_column($cities, 'revenue_split_government')) / $cityCount) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Revenue Splits - BodaERP Platform'; require __DIR__ . '/../../partials/head-assets.php'; ?>
    <style>
        :root {
            --split-city: #7a6cf0;
            --split-assoc: #17a672;
            --split-government: #e0529c;
            --split-platform: #f0a020;
        }

        .split-card {
            background: var(--pure-white);
            border-radius: var(--radius-lg);
            padding: 22px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(28,32,51,0.06);
            transition: box-shadow var(--transition-normal), transform var(--transition-normal);
            height: 100%;
        }
        .split-card:hover { box-shadow: var(--shadow-md); }

        .split-card-logo { width: 44px; height: 44px; object-fit: contain; background: var(--off-white); border-radius: 12px; padding: 5px; flex-shrink: 0; }

        .total-badge { display:inline-flex; align-items:center; gap:5px; padding: 4px 14px; border-radius: var(--radius-full); font-weight: 700; font-size: 0.78rem; }
        .total-ok  { background: #e3f6ec; color: #157347; }

        /* ----- Overview panel: donut + legend ----- */
        .split-overview {
            display: flex;
            align-items: center;
            gap: 16px;
            background: var(--off-white);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            margin: 16px 0;
        }
        .split-donut { width: 92px; height: 92px; flex-shrink: 0; }
        .split-legend { flex: 1; min-width: 0; }
        .split-legend-row { display: flex; align-items: center; justify-content: space-between; padding: 3px 0; font-size: 0.8rem; }
        .split-legend-row .dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; margin-right: 8px; flex-shrink: 0; }
        .split-legend-row .dot.c-city { background: var(--split-city); }
        .split-legend-row .dot.c-assoc { background: var(--split-assoc); }
        .split-legend-row .dot.c-government { background: var(--split-government); }
        .split-legend-row .dot.c-platform { background: var(--split-platform); }
        .split-legend-row .name { color: var(--text-muted); font-weight: 500; }
        .split-legend-row .val { font-weight: 700; color: var(--text-dark); }

        /* ----- Sliders ----- */
        .split-section-label { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .split-section-label .lbl { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-muted); }
        .split-section-label .hint { font-size: 0.68rem; color: var(--text-muted); }

        .split-slider-row { margin-bottom: 16px; }
        .split-slider-row:last-of-type { margin-bottom: 4px; }
        .split-slider-row .slider-label { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
        .split-slider-row .slider-label .name { font-size: 0.82rem; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 7px; }
        .split-slider-row .slider-label .name .dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
        .split-slider-row .slider-label .val { font-size: 0.8rem; font-weight: 800; padding: 2px 10px; border-radius: var(--radius-full); min-width: 44px; text-align: center; }

        .split-slider-row input[type=range] {
            width: 100%;
            height: 6px;
            border-radius: 999px;
            background: var(--light-gray);
            outline: none;
            -webkit-appearance: none;
            appearance: none;
            cursor: pointer;
        }
        .split-slider-row input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 17px; height: 17px;
            border-radius: 50%;
            background: var(--pure-white);
            border: 3px solid currentColor;
            box-shadow: 0 2px 6px rgba(0,0,0,0.18);
            cursor: pointer;
            margin-top: -1px;
        }
        .split-slider-row input[type=range]::-moz-range-thumb {
            width: 14px; height: 14px;
            border-radius: 50%;
            background: var(--pure-white);
            border: 3px solid currentColor;
            box-shadow: 0 2px 6px rgba(0,0,0,0.18);
            cursor: pointer;
        }
        .row-city       { color: var(--split-city); }
        .row-assoc      { color: var(--split-assoc); }
        .row-government { color: var(--split-government); }
        .row-platform   { color: var(--split-platform); }
        .row-city input[type=range] { accent-color: var(--split-city); }
        .row-assoc input[type=range] { accent-color: var(--split-assoc); }
        .row-government input[type=range] { accent-color: var(--split-government); }
        .row-platform input[type=range] { accent-color: var(--split-platform); }
        .row-city .val { background: rgba(122,108,240,0.1); color: var(--split-city); }
        .row-assoc .val { background: rgba(23,166,114,0.1); color: var(--split-assoc); }
        .row-government .val { background: rgba(224,82,156,0.1); color: var(--split-government); }
        .row-platform .val { background: rgba(240,160,32,0.12); color: var(--split-platform); }

        .split-card-footer { display: flex; align-items: center; justify-content: flex-end; gap: 8px; margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--light-gray); }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-superadmin.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <div>
                    <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">Revenue Splits</span></h5>
                    <small class="text-muted">Per-city configurable revenue distribution</small>
                </div>
            </div>
        </div>
    </header>

    <div class="content-area">

        <div class="row g-3 row-cols-2 row-cols-xl-5 mb-4">
            <div class="col"><div class="stat-hero-card grad-dark"><div class="stat-hero-icon"><i class="fas fa-city"></i></div><div><div class="stat-hero-value"><?= $cityCount ?></div><div class="stat-hero-label">Cities Configured</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-blue"><div class="stat-hero-icon"><i class="fas fa-landmark"></i></div><div><div class="stat-hero-value"><?= $avgCity ?>%</div><div class="stat-hero-label">Avg. City Council</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-green"><div class="stat-hero-icon"><i class="fas fa-handshake"></i></div><div><div class="stat-hero-value"><?= $avgAssoc ?>%</div><div class="stat-hero-label">Avg. Association</div></div></div></div>
            <div class="col"><div class="stat-hero-card" style="background:linear-gradient(135deg,#e0529c,#a02a6b);"><div class="stat-hero-icon"><i class="fas fa-university"></i></div><div><div class="stat-hero-value"><?= $avgGovernment ?>%</div><div class="stat-hero-label">Avg. Government</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-gold"><div class="stat-hero-icon"><i class="fas fa-microchip"></i></div><div><div class="stat-hero-value"><?= $avgPlatform ?>%</div><div class="stat-hero-label">Avg. Kakebe Tech</div></div></div></div>
        </div>

        <div class="alert alert-primary mb-4">
            <i class="fas fa-info-circle me-2"></i>
            Revenue splits define how each payment is distributed between <strong>City Council</strong>, <strong>Boda Association</strong>, <strong>Government</strong>, and <strong>Kakebe Tech (Platform)</strong>.
            Drag any slider and the other three <strong>auto-balance</strong> to keep every city at exactly 100%.
        </div>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e); ?></div><?php endif; ?>

        <div class="row g-4" id="splitCards">
        <?php foreach ($cities as $t): $id = $t['id']; ?>
            <div class="col-xl-6">
            <div class="split-card" id="card-<?= h($id) ?>"
                 data-orig-city="<?= (int)$t['revenue_split_city'] ?>" data-orig-assoc="<?= (int)$t['revenue_split_association'] ?>"
                 data-orig-platform="<?= (int)$t['revenue_split_platform'] ?>" data-orig-government="<?= (int)$t['revenue_split_government'] ?>">

                <div class="d-flex align-items-center gap-3">
                    <img src="<?= h(BASE_URL . $t['logo_path']) ?>" class="split-card-logo border" onerror="this.src='<?= BASE_URL ?>/assets/images/logo.png'">
                    <div class="flex-1" style="min-width:0;">
                        <h6 class="mb-0 fw-bold text-truncate"><?= h($t['name']) ?></h6>
                        <small class="text-muted"><?= h($id) ?> · Fee: <?= h($t['currency']) ?> <?= number_format($t['annual_fee']) ?></small>
                    </div>
                    <span class="total-badge total-ok" id="total-<?= h($id) ?>"><i class="fas fa-check-circle"></i> 100%</span>
                </div>

                <div class="split-overview">
                    <div class="split-donut" id="chart-<?= h($id) ?>"></div>
                    <div class="split-legend">
                        <div class="split-legend-row"><span class="name"><span class="dot c-city"></span>City Council</span><span class="val" id="lbl-city-<?= h($id) ?>"><?= (int)$t['revenue_split_city'] ?>%</span></div>
                        <div class="split-legend-row"><span class="name"><span class="dot c-assoc"></span>Boda Association</span><span class="val" id="lbl-assoc-<?= h($id) ?>"><?= (int)$t['revenue_split_association'] ?>%</span></div>
                        <div class="split-legend-row"><span class="name"><span class="dot c-government"></span>Government</span><span class="val" id="lbl-government-<?= h($id) ?>"><?= (int)$t['revenue_split_government'] ?>%</span></div>
                        <div class="split-legend-row"><span class="name"><span class="dot c-platform"></span>Kakebe Tech</span><span class="val" id="lbl-platform-<?= h($id) ?>"><?= (int)$t['revenue_split_platform'] ?>%</span></div>
                    </div>
                </div>

                <form method="post" onsubmit="return prepareSubmit('<?= h($id) ?>')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="city_id" value="<?= h($id) ?>">
                    <input type="hidden" name="split_city" id="hidden-city-<?= h($id) ?>" value="<?= (int)$t['revenue_split_city'] ?>">
                    <input type="hidden" name="split_assoc" id="hidden-assoc-<?= h($id) ?>" value="<?= (int)$t['revenue_split_association'] ?>">
                    <input type="hidden" name="split_government" id="hidden-government-<?= h($id) ?>" value="<?= (int)$t['revenue_split_government'] ?>">
                    <input type="hidden" name="split_platform" id="hidden-platform-<?= h($id) ?>" value="<?= (int)$t['revenue_split_platform'] ?>">

                    <div class="split-section-label">
                        <span class="lbl">Adjust Distribution</span>
                        <span class="hint"><i class="fas fa-sync-alt me-1"></i>Auto-balances to 100%</span>
                    </div>

                    <div class="split-slider-row row-city">
                        <div class="slider-label"><span class="name"><span class="dot" style="background:var(--split-city);"></span>City Council</span><span class="val" id="city-val-<?= h($id) ?>"><?= (int)$t['revenue_split_city'] ?>%</span></div>
                        <input type="range" min="0" max="100" value="<?= (int)$t['revenue_split_city'] ?>" id="city-<?= h($id) ?>" oninput="onSliderInput('<?= h($id) ?>','city')">
                    </div>
                    <div class="split-slider-row row-assoc">
                        <div class="slider-label"><span class="name"><span class="dot" style="background:var(--split-assoc);"></span>Boda Association</span><span class="val" id="assoc-val-<?= h($id) ?>"><?= (int)$t['revenue_split_association'] ?>%</span></div>
                        <input type="range" min="0" max="100" value="<?= (int)$t['revenue_split_association'] ?>" id="assoc-<?= h($id) ?>" oninput="onSliderInput('<?= h($id) ?>','assoc')">
                    </div>
                    <div class="split-slider-row row-government">
                        <div class="slider-label"><span class="name"><span class="dot" style="background:var(--split-government);"></span>Government</span><span class="val" id="government-val-<?= h($id) ?>"><?= (int)$t['revenue_split_government'] ?>%</span></div>
                        <input type="range" min="0" max="100" value="<?= (int)$t['revenue_split_government'] ?>" id="government-<?= h($id) ?>" oninput="onSliderInput('<?= h($id) ?>','government')">
                    </div>
                    <div class="split-slider-row row-platform">
                        <div class="slider-label"><span class="name"><span class="dot" style="background:var(--split-platform);"></span>Kakebe Tech (Platform)</span><span class="val" id="platform-val-<?= h($id) ?>"><?= (int)$t['revenue_split_platform'] ?>%</span></div>
                        <input type="range" min="0" max="100" value="<?= (int)$t['revenue_split_platform'] ?>" id="platform-<?= h($id) ?>" oninput="onSliderInput('<?= h($id) ?>','platform')">
                    </div>

                    <div class="split-card-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetSplit('<?= h($id) ?>')">Reset</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Save Changes</button>
                    </div>
                </form>
            </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js"></script>
<script>
    const KEYS = ['city', 'assoc', 'government', 'platform'];
    const charts = {};
    const state = {};   // id -> { city, assoc, government, platform }  — always sums to 100
    const original = {};

    <?php foreach ($cities as $t): $id = h($t['id']); ?>
    state['<?= $id ?>'] = { city: <?= (int)$t['revenue_split_city'] ?>, assoc: <?= (int)$t['revenue_split_association'] ?>, government: <?= (int)$t['revenue_split_government'] ?>, platform: <?= (int)$t['revenue_split_platform'] ?> };
    original['<?= $id ?>'] = { ...state['<?= $id ?>'] };

    charts['<?= $id ?>'] = new ApexCharts(document.getElementById('chart-<?= $id ?>'), {
        series: [state['<?= $id ?>'].city, state['<?= $id ?>'].assoc, state['<?= $id ?>'].government, state['<?= $id ?>'].platform],
        chart: { type: 'donut', height: 92, width: 92, animations: { enabled: true } },
        labels: ['City Council', 'Association', 'Government', 'Kakebe Tech'],
        colors: ['#7a6cf0', '#17a672', '#e0529c', '#f0a020'],
        legend: { show: false },
        plotOptions: { pie: { donut: { size: '68%' } } },
        dataLabels: { enabled: false },
        stroke: { width: 2, colors: ['#f4f6fb'] },
        tooltip: { y: { formatter: v => v + '%' } },
    });
    charts['<?= $id ?>'].render();
    <?php endforeach; ?>

    // Adjust one slider, then proportionally rescale the OTHER three so the
    // four values always sum to exactly 100 — a classic "budget allocator" split.
    function onSliderInput(id, changedKey) {
        const s = state[id];
        const newVal = clamp(parseInt(document.getElementById(changedKey + '-' + id).value) || 0, 0, 100);
        const others = KEYS.filter(k => k !== changedKey);
        const remaining = 100 - newVal;
        const othersOldSum = others.reduce((sum, k) => sum + s[k], 0);

        const next = { [changedKey]: newVal };
        if (othersOldSum === 0) {
            const base = Math.floor(remaining / others.length);
            others.forEach(k => { next[k] = base; });
            let leftover = remaining - base * others.length;
            let i = 0;
            while (leftover > 0) { next[others[i % others.length]]++; leftover--; i++; }
        } else {
            let assigned = 0;
            others.forEach(k => {
                next[k] = Math.round(remaining * (s[k] / othersOldSum));
                assigned += next[k];
            });
            // fix rounding drift on the largest remaining share
            const diff = remaining - assigned;
            if (diff !== 0) {
                const biggest = others.reduce((a, b) => (next[a] >= next[b] ? a : b));
                next[biggest] += diff;
            }
        }

        KEYS.forEach(k => { s[k] = next[k]; });
        applyState(id);
    }

    function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }

    function applyState(id) {
        const s = state[id];
        KEYS.forEach(k => {
            const pct = s[k] + '%';
            document.getElementById(k + '-' + id).value = s[k];
            document.getElementById(k + '-val-' + id).textContent = pct;
            document.getElementById('lbl-' + k + '-' + id).textContent = pct;
            document.getElementById('hidden-' + k + '-' + id).value = s[k];
        });
        charts[id]?.updateSeries([s.city, s.assoc, s.government, s.platform]);
        // total is always 100 by construction, badge simply confirms it
        document.getElementById('total-' + id).innerHTML = '<i class="fas fa-check-circle"></i> 100%';
    }

    function resetSplit(id) {
        state[id] = { ...original[id] };
        applyState(id);
    }

    function prepareSubmit(id) {
        const total = KEYS.reduce((sum, k) => sum + state[id][k], 0);
        if (total !== 100) { alert('Splits must total 100% before saving.'); return false; }
        return true;
    }

    document.getElementById('toggleSidebar')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    });
    document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
    });
</script>
</body>
</html>
