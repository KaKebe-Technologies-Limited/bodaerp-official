<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['city_admin']);
$active = 'dashboard';
$cityId = $_SESSION['city_id'];

$city = fetchOne("SELECT * FROM cities WHERE id = ?", [$cityId]);
$totalRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND deleted_at IS NULL", [$cityId]);
$activeRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND status = 'active' AND deleted_at IS NULL", [$cityId]);
$defaulters = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND status = 'expired' AND deleted_at IS NULL", [$cityId]);
$totalRevenue = (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM payments WHERE city_id = ? AND status = 'Confirmed'", [$cityId]);
$complianceRate = $totalRiders ? round(100 * $activeRiders / $totalRiders) : 0;

$monthlyRows = fetchAll(
    "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS ym, SUM(amount) AS total FROM payments
     WHERE city_id = ? AND status = 'Confirmed' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
     GROUP BY ym", [$cityId]
);
$monthlyByYm = array_column($monthlyRows, 'total', 'ym');
$chartLabels = []; $chartValues = [];
for ($i = 11; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i months"));
    $chartLabels[] = date('M', strtotime("-$i months"));
    $chartValues[] = round((float) ($monthlyByYm[$ym] ?? 0) / 1000000, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = get_setting('citycouncil_dashboard_title', 'City council dashboard') . ' | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'City council dashboard'; $pageSubtitle = 'Overview for ' . $city['name']; require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <div class="dash-banner theme-blue">
            <div class="d-flex justify-content-between align-items-center flex-wrap dash-banner-row">
                <div>
                    <h4>City council overview</h4>
                    <p>Monitoring boda operations across all stages in <?= h($city['name']) ?></p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="dash-meta"><?= h($city['fiscal_year']) ?></span>
                    <span class="dash-meta"><?= h($city['name']) ?></span>
                </div>
            </div>
        </div>

        <div class="row g-3 row-cols-2 row-cols-xl-4 mb-4">
            <div class="col"><div class="stat-hero-card tone-green"><div><div class="stat-hero-value"><?= number_format($totalRiders) ?></div><div class="stat-hero-label">Total riders</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-blue"><div><div class="stat-hero-value"><?= h(formatCurrency($totalRevenue)) ?></div><div class="stat-hero-label">Total revenue</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-gold"><div><div class="stat-hero-value"><?= $complianceRate ?>%</div><div class="stat-hero-label">Compliance rate</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-red"><div><div class="stat-hero-value"><?= number_format($defaulters) ?></div><div class="stat-hero-label">Defaulters</div></div></div></div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-8">
                <div class="card" style="border: none; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); background: white;">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Revenue Overview (last 12 months)</h5>
                        <div id="revenueChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card" style="border: none; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); background: white;">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Revenue split</h5>
                        <div id="pieChart"></div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center py-1"><span><span class="legend-dot bg-primary me-2"></span> City council</span><span class="fw-bold"><?= (int)$city['revenue_split_city'] ?>%</span></div>
                            <div class="d-flex justify-content-between align-items-center py-1"><span><span class="legend-dot bg-success me-2"></span> Boda association</span><span class="fw-bold"><?= (int)$city['revenue_split_association'] ?>%</span></div>
                            <div class="d-flex justify-content-between align-items-center py-1"><span><span class="legend-dot me-2" style="background:#b45309;"></span> Government</span><span class="fw-bold"><?= (int)$city['revenue_split_government'] ?>%</span></div>
                            <div class="d-flex justify-content-between align-items-center py-1"><span><span class="legend-dot bg-info me-2"></span> Platform</span><span class="fw-bold"><?= (int)$city['revenue_split_platform'] ?>%</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h6 class="fw-bold mb-3">Quick actions</h6>
        <div class="row g-3">
            <div class="col-6 col-md-3"><a href="<?= BASE_URL ?>/pages/citycouncil/revenue-analytics.php" class="text-decoration-none"><div class="quick-action-card"><h6>Revenue report</h6><small>Analytics &amp; trends</small></div></a></div>
            <div class="col-6 col-md-3"><a href="<?= BASE_URL ?>/pages/citycouncil/compliance-monitoring.php" class="text-decoration-none"><div class="quick-action-card"><h6>Compliance</h6><small>Monitor stages</small></div></a></div>
            <div class="col-6 col-md-3"><a href="<?= BASE_URL ?>/pages/citycouncil/city-defaulters.php" class="text-decoration-none"><div class="quick-action-card"><h6>Defaulters</h6><small>City-wide list</small></div></a></div>
            <div class="col-6 col-md-3"><a href="<?= BASE_URL ?>/pages/citycouncil/all-stages.php" class="text-decoration-none"><div class="quick-action-card"><h6>All Stages</h6><small>City overview</small></div></a></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script nonce="<?= csp_nonce() ?>">
    new ApexCharts(document.querySelector('#revenueChart'), {
        series: [{ name: 'Revenue (UGX M)', data: <?= js_json($chartValues) ?> }],
        chart: { type: 'area', height: 280, toolbar: { show: false }, animations: { enabled: true } },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3, colors: ['#056CFE'] },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        xaxis: { categories: <?= js_json($chartLabels) ?>, labels: { style: { colors: '#6c757d', fontSize: '12px' } } },
        yaxis: { labels: { formatter: (val) => 'UGX ' + val + 'M', style: { colors: '#6c757d', fontSize: '12px' } } },
        grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
        colors: ['#056CFE'],
        tooltip: { theme: 'dark', y: { formatter: (val) => 'UGX ' + val + ' Million' } }
    }).render();

    new ApexCharts(document.querySelector('#pieChart'), {
        series: [<?= (int)$city['revenue_split_city'] ?>, <?= (int)$city['revenue_split_association'] ?>, <?= (int)$city['revenue_split_government'] ?>, <?= (int)$city['revenue_split_platform'] ?>],
        chart: { type: 'donut', height: 200, animations: { enabled: true } },
        labels: ['City Council', 'Boda Association', 'Government', 'Platform'],
        colors: ['#056CFE', '#198754', '#d97706', '#0dcaf0'],
        legend: { show: false },
        plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'Total Split', formatter: () => '100%' } } } } },
        stroke: { width: 0 },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: (val) => val + '%' } }
    }).render();
</script>
</body>
</html>
