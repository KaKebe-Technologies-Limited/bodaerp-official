<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['government']);
$active = 'comparison';

$cities = fetchAll("SELECT c.*,
    (SELECT COUNT(*) FROM stages s WHERE s.city_id = c.id) AS stage_count,
    (SELECT COUNT(*) FROM riders r WHERE r.city_id = c.id AND r.deleted_at IS NULL) AS rider_count,
    (SELECT COUNT(*) FROM riders r WHERE r.city_id = c.id AND r.status = 'active' AND r.deleted_at IS NULL) AS active_rider_count,
    (SELECT COUNT(*) FROM enforcement_actions e WHERE e.city_id = c.id) AS enforcement_count,
    (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.city_id = c.id AND p.status = 'Confirmed') AS revenue_collected
    FROM cities c");

foreach ($cities as &$c) {
    $c['compliance_pct'] = $c['rider_count'] ? round(100 * $c['active_rider_count'] / $c['rider_count']) : 0;
}
unset($c);

$byRevenue = $cities; usort($byRevenue, fn($a, $b) => $b['revenue_collected'] <=> $a['revenue_collected']);
$byCompliance = $cities; usort($byCompliance, fn($a, $b) => $b['compliance_pct'] <=> $a['compliance_pct']);
$byRiders = $cities; usort($byRiders, fn($a, $b) => $b['rider_count'] <=> $a['rider_count']);

$agencyLabel = $_SESSION['agency_name'] ?? 'Government Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'District & City Comparison - BodaERP Government Portal'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
    <style>
        .rank-badge { width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:0.8rem;background:var(--off-white);color:var(--text-muted);flex-shrink:0;}
        .rank-badge.r1 { background:linear-gradient(135deg,#f0a020,#d97706); color:white; }
        .rank-badge.r2 { background:linear-gradient(135deg,#c0c8d4,#9aa5b5); color:white; }
        .rank-badge.r3 { background:linear-gradient(135deg,#cd8a5a,#a8602f); color:white; }
        .city-cell-logo { width: 30px; height: 30px; object-fit: contain; background: white; border-radius: 8px; }
        .compliance-bar { height: 5px; border-radius: 3px; background: #eef1f4; overflow: hidden; width: 70px; display: inline-block; vertical-align: middle; }
        .compliance-bar span { display: block; height: 100%; background: #198754; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-government.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <div>
                    <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">District &amp; City Comparison</span></h5>
                    <small class="text-muted d-none d-sm-block">Rankings by revenue, compliance, and rider count</small>
                </div>
            </div>
        </div>
    </header>

    <div class="content-area">

        <div class="card mb-4">
            <div class="card-body">
                <h6 class="section-heading mb-3"><i class="fas fa-chart-bar text-primary me-2"></i>Revenue Collected by City</h6>
                <div id="revenueByCityChart"></div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fas fa-money-bill-wave text-success me-2"></i>Top by Revenue</h6>
                        <?php foreach ($byRevenue as $i => $t): ?>
                        <div class="activity-row">
                            <span class="rank-badge <?= $i === 0 ? 'r1' : ($i === 1 ? 'r2' : ($i === 2 ? 'r3' : '')) ?>"><?= $i + 1 ?></span>
                            <img src="<?= h(BASE_URL . $t['logo_path']) ?>" class="city-cell-logo border" onerror="this.src='<?= BASE_URL ?>/assets/images/logo.png'">
                            <div class="flex-1" style="min-width:0;">
                                <div class="activity-title"><?= h($t['name']) ?></div>
                                <div class="activity-sub"><?= h($t['currency']) ?> <?= number_format($t['revenue_collected']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fas fa-check-circle text-primary me-2"></i>Top by Compliance</h6>
                        <?php foreach ($byCompliance as $i => $t): ?>
                        <div class="activity-row">
                            <span class="rank-badge <?= $i === 0 ? 'r1' : ($i === 1 ? 'r2' : ($i === 2 ? 'r3' : '')) ?>"><?= $i + 1 ?></span>
                            <img src="<?= h(BASE_URL . $t['logo_path']) ?>" class="city-cell-logo border" onerror="this.src='<?= BASE_URL ?>/assets/images/logo.png'">
                            <div class="flex-1" style="min-width:0;">
                                <div class="activity-title"><?= h($t['name']) ?></div>
                                <div class="activity-sub"><?= $t['compliance_pct'] ?>% compliant</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fas fa-users text-warning me-2"></i>Top by Rider Count</h6>
                        <?php foreach ($byRiders as $i => $t): ?>
                        <div class="activity-row">
                            <span class="rank-badge <?= $i === 0 ? 'r1' : ($i === 1 ? 'r2' : ($i === 2 ? 'r3' : '')) ?>"><?= $i + 1 ?></span>
                            <img src="<?= h(BASE_URL . $t['logo_path']) ?>" class="city-cell-logo border" onerror="this.src='<?= BASE_URL ?>/assets/images/logo.png'">
                            <div class="flex-1" style="min-width:0;">
                                <div class="activity-title"><?= h($t['name']) ?></div>
                                <div class="activity-sub"><?= number_format($t['rider_count']) ?> riders</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h6 class="fw-bold mb-0"><i class="fas fa-table text-primary me-2"></i>Full Comparison Table</h6>
                    <?php $exportTableId='comparisonTable'; $exportFilename='district_city_comparison'; $exportTitle='District & City Comparison'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="comparisonTable">
                        <thead><tr><th>City</th><th>Status</th><th>Stages</th><th>Riders</th><th>Compliance</th><th>Revenue Collected</th><th>Enforcement Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($cities as $t): $sc = ['active'=>'success','pending'=>'warning','inactive'=>'secondary']; ?>
                            <tr>
                                <td><div class="d-flex align-items-center gap-2"><img src="<?= h(BASE_URL . $t['logo_path']) ?>" class="city-cell-logo border" onerror="this.src='<?= BASE_URL ?>/assets/images/logo.png'"><div><strong><?= h($t['name']) ?></strong><br><small class="text-muted"><?= h($t['id']) ?></small></div></div></td>
                                <td><span class="badge bg-<?= $sc[$t['status']] ?? 'secondary' ?>"><?= strtoupper($t['status']) ?></span></td>
                                <td><?= (int) $t['stage_count'] ?></td>
                                <td><?= number_format($t['rider_count']) ?></td>
                                <td><span class="compliance-bar"><span style="width:<?= $t['compliance_pct'] ?>%;"></span></span> <small class="text-muted ms-1"><?= $t['compliance_pct'] ?>%</small></td>
                                <td><?= h($t['currency']) ?> <?= number_format($t['revenue_collected']) ?></td>
                                <td><?= number_format($t['enforcement_count']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script>
    new ApexCharts(document.querySelector('#revenueByCityChart'), {
        series: [{ name: 'Revenue', data: <?= json_encode(array_map(fn($c) => (float) $c['revenue_collected'], $byRevenue)) ?> }],
        chart: { type: 'bar', height: 280, toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '45%', distributed: true } },
        dataLabels: { enabled: false },
        legend: { show: false },
        colors: ['#5b4bd5', '#198754', '#f59e0b', '#0dcaf0', '#dc3545', '#6f42c1'],
        xaxis: { categories: <?= json_encode(array_map(fn($c) => $c['name'], $byRevenue)) ?>, labels: { style: { colors: '#6c757d', fontSize: '11px' } } },
        yaxis: { labels: { formatter: (v) => 'UGX ' + (v/1000000).toFixed(1) + 'M', style: { colors: '#6c757d', fontSize: '12px' } } },
        grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
        tooltip: { theme: 'dark', y: { formatter: (v) => 'UGX ' + Number(v).toLocaleString() } }
    }).render();

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
