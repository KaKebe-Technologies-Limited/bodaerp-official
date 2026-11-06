<?php
require_once __DIR__ . '/../../../backend/auth.php';
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
$monthlyCounts = array_column(fetchAll("SELECT DATE_FORMAT(p.paid_at,'%Y-%m') ym, COUNT(*) n FROM payments p
    WHERE p.status='Confirmed' AND p.paid_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) AND p.rider_id IN (SELECT id FROM riders WHERE stage_id=?)
    GROUP BY ym", [$stageId]), 'n', 'ym');
$owing = fetchAll("SELECT full_name, id_number, bike_plate, phone, status, expiry_date FROM riders
    WHERE stage_id = ? AND deleted_at IS NULL AND status IN ('expired','pending') ORDER BY status, expiry_date", [$stageId]);
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
    <?php $pageTitle = 'Stage report | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Stage report'; $pageSubtitle = 'Performance summary for ' . ($stage['name'] ?? 'your stage'); require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <div class="dash-banner theme-blue">
            <div class="d-flex justify-content-between align-items-center flex-wrap dash-banner-row">
                <div>
                    <h4><?= h($stage['name']) ?>: Performance report</h4>
                    <p>A snapshot of riders, compliance, and revenue for your stage.</p>
                </div>
                <div class="d-flex align-items-center gap-2"><span class="dash-meta">Generated <?= date('M d, Y') ?></span>
                    <button type="button" class="btn btn-light btn-sm no-print" data-print>Print report</button></div>
            </div>
        </div>

        <div class="row g-3 row-cols-2 row-cols-md-4 mb-4">
            <div class="col"><div class="stat-hero-card tone-blue"><div class="stat-hero-icon"><i class="fas fa-users"></i></div><div><div class="stat-hero-value"><?= $totalRiders ?></div><div class="stat-hero-label">Total riders</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-green"><div class="stat-hero-icon"><i class="fas fa-user-check"></i></div><div><div class="stat-hero-value"><?= $active_ ?></div><div class="stat-hero-label">Active</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-red"><div class="stat-hero-icon"><i class="fas fa-user-times"></i></div><div><div class="stat-hero-value"><?= $expired ?></div><div class="stat-hero-label">Expired</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-gold"><div class="stat-hero-icon"><i class="fas fa-percentage"></i></div><div><div class="stat-hero-value"><?= $compliance ?>%</div><div class="stat-hero-label">Compliance rate</div></div></div></div>
        </div>

        <div class="card"><div class="card-body">
            <h6 class="fw-bold mb-3">Revenue trend (last 12 months)</h6>
            <div id="stageReportChart" style="height: 250px;"></div>
        </div></div>

        <div class="card mt-4"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h2 class="section-title mb-0">Money collected by month</h2>
                <?php $exportTableId = 'stageMonthly'; $exportFilename = 'stage_report_' . date('Y_m'); $exportTitle = ($stage['name'] ?? 'Stage') . ': money collected by month'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
            </div>
            <div class="table-responsive"><table class="table table-sm align-middle" id="stageMonthly">
                <thead><tr><th>Month</th><th class="text-end">Payments</th><th class="text-end">Amount</th></tr></thead>
                <tbody><?php for ($i = 11; $i >= 0; $i--): $ym = date('Y-m', strtotime("first day of -$i months")); ?>
                    <tr><td><?= date('F Y', strtotime($ym . '-01')) ?></td><td class="text-end"><?= (int) ($monthlyCounts[$ym] ?? 0) ?></td><td class="text-end"><?= h(formatCurrency((float) ($monthlyByYm[$ym] ?? 0))) ?></td></tr>
                <?php endfor; ?></tbody>
            </table></div>
        </div></div>

        <div class="card mt-4"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h2 class="section-title mb-0">Riders who still owe (<?= count($owing) ?>)</h2>
                <?php $exportTableId = 'stageOwing'; $exportFilename = 'stage_riders_owing_' . date('Y_m_d'); $exportTitle = ($stage['name'] ?? 'Stage') . ': riders who still owe'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
            </div>
            <div class="table-responsive"><table class="table table-sm align-middle" id="stageOwing">
                <thead><tr><th>Rider</th><th>Permit number</th><th>Plate</th><th>Phone</th><th>Status</th><th>Expired on</th></tr></thead>
                <tbody><?php foreach ($owing as $o): ?>
                    <tr><td><?= h($o['full_name']) ?></td><td><?= h($o['id_number']) ?></td><td><?= h($o['bike_plate']) ?></td><td><?= h($o['phone']) ?></td><td><?= $o['status'] === 'pending' ? 'Not paid yet' : 'Expired' ?></td><td><?= $o['expiry_date'] ? formatDate($o['expiry_date']) : '–' ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$owing): ?><tr><td colspan="6" class="text-muted">Every rider at the stage is paid up.</td></tr><?php endif; ?></tbody>
            </table></div>
        </div></div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script nonce="<?= csp_nonce() ?>">
    new ApexCharts(document.querySelector('#stageReportChart'), {
        series: [{ name: 'Revenue (UGX K)', data: <?= js_json($chartValues) ?> }],
        chart: { type: 'bar', height: 250, toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
        colors: ['#056CFE'],
        dataLabels: { enabled: false },
        xaxis: { categories: <?= js_json($chartLabels) ?> },
        yaxis: { labels: { formatter: v => 'UGX ' + v + 'K' } },
    }).render();

</script>
</body>
</html>
