<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']);
$active = 'platform-reports';

$statusFilter = $_GET['status'] ?? '';
$where = "WHERE 1=1"; $qparams = [];
if ($statusFilter !== '') { $where .= " AND c.status = :status"; $qparams['status'] = $statusFilter; }

$rows = fetchAll("SELECT c.*,
    (SELECT COUNT(*) FROM riders r WHERE r.city_id = c.id) AS rider_count,
    (SELECT COUNT(*) FROM stages s WHERE s.city_id = c.id) AS stage_count,
    (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.city_id = c.id AND p.status = 'Confirmed') AS revenue_collected
    FROM cities c $where ORDER BY c.name", $qparams);

$totalRiders = 0; $totalStages = 0; $totalRevenue = 0; $totalPlatformShare = 0;
foreach ($rows as &$r) {
    $r['platform_share'] = $r['revenue_collected'] * ($r['revenue_split_platform'] / 100);
    $totalRiders += $r['rider_count'];
    $totalStages += $r['stage_count'];
    $totalRevenue += $r['revenue_collected'];
    $totalPlatformShare += $r['platform_share'];
}
unset($r);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Platform Reports - BodaERP Platform'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-superadmin.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">Platform Reports</span></h5>
            </div>
        </div>
    </header>

    <div class="content-area">
        <div class="card mb-3 no-print">
            <div class="card-body">
                <form class="row g-2 align-items-center" method="get">
                    <div class="col-md-4">
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
                            <option value="pending" <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
                            <option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-8 text-md-end">
                        <?php $exportTableId='reportsTable'; $exportFilename='platform_revenue_report'; $exportTitle='Platform Revenue by City'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
                    </div>
                </form>
            </div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="card"><div class="card-body"><small class="text-muted">Total Cities</small><h3 class="fw-bold mb-0"><?= count($rows) ?></h3></div></div></div>
            <div class="col-md-3 col-6"><div class="card"><div class="card-body"><small class="text-muted">Total Riders</small><h3 class="fw-bold mb-0"><?= number_format($totalRiders) ?></h3></div></div></div>
            <div class="col-md-3 col-6"><div class="card"><div class="card-body"><small class="text-muted">Total Stages</small><h3 class="fw-bold mb-0"><?= $totalStages ?></h3></div></div></div>
            <div class="col-md-3 col-6"><div class="card"><div class="card-body"><small class="text-muted">Platform Revenue Collected</small><h3 class="fw-bold mb-0">UGX <?= number_format(round($totalPlatformShare)) ?></h3></div></div></div>
        </div>

        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-chart-bar text-primary me-2"></i>Revenue by City</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="reportsTable">
                        <thead><tr><th>City</th><th>Riders</th><th>Stages</th><th>Annual Fee</th><th>Revenue Collected</th><th>Platform Share</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $t): $sc = ['active'=>'success','pending'=>'warning','inactive'=>'secondary']; ?>
                            <tr>
                                <td><div class="d-flex align-items-center gap-2"><img src="<?= h(BASE_URL . $t['logo_path']) ?>" width="28" height="28" class="rounded border" style="object-fit:contain;background:white;" onerror="this.src='<?= BASE_URL ?>/assets/images/logo.png'"><div><strong><?= h($t['name']) ?></strong><br><small class="text-muted"><?= h($t['id']) ?></small></div></div></td>
                                <td><?= number_format($t['rider_count']) ?></td>
                                <td><?= (int) $t['stage_count'] ?></td>
                                <td><?= h($t['currency']) ?> <?= number_format($t['annual_fee']) ?></td>
                                <td><?= h($t['currency']) ?> <?= number_format($t['revenue_collected']) ?></td>
                                <td><?= h($t['currency']) ?> <?= number_format(round($t['platform_share'])) ?></td>
                                <td><span class="badge bg-<?= $sc[$t['status']] ?? 'secondary' ?>"><?= strtoupper($t['status']) ?></span></td>
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
