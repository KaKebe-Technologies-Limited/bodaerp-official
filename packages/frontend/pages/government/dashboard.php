<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['government']);

$active = 'dashboard';

$totalCities   = (int) fetchValue("SELECT COUNT(*) FROM cities WHERE status = 'active'");
$totalRiders   = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE deleted_at IS NULL");
$totalStages   = (int) fetchValue("SELECT COUNT(*) FROM stages");
$totalRevenue  = (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'Confirmed'");
$totalEnforce  = (int) fetchValue("SELECT COUNT(*) FROM enforcement_actions");

$cities = fetchAll("SELECT c.*,
    (SELECT COUNT(*) FROM stages s WHERE s.city_id = c.id) AS stage_count,
    (SELECT COUNT(*) FROM riders r WHERE r.city_id = c.id AND r.deleted_at IS NULL) AS rider_count,
    (SELECT COUNT(*) FROM riders r WHERE r.city_id = c.id AND r.status = 'active' AND r.deleted_at IS NULL) AS active_rider_count,
    (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.city_id = c.id AND p.status = 'Confirmed') AS revenue_collected
    FROM cities c ORDER BY c.created_at");

$splitGovTotal = 0; $splitPlatformTotal = 0;
foreach ($cities as &$c) {
    $c['compliance_pct'] = $c['rider_count'] ? round(100 * $c['active_rider_count'] / $c['rider_count']) : 0;
    $splitGovTotal      += $c['revenue_collected'] * $c['revenue_split_government'] / 100;
    $splitPlatformTotal += $c['revenue_collected'] * $c['revenue_split_platform'] / 100;
}
unset($c);

$monthlyRows = fetchAll(
    "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS ym, SUM(amount) AS total FROM payments
     WHERE status = 'Confirmed' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
     GROUP BY ym"
);
$monthlyByYm = array_column($monthlyRows, 'total', 'ym');
$chartLabels = []; $chartValues = [];
for ($i = 11; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i months"));
    $chartLabels[] = date('M', strtotime("-$i months"));
    $chartValues[] = round((float) ($monthlyByYm[$ym] ?? 0) / 1000000, 2);
}

$statusCounts = fetchAll("SELECT status, COUNT(*) AS c FROM riders WHERE deleted_at IS NULL GROUP BY status");
$statusMap = array_column($statusCounts, 'c', 'status');
$activeCount  = (int) ($statusMap['active'] ?? 0);
$expiredCount = (int) ($statusMap['expired'] ?? 0);
$pendingCount = (int) ($statusMap['pending'] ?? 0);
$complianceRate = $totalRiders ? round(100 * $activeCount / $totalRiders) : 0;

$recentEnforcement = fetchAll("SELECT e.*, c.name AS city_name FROM enforcement_actions e LEFT JOIN cities c ON c.id = e.city_id ORDER BY e.created_at DESC LIMIT 5");
$agencyLabel = $_SESSION['agency_name'] ?? 'Government Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'National Dashboard - BodaERP Government Portal'; require __DIR__ . '/../../partials/head-assets.php'; ?>
    <style>
        .chart-card { border: none; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); background: white; height: 100%; }
        .section-heading { font-weight: 700; font-size: 0.95rem; margin-bottom: 0; }
        .city-cell-logo { width: 30px; height: 30px; object-fit: contain; background: white; border-radius: 8px; }
        .compliance-bar { height: 5px; border-radius: 3px; background: #eef1f4; overflow: hidden; width: 70px; display: inline-block; vertical-align: middle; }
        .compliance-bar span { display: block; height: 100%; background: #198754; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-government.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <div>
                    <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">National Dashboard</span></h5>
                    <small class="text-muted d-none d-sm-block"><?= h($agencyLabel) ?> — Republic of Uganda</small>
                </div>
            </div>
            <div class="user-info">
                <img src="<?= BASE_URL ?>/assets/images/avatar-placeholder.png" class="rounded-circle" width="40" height="40">
                <div>
                    <span class="fw-bold d-block" style="font-size:0.85rem;"><?= h($_SESSION['name']) ?></span>
                    <small class="text-muted"><?= h($agencyLabel) ?></small>
                </div>
            </div>
        </div>
    </header>

    <div class="content-area">

        <div class="dash-banner theme-dark">
            <div class="d-flex justify-content-between align-items-center flex-wrap dash-banner-row">
                <div>
                    <h4>🏛️ National Boda Boda Oversight</h4>
                    <p>Monitoring <?= $totalCities ?> cities, <?= number_format($totalRiders) ?> registered riders and <?= $totalStages ?> stages nationwide.</p>
                </div>
                <span class="dash-badge"><i class="fas fa-signal me-1"></i>Read-only national view</span>
            </div>
        </div>

        <div class="row g-3 row-cols-2 row-cols-md-3 row-cols-xl-6 mb-4">
            <div class="col"><div class="stat-hero-card grad-blue"><div class="stat-hero-icon"><i class="fas fa-city"></i></div><div><div class="stat-hero-value"><?= $totalCities ?></div><div class="stat-hero-label">Cities</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-green"><div class="stat-hero-icon"><i class="fas fa-users"></i></div><div><div class="stat-hero-value"><?= number_format($totalRiders) ?></div><div class="stat-hero-label">Total Riders</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-gold"><div class="stat-hero-icon"><i class="fas fa-check-circle"></i></div><div><div class="stat-hero-value"><?= $complianceRate ?>%</div><div class="stat-hero-label">Compliance Rate</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-dark"><div class="stat-hero-icon"><i class="fas fa-map-marker-alt"></i></div><div><div class="stat-hero-value"><?= $totalStages ?></div><div class="stat-hero-label">Total Stages</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-red"><div class="stat-hero-icon"><i class="fas fa-gavel"></i></div><div><div class="stat-hero-value"><?= number_format($totalEnforce) ?></div><div class="stat-hero-label">Enforcement Actions</div></div></div></div>
            <div class="col"><div class="stat-hero-card" style="background:linear-gradient(135deg,#e0529c,#a02a6b);"><div class="stat-hero-icon"><i class="fas fa-university"></i></div><div><div class="stat-hero-value"><?= $totalRevenue >= 1000000 ? number_format($totalRevenue / 1000000, 1) . 'M' : number_format($totalRevenue) ?></div><div class="stat-hero-label">National Revenue (UGX)</div></div></div></div>
        </div>

        <div class="row g-3 g-md-4 mb-4">
            <div class="col-xl-8">
                <div class="chart-card">
                    <div class="card-body">
                        <h6 class="section-heading mb-3"><i class="fas fa-chart-area text-primary me-2"></i>National Revenue Trend (Last 12 Months)</h6>
                        <div id="revenueChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="chart-card">
                    <div class="card-body">
                        <h6 class="section-heading mb-3"><i class="fas fa-check-circle text-primary me-2"></i>Rider Compliance Status</h6>
                        <div id="complianceChart"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 g-md-4 mb-4">
            <div class="col-xl-7">
                <div class="chart-card">
                    <div class="card-body">
                        <h6 class="section-heading mb-3"><i class="fas fa-chart-bar text-primary me-2"></i>Riders by City</h6>
                        <div id="ridersByCityChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="chart-card">
                    <div class="card-body">
                        <h6 class="section-heading mb-3"><i class="fas fa-university text-primary me-2"></i>Government &amp; Platform Revenue</h6>
                        <div class="mt-2">
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span><span class="badge me-2" style="background:#e0529c;">●</span>Government Share</span><span class="fw-bold">UGX <?= number_format(round($splitGovTotal)) ?></span></div>
                            <div class="d-flex justify-content-between align-items-center py-2"><span><span class="badge bg-info text-dark me-2">●</span>Kakebe Tech (Platform)</span><span class="fw-bold">UGX <?= number_format(round($splitPlatformTotal)) ?></span></div>
                        </div>
                        <a href="<?= BASE_URL ?>/pages/government/comparison.php" class="btn btn-sm btn-outline-primary mt-3 w-100"><i class="fas fa-balance-scale me-1"></i>Full District &amp; City Comparison</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 g-md-4">
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0"><i class="fas fa-city text-primary me-2"></i>City Performance Snapshot</h6>
                            <a href="<?= BASE_URL ?>/pages/government/comparison.php" class="small">View all &raquo;</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle">
                                <thead><tr><th>City</th><th>Riders</th><th>Compliance</th><th>Revenue</th></tr></thead>
                                <tbody>
                                <?php foreach ($cities as $t): ?>
                                    <tr>
                                        <td><div class="d-flex align-items-center gap-2"><img src="<?= h(BASE_URL . $t['logo_path']) ?>" class="city-cell-logo border" onerror="this.src='<?= BASE_URL ?>/assets/images/logo.png'"><strong style="font-size:0.85rem;"><?= h($t['name']) ?></strong></div></td>
                                        <td><?= number_format($t['rider_count']) ?></td>
                                        <td><span class="compliance-bar"><span style="width:<?= $t['compliance_pct'] ?>%;"></span></span> <small class="text-muted ms-1"><?= $t['compliance_pct'] ?>%</small></td>
                                        <td style="font-size:0.82rem;"><?= h($t['currency']) ?> <?= number_format($t['revenue_collected']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0"><i class="fas fa-gavel text-primary me-2"></i>Recent Enforcement Actions</h6>
                            <a href="<?= BASE_URL ?>/pages/government/enforcement.php" class="small">View all &raquo;</a>
                        </div>
                        <?php if (!$recentEnforcement): ?>
                            <p class="text-muted small mb-0">No enforcement actions recorded yet.</p>
                        <?php else: $typeColors = ['warning'=>'warning','fine'=>'danger','suspension'=>'dark','impound'=>'danger']; ?>
                        <div>
                        <?php foreach ($recentEnforcement as $e): ?>
                            <div class="activity-row">
                                <div class="icon-badge red" style="width:34px;height:34px;font-size:0.85rem;"><i class="fas fa-gavel"></i></div>
                                <div class="flex-1" style="min-width:0;">
                                    <div class="activity-title"><?= h(ucfirst($e['type'])) ?> — <?= h($e['plate'] ?: 'N/A') ?></div>
                                    <div class="activity-sub"><?= h($e['city_name'] ?? '—') ?> · <?= formatDate($e['action_date']) ?></div>
                                </div>
                                <span class="badge bg-<?= $typeColors[$e['type']] ?? 'secondary' ?>" style="font-size:0.6rem;flex-shrink:0;"><?= strtoupper($e['status']) ?></span>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script>
    new ApexCharts(document.querySelector('#revenueChart'), {
        series: [{ name: 'Revenue (UGX M)', data: <?= json_encode($chartValues) ?> }],
        chart: { type: 'area', height: 280, toolbar: { show: false }, animations: { enabled: true } },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3, colors: ['#5b4bd5'] },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        xaxis: { categories: <?= json_encode($chartLabels) ?>, labels: { style: { colors: '#6c757d', fontSize: '12px' } } },
        yaxis: { labels: { formatter: (val) => 'UGX ' + val + 'M', style: { colors: '#6c757d', fontSize: '12px' } } },
        grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
        colors: ['#5b4bd5'],
        tooltip: { theme: 'dark', y: { formatter: (val) => 'UGX ' + val + ' Million' } }
    }).render();

    new ApexCharts(document.querySelector('#ridersByCityChart'), {
        series: [{ name: 'Riders', data: <?= json_encode(array_map(fn($c) => (int) $c['rider_count'], $cities)) ?> }],
        chart: { type: 'bar', height: 260, toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '45%', distributed: true } },
        dataLabels: { enabled: false },
        legend: { show: false },
        colors: ['#5b4bd5', '#198754', '#f59e0b', '#0dcaf0', '#dc3545', '#6f42c1'],
        xaxis: { categories: <?= json_encode(array_map(fn($c) => $c['name'], $cities)) ?>, labels: { style: { colors: '#6c757d', fontSize: '11px' } } },
        yaxis: { labels: { style: { colors: '#6c757d', fontSize: '12px' } } },
        grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
        tooltip: { theme: 'dark' }
    }).render();

    new ApexCharts(document.querySelector('#complianceChart'), {
        series: [<?= $activeCount ?>, <?= $expiredCount ?>, <?= $pendingCount ?>],
        chart: { type: 'donut', height: 260, animations: { enabled: true } },
        labels: ['Active', 'Expired', 'Pending'],
        colors: ['#198754', '#dc3545', '#f59e0b'],
        legend: { position: 'bottom', fontSize: '12px' },
        plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: 'Total Riders', formatter: () => '<?= number_format($totalRiders) ?>' } } } } },
        stroke: { width: 0 },
        dataLabels: { enabled: true, formatter: (val) => Math.round(val) + '%' },
        tooltip: { y: { formatter: (val) => Number(val).toLocaleString() + ' riders' } }
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
