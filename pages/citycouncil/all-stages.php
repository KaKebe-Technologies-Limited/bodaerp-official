<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['city_admin']);
$active = 'all-stages';
$cityId = $_SESSION['city_id'];

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$stageWhere = "WHERE s.city_id = :city"; $stageParams = ['city' => $cityId];
if ($search !== '') { $stageWhere .= " AND s.name LIKE :q"; $stageParams['q'] = "%$search%"; }
if ($statusFilter !== '') { $stageWhere .= " AND s.status = :status"; $stageParams['status'] = $statusFilter; }

$stages = fetchAll("SELECT s.*, v.rider_count, v.active_count, v.compliance_pct,
    (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.rider_id IN (SELECT id FROM riders WHERE stage_id = s.id) AND p.status='Confirmed') AS revenue
    FROM stages s LEFT JOIN v_stage_stats v ON v.stage_id = s.id
    $stageWhere ORDER BY s.name", $stageParams);

$totalStages = count($stages);
$activeStages = count(array_filter($stages, fn($s) => $s['status'] === 'active'));
$totalRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ?", [$cityId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'All Stages - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
    <style>
        .council-stat-card { background: white; border-radius: 14px; padding: 16px 20px; border-left: 4px solid #0d6efd; box-shadow: 0 2px 12px rgba(0,0,0,0.04); }
        .council-stat-card .stat-number { font-size: 1.6rem; font-weight: 900; color: #1a1a2e; line-height: 1; }
        .council-stat-card .stat-label { font-size: 0.75rem; color: #6c757d; font-weight: 500; }
        .council-stat-card .stat-icon { font-size: 1.6rem; opacity: 0.15; }
        .council-stat-card.blue { border-left-color: #0d6efd; }
        .council-stat-card.green { border-left-color: #198754; }
        .council-stat-card.red { border-left-color: #dc3545; }
        .council-stat-card.gold { border-left-color: #f59e0b; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">All Stages</span></h5>
            </div>
            <a href="<?= BASE_URL ?>/pages/citycouncil/stage-management.php" class="btn btn-primary btn-sm"><i class="fas fa-cog me-1"></i>Manage Stages</a>
        </div>
    </header>

    <div class="content-area">
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="council-stat-card blue"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= $totalStages ?></span><div class="stat-label">Total Stages</div></div><i class="fas fa-map-marker-alt stat-icon"></i></div></div></div>
            <div class="col-md-3 col-6"><div class="council-stat-card green"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= $activeStages ?></span><div class="stat-label">Active Stages</div></div><i class="fas fa-check-circle stat-icon"></i></div></div></div>
            <div class="col-md-3 col-6"><div class="council-stat-card red"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= $totalStages - $activeStages ?></span><div class="stat-label">Inactive Stages</div></div><i class="fas fa-exclamation-circle stat-icon"></i></div></div></div>
            <div class="col-md-3 col-6"><div class="council-stat-card gold"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= number_format($totalRiders) ?></span><div class="stat-label">Total Riders</div></div><i class="fas fa-users stat-icon"></i></div></div></div>
        </div>

        <div class="card mb-3 no-print">
            <div class="card-body">
                <form class="row g-2 align-items-center">
                    <div class="col-md-5"><input type="text" class="form-control" name="q" value="<?= h($search) ?>" placeholder="Search stage name..."></div>
                    <div class="col-md-3">
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
                            <option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-outline-primary w-100"><i class="fas fa-search me-1"></i>Search</button></div>
                    <div class="col-md-2 text-md-end">
                        <?php $exportTableId='stagesExportTable'; $exportFilename='all_stages'; $exportTitle='All Stages'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
                    </div>
                </form>
            </div>
        </div>

        <table id="stagesExportTable" class="d-none">
            <thead><tr><th>Stage</th><th>Location</th><th>Status</th><th>Riders</th><th>Revenue</th><th>Compliance</th><th>Chairperson</th><th>Phone</th></tr></thead>
            <tbody>
            <?php foreach ($stages as $s): ?>
                <tr>
                    <td><?= h($s['name']) ?></td>
                    <td><?= h($s['location'] ?: '—') ?></td>
                    <td><?= ucfirst($s['status']) ?></td>
                    <td><?= (int) $s['rider_count'] ?></td>
                    <td><?= h(formatCurrency($s['revenue'])) ?></td>
                    <td><?= (float) ($s['compliance_pct'] ?? 0) ?>%</td>
                    <td><?= h($s['chairperson_name'] ?: '—') ?></td>
                    <td><?= h($s['chairperson_phone'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="row g-3">
        <?php foreach ($stages as $s):
            $compliance = (float) ($s['compliance_pct'] ?? 0);
            $cBadge = $compliance >= 80 ? 'bg-success' : ($compliance >= 70 ? 'bg-primary' : ($compliance >= 50 ? 'bg-warning text-dark' : 'bg-danger'));
        ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="fw-bold mb-0"><?= h($s['name']) ?></h6>
                            <span class="badge bg-<?= $s['status']==='active'?'success':'secondary' ?>"><?= ucfirst($s['status']) ?></span>
                        </div>
                        <small class="text-muted d-block mb-3"><?= h($s['location'] ?: 'No location set') ?></small>
                        <div class="row g-0 text-center mb-3">
                            <div class="col-4 border-end"><div class="fw-bold text-primary"><?= (int) $s['rider_count'] ?></div><small class="text-muted" style="font-size:0.7rem;">Riders</small></div>
                            <div class="col-4 border-end"><div class="fw-bold text-success"><?= h(formatCurrency($s['revenue'])) ?></div><small class="text-muted" style="font-size:0.7rem;">Revenue</small></div>
                            <div class="col-4"><div class="fw-bold"><span class="badge <?= $cBadge ?>"><?= $compliance ?>%</span></div><small class="text-muted" style="font-size:0.7rem;">Compliance</small></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <small class="text-muted"><i class="fas fa-user me-1"></i><?= h($s['chairperson_name'] ?: 'No chairperson') ?></small>
                            <small class="text-muted"><?= h($s['chairperson_phone'] ?: '') ?></small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
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
