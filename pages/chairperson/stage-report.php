<?php
require_once __DIR__ . '/../../includes/auth.php';
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
    <?php $pageTitle = 'Stage Report - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Stage Report'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area">
        <div class="card mb-4"><div class="card-body">
            <h5 class="fw-bold"><?= h($stage['name']) ?> — Performance Report</h5>
            <p class="text-muted mb-0">Generated <?= date('M d, Y') ?></p>
        </div></div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="card"><div class="card-body text-center"><h3 class="fw-bold"><?= $totalRiders ?></h3><small class="text-muted">Total Riders</small></div></div></div>
            <div class="col-md-3 col-6"><div class="card"><div class="card-body text-center"><h3 class="fw-bold text-success"><?= $active_ ?></h3><small class="text-muted">Active</small></div></div></div>
            <div class="col-md-3 col-6"><div class="card"><div class="card-body text-center"><h3 class="fw-bold text-danger"><?= $expired ?></h3><small class="text-muted">Expired</small></div></div></div>
            <div class="col-md-3 col-6"><div class="card"><div class="card-body text-center"><h3 class="fw-bold text-warning"><?= $compliance ?>%</h3><small class="text-muted">Compliance Rate</small></div></div></div>
        </div>

        <div class="card"><div class="card-body">
            <h6 class="fw-bold mb-3">Revenue Trend (last 12 months)</h6>
            <div id="stageReportChart" style="height: 250px;"></div>
        </div></div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script>
    new ApexCharts(document.querySelector('#stageReportChart'), {
        series: [{ name: 'Revenue (UGX K)', data: <?= json_encode($chartValues) ?> }],
        chart: { type: 'bar', height: 250, toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
        colors: ['#0d6efd'],
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
