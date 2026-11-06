<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_once __DIR__ . '/../../../backend/sms.php';
require_role(['chairperson']);
$active = 'expiry-alerts';
$stageId = $_SESSION['stage_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $riderId = (int) ($_POST['rider_id'] ?? 0);
    $rider = fetchOne("SELECT id, user_id, full_name FROM riders WHERE id = ? AND stage_id = ?", [$riderId, $stageId]);
    if ($rider) flash('success', rider_send_reminder($riderId, 'Your permit expires soon. Renew it with your stage chairperson before it lapses.'));
    if ($rider) audit_log('CREATE', 'reminder', (string) $riderId, 'Expiry reminder sent to ' . $rider['full_name']);
    redirect('/pages/chairperson/expiry-alerts.php');
}

$riders = fetchAll(
    "SELECT *, DATEDIFF(expiry_date, CURDATE()) AS days_left FROM riders
     WHERE stage_id = ? AND status = 'active' AND deleted_at IS NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY expiry_date ASC", [$stageId]
);
$criticalCount = count(array_filter($riders, fn($r) => (int) $r['days_left'] <= 7));
$upcomingRevenue = array_sum(array_column($riders, 'annual_tax'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Expiry alerts | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Expiry alerts'; $pageSubtitle = 'Riders whose permits expire within the next 30 days'; require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <div class="row g-3 row-cols-1 row-cols-md-3 mb-4">
            <div class="col"><div class="stat-hero-card tone-gold"><div><div class="stat-hero-value"><?= count($riders) ?></div><div class="stat-hero-label">Expiring in 30 days</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-red"><div><div class="stat-hero-value"><?= $criticalCount ?></div><div class="stat-hero-label">Critical (&le;7 days)</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-blue"><div><div class="stat-hero-value"><?= h(formatCurrency($upcomingRevenue)) ?></div><div class="stat-hero-label">Renewal value</div></div></div></div>
        </div>

        <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Riders whose permits expire within the next 30 days.</span>
            <a href="<?= BASE_URL ?>/pages/chairperson/expiring-soon.php" class="btn btn-sm btn-outline-dark no-print">View breakdown</a>
        </div>
        <div class="d-flex justify-content-end mb-2 no-print">
            <?php $exportTableId='expiryTable'; $exportFilename='expiry_alerts'; $exportTitle='Expiry Alerts'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
        </div>
        <div class="card"><div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="expiryTable">
                    <thead><tr><th>Rider</th><th>Plate</th><th>Expiry date</th><th>Days left</th><th class="no-export">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($riders as $r): $days = (int) $r['days_left']; $badge = $days <= 7 ? 'bg-danger' : 'bg-warning text-dark'; ?>
                        <tr>
                            <td><?= person_cell($r['photo_path'], $r['full_name']) ?></td>
                            <td><?= h($r['bike_plate']) ?></td>
                            <td><?= formatDate($r['expiry_date']) ?></td>
                            <td><span class="badge <?= $badge ?>"><?= $days ?> days</span></td>
                            <td class="no-export">
                                <form method="post" class="d-inline" data-confirm="Send <?= h($r['full_name']) ?> a reminder that their permit expires on <?= h(formatDate($r['expiry_date'])) ?>?" data-confirm-ok="Send reminder">
                                    <?= csrf_field() ?><input type="hidden" name="rider_id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning">Remind</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$riders): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h5>No upcoming expiries</h5>
                    <p>No riders in your stage have permits expiring in the next 30 days.</p>
                </div>
                <?php endif; ?>
            </div>
        </div></div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
</body>
</html>
