<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['chairperson']);
require_once __DIR__ . '/../../../backend/db_helpers.php';
$active = 'dashboard';
$stageId = $_SESSION['stage_id'];

$stage = fetchOne("SELECT * FROM stages WHERE id = ?", [$stageId]);
$totalRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND deleted_at IS NULL", [$stageId]);
$activeRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status='active' AND deleted_at IS NULL", [$stageId]);
$defaulters = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status='expired' AND deleted_at IS NULL", [$stageId]);
$revenue = (float) fetchValue("SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.status='Confirmed' AND p.rider_id IN (SELECT id FROM riders WHERE stage_id=?)", [$stageId]);

$recentRiders = fetchAll("SELECT * FROM riders WHERE stage_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 8", [$stageId]);
$pageTitle = get_setting('chairperson_dashboard_title', 'Stage dashboard');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = get_setting('chairperson_dashboard_title', 'Stage dashboard') . ' | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
    <style>
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
        .status-dot.active { background: #198754; } .status-dot.expired { background: #dc3545; } .status-dot.pending { background: #ffc107; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = $stage['name'] . ' Dashboard'; require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <div class="dash-banner theme-green">
            <div class="d-flex justify-content-between align-items-center flex-wrap dash-banner-row">
                <div>
                    <h4><?= h($stage['name']) ?></h4>
                    <p><?= h(get_setting('chairperson_welcome_text', 'Riders, cash collected and renewals due at your stage.')) ?></p>
                </div>
                <span class="dash-meta"><?= h(CURRENT_FISCAL_YEAR) ?></span>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="stat-hero-card tone-blue"><div><div class="stat-hero-value"><?= $totalRiders ?></div><div class="stat-hero-label">Total riders</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card tone-green"><div><div class="stat-hero-value"><?= $activeRiders ?></div><div class="stat-hero-label">Active (paid)</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card tone-red"><div><div class="stat-hero-value"><?= $defaulters ?></div><div class="stat-hero-label">Defaulters</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card tone-gold"><div><div class="stat-hero-value"><?= h(formatCurrency($revenue)) ?></div><div class="stat-hero-label">Revenue collected</div></div></div></div>
        </div>

        <h6 class="fw-bold mb-3">Quick actions</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><a href="<?= BASE_URL ?>/pages/chairperson/register-rider.php" class="text-decoration-none"><div class="quick-action-card"><h6>Add rider</h6><small>Register new rider</small></div></a></div>
            <div class="col-md-3 col-6"><a href="<?= BASE_URL ?>/pages/chairperson/my-riders.php" class="text-decoration-none"><div class="quick-action-card"><h6>View riders</h6><small>All stage members</small></div></a></div>
            <div class="col-md-3 col-6"><a href="<?= BASE_URL ?>/pages/chairperson/collect-payments.php" class="text-decoration-none"><div class="quick-action-card"><h6>Record payment</h6><small>Collect taxes</small></div></a></div>
            <div class="col-md-3 col-6"><a href="<?= BASE_URL ?>/pages/chairperson/stage-report.php" class="text-decoration-none"><div class="quick-action-card"><h6>Stage report</h6><small>View performance</small></div></a></div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Recent riders</h5>
                    <a href="<?= BASE_URL ?>/pages/chairperson/my-riders.php" class="btn btn-sm btn-outline-primary">All riders at this stage</a>
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
                                <td><a href="<?= BASE_URL ?>/pages/chairperson/rider-profile.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-primary" aria-label="View <?= h($r['full_name']) ?>" title="View"><i class="fas fa-eye" aria-hidden="true"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
</body>
</html>
