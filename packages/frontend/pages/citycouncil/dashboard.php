<?php
require_once __DIR__ . '/../../../packages/backend/auth.php';
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
    <?php $pageTitle = 'City Council Dashboard - BodaERP'; require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'City Council Dashboard'; $pageSubtitle = 'Overview for ' . $city['name']; require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <div class="dash-banner theme-blue">
            <div class="d-flex justify-content-between align-items-center flex-wrap dash-banner-row">
                <div>
                    <h4>🏛️ City Council Overview</h4>
                    <p>Monitoring boda operations across all stages in <?= h($city['name']) ?></p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="dash-badge"><i class="fas fa-calendar me-1"></i><?= h($city['fiscal_year']) ?></span>
                    <span class="dash-badge"><i class="fas fa-flag me-1"></i><?= h($city['name']) ?></span>
                </div>
            </div>
        </div>

        <div class="row g-3 row-cols-2 row-cols-xl-4 mb-4">
            <div class="col"><div class="stat-hero-card grad-green"><div class="stat-hero-icon"><i class="fas fa-users"></i></div><div><div class="stat-hero-value"><?= number_format($totalRiders) ?></div><div class="stat-hero-label">Total Riders</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-blue"><div class="stat-hero-icon"><i class="fas fa-money-bill-wave"></i></div><div><div class="stat-hero-value"><?= h(formatCurrency($totalRevenue)) ?></div><div class="stat-hero-label">Total Revenue</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-gold"><div class="stat-hero-icon"><i class="fas fa-check-circle"></i></div><div><div class="stat-hero-value"><?= $complianceRate ?>%</div><div class="stat-hero-label">Compliance Rate</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-red"><div class="stat-hero-icon"><i class="fas fa-exclamation-triangle"></i></div><div><div class="stat-hero-value"><?= number_format($defaulters) ?></div><div class="stat-hero-label">Defaulters</div></div></div></div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-8">
                <div class="card" style="border: none; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); background: white;">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3"><i class="fas fa-chart-bar text-primary me-2"></i>Revenue Overview (last 12 months)</h5>
                        <div id="revenueChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card" style="border: none; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); background: white;">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3"><i class="fas fa-pie-chart text-primary me-2"></i>Revenue Split</h5>
                        <div id="pieChart"></div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center py-1"><span><span class="badge bg-primary me-2">●</span> City Council</span><span class="fw-bold"><?= (int)$city['revenue_split_city'] ?>%</span></div>
                            <div class="d-flex justify-content-between align-items-center py-1"><span><span class="badge bg-success me-2">●</span> Boda Association</span><span class="fw-bold"><?= (int)$city['revenue_split_association'] ?>%</span></div>
                            <div class="d-flex justify-content-between align-items-center py-1"><span><span class="badge me-2" style="background:#e0529c;">●</span> Government</span><span class="fw-bold"><?= (int)$city['revenue_split_government'] ?>%</span></div>
                            <div class="d-flex justify-content-between align-items-center py-1"><span><span class="badge bg-info me-2">●</span> Kakebe Tech</span><span class="fw-bold"><?= (int)$city['revenue_split_platform'] ?>%</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h6 class="fw-bold mb-3">Quick Actions</h6>
        <div class="row g-3">
            <div class="col-6 col-md-3"><a href="<?= BASE_URL ?>/pages/citycouncil/revenue-analytics.php" class="text-decoration-none"><div class="quick-action-card"><div class="icon-badge blue"><i class="fas fa-file-invoice"></i></div><h6>Revenue Report</h6><small>Analytics &amp; trends</small></div></a></div>
            <div class="col-6 col-md-3"><a href="<?= BASE_URL ?>/pages/citycouncil/compliance-monitoring.php" class="text-decoration-none"><div class="quick-action-card"><div class="icon-badge green"><i class="fas fa-check-circle"></i></div><h6>Compliance</h6><small>Monitor stages</small></div></a></div>
            <div class="col-6 col-md-3"><a href="<?= BASE_URL ?>/pages/citycouncil/city-defaulters.php" class="text-decoration-none"><div class="quick-action-card"><div class="icon-badge red"><i class="fas fa-exclamation-triangle"></i></div><h6>Defaulters</h6><small>City-wide list</small></div></a></div>
            <div class="col-6 col-md-3"><a href="<?= BASE_URL ?>/pages/citycouncil/all-stages.php" class="text-decoration-none"><div class="quick-action-card"><div class="icon-badge gold"><i class="fas fa-map-marker-alt"></i></div><h6>All Stages</h6><small>City overview</small></div></a></div>
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

    new ApexCharts(document.querySelector('#pieChart'), {
        series: [<?= (int)$city['revenue_split_city'] ?>, <?= (int)$city['revenue_split_association'] ?>, <?= (int)$city['revenue_split_government'] ?>, <?= (int)$city['revenue_split_platform'] ?>],
        chart: { type: 'donut', height: 200, animations: { enabled: true } },
        labels: ['City Council', 'Boda Association', 'Government', 'Kakebe Tech'],
        colors: ['#5b4bd5', '#198754', '#e0529c', '#0dcaf0'],
        legend: { show: false },
        plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'Total Split', formatter: () => '100%' } } } } },
        stroke: { width: 0 },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: (val) => val + '%' } }
    }).render();
</script>
</body>
</html>
