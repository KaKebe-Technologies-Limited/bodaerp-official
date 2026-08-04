<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = strtoupper(trim($_POST['city_id'] ?? ''));
    $c = (int) ($_POST['split_city'] ?? 0);
    $a = (int) ($_POST['split_assoc'] ?? 0);
    $p = (int) ($_POST['split_platform'] ?? 0);
    if ($c + $a + $p !== 100) {
        $errors[] = 'Splits must total 100% before saving.';
    } else {
        runQuery("UPDATE cities SET revenue_split_city=?, revenue_split_association=?, revenue_split_platform=? WHERE id=?", [$c,$a,$p,$id]);
        audit_log('UPDATE', 'city', $id, 'Revenue split updated');
        redirect('/pages/superadmin/revenue-splits.php');
    }
}

$active = 'revenue-splits';
$cities = fetchAll("SELECT * FROM cities ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Revenue Splits - BodaERP Platform'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
    <style>
        .split-card { background:white; border-radius:14px; padding:20px; box-shadow:0 2px 12px rgba(0,0,0,0.05); margin-bottom:20px; border-top:4px solid #0d6efd; }
        .split-card.dirty { border-top-color:#f59e0b; }
        .range-wrap { display:flex; align-items:center; gap:10px; }
        .range-wrap input[type=range] { flex:1; accent-color:#0d6efd; }
        .range-val { min-width:42px; text-align:right; font-weight:700; font-size:0.95rem; color:#0d6efd; }
        .total-badge { padding:6px 18px; border-radius:20px; font-weight:700; font-size:0.9rem; }
        .total-ok  { background:#d4edda; color:#155724; }
        .total-bad { background:#f8d7da; color:#721c24; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-superadmin.php'; ?>

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
        <div class="alert alert-primary mb-4">
            <i class="fas fa-info-circle me-2"></i>
            Revenue splits define how each payment is distributed: <strong>City Council</strong>, <strong>Boda Association</strong>, and <strong>Kakebe Tech (Platform)</strong>. Splits are unique per city and must total 100%.
        </div>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e); ?></div><?php endif; ?>

        <div id="splitCards">
        <?php foreach ($cities as $t): $id = $t['id']; ?>
            <div class="split-card" id="card-<?= h($id) ?>"
                 data-orig-city="<?= (int)$t['revenue_split_city'] ?>" data-orig-assoc="<?= (int)$t['revenue_split_association'] ?>" data-orig-platform="<?= (int)$t['revenue_split_platform'] ?>">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="<?= h(BASE_URL . $t['logo_path']) ?>" width="40" height="40" class="rounded border" style="object-fit:contain;background:white;" onerror="this.src='<?= BASE_URL ?>/assets/images/logo.png'">
                    <div>
                        <h6 class="mb-0 fw-bold"><?= h($t['name']) ?></h6>
                        <small class="text-muted"><?= h($id) ?> · Annual Fee: <?= h($t['currency']) ?> <?= number_format($t['annual_fee']) ?></small>
                    </div>
                    <div class="ms-auto">Total: <span class="total-badge total-ok" id="total-<?= h($id) ?>">100%</span></div>
                </div>

                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="city_id" value="<?= h($id) ?>">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="small fw-semibold text-primary">🏛️ City Council</label>
                            <div class="range-wrap">
                                <input type="range" min="0" max="100" value="<?= (int)$t['revenue_split_city'] ?>" name="split_city" id="city-<?= h($id) ?>" oninput="updateRanges('<?= h($id) ?>')">
                                <span class="range-val" id="city-val-<?= h($id) ?>"><?= (int)$t['revenue_split_city'] ?>%</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-semibold text-success">🤝 Boda Association</label>
                            <div class="range-wrap">
                                <input type="range" min="0" max="100" value="<?= (int)$t['revenue_split_association'] ?>" name="split_assoc" id="assoc-<?= h($id) ?>" oninput="updateRanges('<?= h($id) ?>')">
                                <span class="range-val" id="assoc-val-<?= h($id) ?>" style="color:#198754"><?= (int)$t['revenue_split_association'] ?>%</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-semibold text-info">💻 Kakebe Tech (Platform)</label>
                            <div class="range-wrap">
                                <input type="range" min="0" max="100" value="<?= (int)$t['revenue_split_platform'] ?>" name="split_platform" id="platform-<?= h($id) ?>" oninput="updateRanges('<?= h($id) ?>')">
                                <span class="range-val" id="platform-val-<?= h($id) ?>" style="color:#0dcaf0"><?= (int)$t['revenue_split_platform'] ?>%</span>
                            </div>
                        </div>
                    </div>

                    <div class="row align-items-center g-3">
                        <div class="col-md-4"><div id="chart-<?= h($id) ?>"></div></div>
                        <div class="col-md-5">
                            <div class="d-flex justify-content-between small py-1"><span><span class="badge bg-primary me-1">●</span>City Council</span><strong id="lbl-city-<?= h($id) ?>"><?= (int)$t['revenue_split_city'] ?>%</strong></div>
                            <div class="d-flex justify-content-between small py-1"><span><span class="badge bg-success me-1">●</span>Boda Association</span><strong id="lbl-assoc-<?= h($id) ?>"><?= (int)$t['revenue_split_association'] ?>%</strong></div>
                            <div class="d-flex justify-content-between small py-1"><span><span class="badge bg-info text-dark me-1">●</span>Kakebe Tech</span><strong id="lbl-platform-<?= h($id) ?>"><?= (int)$t['revenue_split_platform'] ?>%</strong></div>
                        </div>
                        <div class="col-md-3 text-end">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Save</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="resetSplit('<?= h($id) ?>')">Reset</button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js"></script>
<script>
    const charts = {};
    <?php foreach ($cities as $t): $id = $t['id']; ?>
    charts['<?= h($id) ?>'] = new ApexCharts(document.getElementById('chart-<?= h($id) ?>'), {
        series: [<?= (int)$t['revenue_split_city'] ?>, <?= (int)$t['revenue_split_association'] ?>, <?= (int)$t['revenue_split_platform'] ?>],
        chart: { type: 'donut', height: 120, animations: { enabled: true } },
        labels: ['City', 'Association', 'Platform'],
        colors: ['#0d6efd', '#198754', '#0dcaf0'],
        legend: { show: false },
        plotOptions: { pie: { donut: { size: '65%' } } },
        dataLabels: { enabled: false },
        stroke: { width: 0 },
        tooltip: { y: { formatter: v => v + '%' } },
    });
    charts['<?= h($id) ?>'].render();
    <?php endforeach; ?>

    function updateRanges(id) {
        const cVal = parseInt(document.getElementById('city-'+id).value)||0;
        const aVal = parseInt(document.getElementById('assoc-'+id).value)||0;
        const pVal = parseInt(document.getElementById('platform-'+id).value)||0;
        const total = cVal + aVal + pVal;

        document.getElementById('city-val-'+id).textContent     = cVal + '%';
        document.getElementById('assoc-val-'+id).textContent    = aVal + '%';
        document.getElementById('platform-val-'+id).textContent = pVal + '%';
        document.getElementById('lbl-city-'+id).textContent     = cVal + '%';
        document.getElementById('lbl-assoc-'+id).textContent    = aVal + '%';
        document.getElementById('lbl-platform-'+id).textContent = pVal + '%';

        const totEl = document.getElementById('total-'+id);
        totEl.textContent = total + '%';
        totEl.className = 'total-badge ' + (total === 100 ? 'total-ok' : 'total-bad');

        charts[id]?.updateSeries([cVal, aVal, pVal]);
        document.getElementById('card-'+id).classList.toggle('dirty', total !== 100);
    }

    function resetSplit(id) {
        const card = document.getElementById('card-'+id);
        document.getElementById('city-'+id).value = card.dataset.origCity;
        document.getElementById('assoc-'+id).value = card.dataset.origAssoc;
        document.getElementById('platform-'+id).value = card.dataset.origPlatform;
        updateRanges(id);
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
