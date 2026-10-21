<?php
require_once __DIR__ . '/../../../packages/backend/auth.php';
require_role(['chairperson']);
$active = 'stage-report';
$stageId = $_SESSION['stage_id'];
$stage = fetchOne("SELECT * FROM stages WHERE id = ?", [$stageId]);

$totalRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id=? AND deleted_at IS NULL", [$stageId]);
$active_ = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id=? AND status='active' AND deleted_at IS NULL", [$stageId]);
$expired = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id=? AND status='expired' AND deleted_at IS NULL", [$stageId]);
$pending = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id=? AND status='pending' AND deleted_at IS NULL", [$stageId]);
$compliance = $totalRiders ? round(100 * $active_ / $totalRiders) : 0;

$monthlyRows = fetchAll("SELECT DATE_FORMAT(p.paid_at,'%Y-%m') ym, SUM(p.amount) total FROM payments p
    WHERE p.status='Confirmed' AND p.paid_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) AND p.rider_id IN (SELECT id FROM riders WHERE stage_id=?)
    GROUP BY ym", [$stageId]);
$monthlyByYm = array_column($monthlyRows, 'total', 'ym');
$chartLabels = []; $chartValues = [];
for ($i = 11; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i months"));
    $chartLabels[] = date('M', strtotime("-$i months"));
    $chartValues[] = round((float) ($monthlyByYm[$ym] ?? 0) / 1000, 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Stage Report - BodaERP'; require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Stage Report'; $pageSubtitle = 'Performance summary for ' . ($stage['name'] ?? 'your stage'); require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <div class="dash-banner theme-blue">
            <div class="d-flex justify-content-between align-items-center flex-wrap dash-banner-row">
                <div>
                    <h4><i class="fas fa-file-alt me-2"></i><?= h($stage['name']) ?> — Performance Report</h4>
                    <p>A snapshot of riders, compliance, and revenue for your stage.</p>
                </div>
                <span class="dash-badge">Generated <?= date('M d, Y') ?></span>
            </div>
        </div>

        <div class="row g-3 row-cols-2 row-cols-md-4 mb-4">
            <div class="col"><div class="stat-hero-card grad-blue"><div class="stat-hero-icon"><i class="fas fa-users"></i></div><div><div class="stat-hero-value"><?= $totalRiders ?></div><div class="stat-hero-label">Total Riders</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-green"><div class="stat-hero-icon"><i class="fas fa-user-check"></i></div><div><div class="stat-hero-value"><?= $active_ ?></div><div class="stat-hero-label">Active</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-red"><div class="stat-hero-icon"><i class="fas fa-user-times"></i></div><div><div class="stat-hero-value"><?= $expired ?></div><div class="stat-hero-label">Expired</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-gold"><div class="stat-hero-icon"><i class="fas fa-percentage"></i></div><div><div class="stat-hero-value"><?= $compliance ?>%</div><div class="stat-hero-label">Compliance Rate</div></div></div></div>
        </div>

        <div class="card"><div class="card-body">
            <h6 class="fw-bold mb-3"><i class="fas fa-chart-bar text-primary me-2"></i>Revenue Trend (Last 12 Months)</h6>
            <div id="stageReportChart" style="height: 250px;"></div>
        </div></div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script>
    new ApexCharts(document.querySelector('#stageReportChart'), {
        series: [{ name: 'Revenue (UGX K)', data: <?= json_encode($chartValues) ?> }],
        chart: { type: 'bar', height: 250, toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
        colors: ['#5b4bd5'],
        dataLabels: { enabled: false },
        xaxis: { categories: <?= json_encode($chartLabels) ?> },
        yaxis: { labels: { formatter: v => 'UGX ' + v + 'K' } },
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
