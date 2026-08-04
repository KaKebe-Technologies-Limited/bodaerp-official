<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['city_admin']);
$active = 'revenue-analytics';
$cityId = $_SESSION['city_id'];
$city = fetchOne("SELECT * FROM cities WHERE id = ?", [$cityId]);

$rows = fetchAll("SELECT s.name, v.rider_count, v.active_count, v.compliance_pct,
    (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.rider_id IN (SELECT id FROM riders WHERE stage_id=s.id) AND p.status='Confirmed') AS revenue
    FROM stages s LEFT JOIN v_stage_stats v ON v.stage_id = s.id WHERE s.city_id = ? ORDER BY revenue DESC", [$cityId]);

$totalRevenue = array_sum(array_column($rows, 'revenue'));
$totalRiders = array_sum(array_column($rows, 'rider_count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Revenue Report - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">Revenue Report</span></h5>
            </div>
        </div>
    </header>

    <div class="content-area">
        <div class="card mb-4"><div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0"><?= h($city['name']) ?> — Annual Revenue Report</h5>
                <p class="text-muted mb-0">Fiscal Year <?= h($city['fiscal_year']) ?> · Generated <?= date('M d, Y') ?></p>
            </div>
            <div class="no-print"><?php $exportTableId='revenueReportTable'; $exportFilename='revenue_report'; $exportTitle=$city['name'].' Revenue Report'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?></div>
        </div></div>

        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="card"><div class="card-body text-center"><h3 class="fw-bold text-primary"><?= h(formatCurrency($totalRevenue)) ?></h3><small class="text-muted">Total Revenue Collected</small></div></div></div>
            <div class="col-md-4"><div class="card"><div class="card-body text-center"><h3 class="fw-bold text-success"><?= number_format($totalRiders) ?></h3><small class="text-muted">Total Riders</small></div></div></div>
            <div class="col-md-4"><div class="card"><div class="card-body text-center"><h3 class="fw-bold text-warning"><?= h(formatCurrency($totalRiders ? $totalRevenue / $totalRiders : 0)) ?></h3><small class="text-muted">Revenue per Rider</small></div></div></div>
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
                        <tfoot><tr class="fw-bold"><td>Total</td><td><?= $totalRiders ?></td><td>—</td><td>—</td><td><?= h(formatCurrency($totalRevenue)) ?></td><td>100%</td></tr></tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script>
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
