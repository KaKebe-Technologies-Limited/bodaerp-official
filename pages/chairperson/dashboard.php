<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['chairperson']);
$active = 'dashboard';
$stageId = $_SESSION['stage_id'];

$stage = fetchOne("SELECT * FROM stages WHERE id = ?", [$stageId]);
$totalRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND deleted_at IS NULL", [$stageId]);
$activeRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status='active' AND deleted_at IS NULL", [$stageId]);
$defaulters = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status='expired' AND deleted_at IS NULL", [$stageId]);
$revenue = (float) fetchValue("SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.status='Confirmed' AND p.rider_id IN (SELECT id FROM riders WHERE stage_id=?)", [$stageId]);

$recentRiders = fetchAll("SELECT * FROM riders WHERE stage_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 8", [$stageId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'My Stage - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
    <style>
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
        .status-dot.active { background: #198754; } .status-dot.expired { background: #dc3545; } .status-dot.pending { background: #ffc107; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = $stage['name'] . ' Dashboard'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area">
        <div class="dash-banner theme-green">
            <div class="d-flex justify-content-between align-items-center flex-wrap dash-banner-row">
                <div>
                    <h4>📍 <?= h($stage['name']) ?></h4>
                    <p>Manage your stage's riders, payments and compliance from one place.</p>
                </div>
                <span class="dash-badge"><i class="fas fa-calendar me-1"></i><?= h(CURRENT_FISCAL_YEAR) ?></span>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="stat-card-modern"><div class="icon-badge blue"><i class="fas fa-users"></i></div><div><span class="stat-number"><?= $totalRiders ?></span><div class="stat-label">Total Riders</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-card-modern"><div class="icon-badge green"><i class="fas fa-check-circle"></i></div><div><span class="stat-number"><?= $activeRiders ?></span><div class="stat-label">Active (Paid)</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-card-modern"><div class="icon-badge red"><i class="fas fa-exclamation-triangle"></i></div><div><span class="stat-number"><?= $defaulters ?></span><div class="stat-label">Defaulters</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-card-modern"><div class="icon-badge gold"><i class="fas fa-money-bill-wave"></i></div><div><span class="stat-number"><?= h(formatCurrency($revenue)) ?></span><div class="stat-label">Revenue Collected</div></div></div></div>
        </div>

        <h6 class="fw-bold mb-3">Quick Actions</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><a href="<?= BASE_URL ?>/pages/chairperson/register-rider.php" class="text-decoration-none"><div class="quick-action-card"><div class="icon-badge blue"><i class="fas fa-user-plus"></i></div><h6>Add Rider</h6><small>Register new rider</small></div></a></div>
            <div class="col-md-3 col-6"><a href="<?= BASE_URL ?>/pages/chairperson/my-riders.php" class="text-decoration-none"><div class="quick-action-card"><div class="icon-badge green"><i class="fas fa-users"></i></div><h6>View Riders</h6><small>All stage members</small></div></a></div>
            <div class="col-md-3 col-6"><a href="<?= BASE_URL ?>/pages/chairperson/collect-payments.php" class="text-decoration-none"><div class="quick-action-card"><div class="icon-badge gold"><i class="fas fa-money-bill-wave"></i></div><h6>Record Payment</h6><small>Collect taxes</small></div></a></div>
            <div class="col-md-3 col-6"><a href="<?= BASE_URL ?>/pages/chairperson/stage-report.php" class="text-decoration-none"><div class="quick-action-card"><div class="icon-badge red"><i class="fas fa-file-alt"></i></div><h6>Stage Report</h6><small>View performance</small></div></a></div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-users text-primary me-2"></i>Recent Riders</h5>
                    <a href="<?= BASE_URL ?>/pages/chairperson/my-riders.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Rider</th><th>Plate</th><th>Status</th><th>Expiry</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentRiders as $r): $sc = ['active'=>'bg-success','expired'=>'bg-danger','pending'=>'bg-warning text-dark']; ?>
                            <tr>
                                <td><strong><?= h($r['full_name']) ?></strong></td>
                                <td><?= h($r['bike_plate']) ?></td>
                                <td><span class="badge <?= $sc[$r['status']] ?>"><span class="status-dot <?= h($r['status']) ?>"></span><?= ucfirst($r['status']) ?></span></td>
                                <td><?= formatDate($r['expiry_date']) ?></td>
                                <td><a href="<?= BASE_URL ?>/pages/chairperson/rider-profile.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a></td>
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
</body>
</html>
