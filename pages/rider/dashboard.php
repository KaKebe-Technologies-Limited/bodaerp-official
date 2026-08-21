<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['rider']);
$active = 'dashboard';
$riderId = $_SESSION['rider_id'];
if (!$riderId) redirect('/login.php');

$rider = fetchOne("SELECT r.*, s.name AS stage_name FROM riders r LEFT JOIN stages s ON s.id=r.stage_id WHERE r.id = ?", [$riderId]);
$daysLeft = daysUntil($rider['expiry_date']);
$hasPaid = (bool) fetchValue("SELECT COUNT(*) FROM payments WHERE rider_id=? AND status='Confirmed' AND fiscal_year=?", [$riderId, CURRENT_FISCAL_YEAR]);
$recentNotifs = fetchAll("SELECT * FROM notifications WHERE rider_id=? ORDER BY created_at DESC LIMIT 5", [$riderId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'My Dashboard - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-rider.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'My Dashboard'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area">
        <div class="dash-banner theme-blue">
            <div class="d-flex justify-content-between align-items-center flex-wrap dash-banner-row">
                <div>
                    <h4>👋 Welcome back, <?= h(explode(' ', $rider['full_name'])[0]) ?></h4>
                    <p>Here's the status of your permit at <?= h($rider['stage_name'] ?? 'your stage') ?>.</p>
                </div>
                <span class="dash-badge"><i class="fas fa-motorcycle me-1"></i><?= h($rider['bike_plate'] ?? '') ?></span>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="stat-card-modern"><div class="icon-badge <?= $rider['status']==='active'?'green':'red' ?>"><i class="fas fa-shield-alt"></i></div><div><span class="stat-number"><?= ucfirst($rider['status']) ?></span><div class="stat-label">Status</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-card-modern"><div class="icon-badge blue"><i class="fas fa-calendar-alt"></i></div><div><span class="stat-number"><?= formatDate($rider['expiry_date']) ?></span><div class="stat-label">Expiry Date</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-card-modern"><div class="icon-badge gold"><i class="fas fa-coins"></i></div><div><span class="stat-number"><?= h(formatCurrency($rider['annual_tax'])) ?></span><div class="stat-label">Annual Tax</div></div></div></div>
            <div class="col-md-3 col-6">
                <a href="<?= BASE_URL ?>/pages/rider/payment-history.php" class="text-decoration-none">
                    <div class="stat-card-modern"><div class="icon-badge <?= $hasPaid?'green':'red' ?>"><i class="fas fa-receipt"></i></div><div><span class="stat-number"><?= $hasPaid ? 'Paid' : 'Unpaid' ?></span><div class="stat-label">Payment Status</div></div></div>
                </a>
            </div>
        </div>

        <?php if (!$hasPaid): ?>
        <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div><i class="fas fa-exclamation-triangle me-2"></i>Your annual tax (<strong><?= h(formatCurrency($rider['annual_tax'])) ?></strong>) hasn't been paid for <?= h(CURRENT_FISCAL_YEAR) ?>.</div>
            <a href="<?= BASE_URL ?>/pages/rider/payment-history.php" class="btn btn-primary btn-sm"><i class="fas fa-mobile-alt me-1"></i>Pay Now</a>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <h6 class="fw-bold mb-3">Quick Links</h6>
                <div class="row g-3">
                    <div class="col-6 col-md-3"><a href="<?= BASE_URL ?>/pages/rider/my-profile.php" class="text-decoration-none"><div class="quick-action-card"><div class="icon-badge blue"><i class="fas fa-user"></i></div><h6>My Profile</h6><small>Personal details</small></div></a></div>
                    <div class="col-6 col-md-3"><a href="<?= BASE_URL ?>/pages/rider/my-id-card.php" class="text-decoration-none"><div class="quick-action-card"><div class="icon-badge green"><i class="fas fa-id-card"></i></div><h6>My ID Card</h6><small>View &amp; print</small></div></a></div>
                    <div class="col-6 col-md-3"><a href="<?= BASE_URL ?>/pages/rider/payment-history.php" class="text-decoration-none"><div class="quick-action-card"><div class="icon-badge gold"><i class="fas fa-receipt"></i></div><h6>Payments</h6><small>Full history</small></div></a></div>
                    <div class="col-6 col-md-3"><a href="<?= BASE_URL ?>/pages/rider/renewal-status.php" class="text-decoration-none"><div class="quick-action-card"><div class="icon-badge red"><i class="fas fa-sync-alt"></i></div><h6>Renewal</h6><small>Current status</small></div></a></div>
                </div>
                <?php if ($daysLeft !== null && $daysLeft <= 30 && $rider['status'] === 'active'): ?>
                <div class="alert alert-warning mt-4"><i class="fas fa-exclamation-triangle me-2"></i>Your permit expires in <?= $daysLeft ?> days. Please renew soon.</div>
                <?php elseif ($rider['status'] === 'expired'): ?>
                <div class="alert alert-danger mt-4"><i class="fas fa-times-circle me-2"></i>Your permit has expired. Please contact your stage chairperson to renew.</div>
                <?php endif; ?>
            </div>
            <div class="col-lg-4">
                <div class="card"><div class="card-body">
                    <h6 class="fw-bold mb-3">Recent Activity</h6>
                    <?php if (!$recentNotifs): ?><p class="text-muted small">No recent activity</p><?php endif; ?>
                    <?php foreach ($recentNotifs as $n): ?>
                        <div class="d-flex align-items-start gap-2 py-2 border-bottom">
                            <i class="fas fa-circle text-primary" style="font-size:0.4rem;margin-top:6px;"></i>
                            <div><div class="small fw-semibold"><?= h($n['type']) ?></div><div class="text-muted" style="font-size:0.75rem;"><?= timeAgo($n['created_at']) ?></div></div>
                        </div>
                    <?php endforeach; ?>
                </div></div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
</body>
</html>
