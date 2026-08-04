<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['rider']);
$active = 'renewal-status';
$riderId = $_SESSION['rider_id'];
if (!$riderId) redirect('/login.php');

$rider = fetchOne("SELECT * FROM riders WHERE id = ?", [$riderId]);
$daysLeft = daysUntil($rider['expiry_date']);
$hasPaid = (bool) fetchValue("SELECT COUNT(*) FROM payments WHERE rider_id=? AND status='Confirmed' AND fiscal_year=?", [$riderId, CURRENT_FISCAL_YEAR]);

$progress = 0;
if ($rider['status'] === 'active' && $daysLeft !== null) {
    $progress = max(0, min(100, round(100 * (365 - $daysLeft) / 365)));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Renewal Status - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-rider.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Renewal Status'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area">
        <div class="card"><div class="card-body">
            <div class="row g-4 align-items-center">
                <div class="col-md-3 text-center">
                    <h6 class="text-muted">Current Status</h6>
                    <h3 class="fw-bold <?= $rider['status']==='active'?'text-success':($rider['status']==='expired'?'text-danger':'text-warning') ?>"><?= ucfirst($rider['status']) ?></h3>
                </div>
                <div class="col-md-3 text-center">
                    <h6 class="text-muted">Expiry Date</h6>
                    <h5 class="fw-bold"><?= formatDate($rider['expiry_date']) ?></h5>
                </div>
                <div class="col-md-3 text-center">
                    <h6 class="text-muted">Days Remaining</h6>
                    <h5 class="fw-bold"><?= $daysLeft !== null ? ($daysLeft >= 0 ? $daysLeft . ' days' : 'Expired') : '—' ?></h5>
                </div>
                <div class="col-md-3 text-center">
                    <h6 class="text-muted">Payment Status</h6>
                    <h5 class="fw-bold <?= $hasPaid?'text-success':'text-danger' ?>"><?= $hasPaid ? 'Paid' : 'Unpaid' ?></h5>
                </div>
            </div>
            <hr>
            <?php if ($rider['status'] === 'active'): ?>
            <div class="progress" style="height: 20px;">
                <div class="progress-bar bg-<?= $progress > 90 ? 'danger' : 'success' ?>" style="width: <?= $progress ?>%"><?= $progress ?>%</div>
            </div>
            <small class="text-muted d-block mt-2">Permit validity period elapsed</small>
            <?php elseif ($rider['status'] === 'expired'): ?>
                <div class="alert alert-danger mb-0"><i class="fas fa-times-circle me-2"></i>Your permit has expired. Please visit your stage chairperson to renew and make payment.</div>
            <?php else: ?>
                <div class="alert alert-warning mb-0"><i class="fas fa-clock me-2"></i>Your registration is pending payment confirmation.</div>
            <?php endif; ?>
        </div></div>
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
