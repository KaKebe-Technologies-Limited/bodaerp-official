<?php
require_once __DIR__ . '/../../includes/auth.php';
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
    <?php $pageTitle = 'City Council Dashboard - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
    <style>
        .council-stat-card { background: white; border-radius: 14px; padding: 16px 20px; border-left: 4px solid #198754; box-shadow: 0 2px 12px rgba(0,0,0,0.04); transition: all 0.3s ease; height: 100%; }
        .council-stat-card:hover { transform: translateX(4px); box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        .council-stat-card .stat-number { font-size: 1.6rem; font-weight: 900; color: #1a1a2e; line-height: 1; word-break: break-word; }
        .council-stat-card .stat-label { font-size: 0.75rem; color: #6c757d; font-weight: 500; }
        .council-stat-card .stat-icon { font-size: 1.6rem; opacity: 0.15; }
        .council-stat-card.green { border-left-color: #198754; }
        .council-stat-card.blue { border-left-color: #0d6efd; }
        .council-stat-card.red { border-left-color: #dc3545; }
        .council-stat-card.gold { border-left-color: #f59e0b; }
        @media (max-width: 576px) {
            .council-stat-card { padding: 12px 14px; border-radius: 12px; }
            .council-stat-card .stat-number { font-size: 1.05rem; }
            .council-stat-card .stat-label { font-size: 0.66rem; }
            .council-stat-card .stat-icon { font-size: 1.2rem; }
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">City Council Dashboard</span></h5>
            </div>
            <div class="user-info">
                <img src="<?= BASE_URL ?>/assets/images/avatar-placeholder.png" alt="Council" class="rounded-circle" width="40" height="40">
                <div><span class="fw-bold d-block" style="font-size: 0.85rem;">City Council</span><small class="text-muted" style="font-size: 0.7rem;"><?= h($city['name']) ?></small></div>
            </div>
        </div>
    </header>

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

        <div class="row g-3 g-md-4 mb-4">
            <div class="col-6 col-xl-3">
                <div class="council-stat-card green"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= number_format($totalRiders) ?></span><div class="stat-label">Total Riders</div></div><i class="fas fa-users stat-icon"></i></div></div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="council-stat-card blue"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= h(formatCurrency($totalRevenue)) ?></span><div class="stat-label">Total Revenue</div></div><i class="fas fa-money-bill-wave stat-icon"></i></div></div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="council-stat-card gold"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= $complianceRate ?>%</span><div class="stat-label">Compliance Rate</div></div><i class="fas fa-check-circle stat-icon"></i></div></div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="council-stat-card red"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= number_format($defaulters) ?></span><div class="stat-label">Defaulters</div></div><i class="fas fa-exclamation-triangle stat-icon"></i></div></div>
            </div>
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

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script>
    new ApexCharts(document.querySelector('#revenueChart'), {
        series: [{ name: 'Revenue (UGX M)', data: <?= json_encode($chartValues) ?> }],
        chart: { type: 'area', height: 280, toolbar: { show: false }, animations: { enabled: true } },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3, colors: ['#0d6efd'] },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        xaxis: { categories: <?= json_encode($chartLabels) ?>, labels: { style: { colors: '#6c757d', fontSize: '12px' } } },
        yaxis: { labels: { formatter: (val) => 'UGX ' + val + 'M', style: { colors: '#6c757d', fontSize: '12px' } } },
        grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
        colors: ['#0d6efd'],
        tooltip: { theme: 'dark', y: { formatter: (val) => 'UGX ' + val + ' Million' } }
    }).render();

    new ApexCharts(document.querySelector('#pieChart'), {
        series: [<?= (int)$city['revenue_split_city'] ?>, <?= (int)$city['revenue_split_association'] ?>, <?= (int)$city['revenue_split_platform'] ?>],
        chart: { type: 'donut', height: 200, animations: { enabled: true } },
        labels: ['City Council', 'Boda Association', 'Kakebe Tech'],
        colors: ['#0d6efd', '#198754', '#0dcaf0'],
        legend: { show: false },
        plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'Total Split', formatter: () => '100%' } } } } },
        stroke: { width: 0 },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: (val) => val + '%' } }
    }).render();
</script>
</body>
</html>
