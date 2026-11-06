<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['city_admin']);
$active = 'revenue-analytics';
$cityId = $_SESSION['city_id'];
$city = fetchOne("SELECT * FROM cities WHERE id = ?", [$cityId]);

$years = array_column(fetchAll("SELECT DISTINCT fiscal_year FROM payments WHERE city_id = ? AND status = 'Confirmed' AND fiscal_year IS NOT NULL ORDER BY fiscal_year DESC", [$cityId]), 'fiscal_year');
if (!in_array(CURRENT_FISCAL_YEAR, $years, true)) array_unshift($years, CURRENT_FISCAL_YEAR);
$year = in_array($_GET['year'] ?? '', $years, true) ? $_GET['year'] : CURRENT_FISCAL_YEAR;
$rows = fetchAll("SELECT s.name, v.rider_count, v.active_count, v.compliance_pct,
    (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN riders pr ON pr.id = p.rider_id WHERE pr.stage_id = s.id AND p.status = 'Confirmed' AND p.fiscal_year = ?) AS revenue
    FROM stages s LEFT JOIN v_stage_stats v ON v.stage_id = s.id WHERE s.city_id = ? ORDER BY revenue DESC", [$year, $cityId]);

$totalRevenue = array_sum(array_column($rows, 'revenue'));
$totalRiders = array_sum(array_column($rows, 'rider_count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Revenue report | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <?php ob_start(); ?>
<a href="<?= BASE_URL ?>/pages/citycouncil/revenue-analytics.php" class="btn btn-outline-secondary btn-sm no-print">Back to revenue</a>
    <?php $headerActions = ob_get_clean();
    $pageTitle = 'Revenue report';
    $pageSubtitle = '';
    require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <div class="card mb-4"><div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0"><?= h($city['name']) ?>: Annual revenue report</h5>
                <p class="text-muted mb-0">Permit year <?= h($year) ?> (July to June) · Generated <?= date('M d, Y') ?></p>
            </div>
            <form method="get" class="no-print d-flex align-items-center gap-2"><label class="small text-muted" for="reportYear">Year</label>
                <select class="form-select form-select-sm" id="reportYear" name="year" data-autosubmit><?php foreach ($years as $y): ?><option<?= $y === $year ? ' selected' : '' ?>><?= h($y) ?></option><?php endforeach; ?></select></form>
            <div class="no-print"><?php $exportTableId='revenueReportTable'; $exportFilename='revenue_report'; $exportTitle=$city['name'].' Revenue Report'; require __DIR__ . '/../../partials/export-toolbar.php'; ?></div>
        </div></div>

        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="card"><div class="card-body text-center"><h3 class="fw-bold text-primary"><?= h(formatCurrency($totalRevenue)) ?></h3><small class="text-muted">Total revenue collected</small></div></div></div>
            <div class="col-md-4"><div class="card"><div class="card-body text-center"><h3 class="fw-bold text-success"><?= number_format($totalRiders) ?></h3><small class="text-muted">Total riders</small></div></div></div>
            <div class="col-md-4"><div class="card"><div class="card-body text-center"><h3 class="fw-bold text-warning"><?= h(formatCurrency($totalRiders ? $totalRevenue / $totalRiders : 0)) ?></h3><small class="text-muted">Revenue per rider</small></div></div></div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="revenueReportTable">
                        <thead><tr><th>Stage</th><th>Riders</th><th>Active</th><th>Compliance</th><th>Revenue</th><th>% of Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><strong><?= h($r['name']) ?></strong></td>
                                <td><?= (int) $r['rider_count'] ?></td>
                                <td><?= (int) $r['active_count'] ?></td>
                                <td><?= (float) ($r['compliance_pct'] ?? 0) ?>%</td>
                                <td><?= h(formatCurrency($r['revenue'])) ?></td>
                                <td><?= $totalRevenue ? round(100 * $r['revenue'] / $totalRevenue, 1) : 0 ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr class="fw-bold"><td>Total</td><td><?= $totalRiders ?></td><td>–</td><td>–</td><td><?= h(formatCurrency($totalRevenue)) ?></td><td>100%</td></tr></tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
</body>
</html>
