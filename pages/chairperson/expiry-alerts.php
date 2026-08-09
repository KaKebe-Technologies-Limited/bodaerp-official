<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['chairperson']);
$active = 'expiry-alerts';
$stageId = $_SESSION['stage_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $riderId = (int) ($_POST['rider_id'] ?? 0);
    $rider = fetchOne("SELECT id, user_id, full_name FROM riders WHERE id = ? AND stage_id = ?", [$riderId, $stageId]);
    if ($rider && $rider['user_id']) {
        runQuery("INSERT INTO notifications (rider_id,type,message) VALUES (?,'Renewal Reminder', ?)",
            [$riderId, 'Your annual tax is due for renewal soon. Please make a payment to stay compliant.']);
    }
    if ($rider) audit_log('CREATE', 'reminder', (string) $riderId, 'Expiry reminder sent to ' . $rider['full_name']);
    redirect('/pages/chairperson/expiry-alerts.php');
}

$riders = fetchAll(
    "SELECT *, DATEDIFF(expiry_date, CURDATE()) AS days_left FROM riders
     WHERE stage_id = ? AND status = 'active' AND deleted_at IS NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY expiry_date ASC", [$stageId]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Expiry Alerts - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Expiry Alerts'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area">
        <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="fas fa-bell me-2"></i>Riders whose permits expire within the next 30 days.</span>
            <a href="<?= BASE_URL ?>/pages/chairperson/expiring-soon.php" class="btn btn-sm btn-outline-dark no-print"><i class="fas fa-chart-bar me-1"></i>View Breakdown</a>
        </div>
        <div class="d-flex justify-content-end mb-2 no-print">
            <?php $exportTableId='expiryTable'; $exportFilename='expiry_alerts'; $exportTitle='Expiry Alerts'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
        </div>
        <div class="card"><div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="expiryTable">
                    <thead><tr><th>Rider</th><th>Plate</th><th>Expiry Date</th><th>Days Left</th><th class="no-export">Action</th></tr></thead>
                    <tbody>
                    <?php if (!$riders): ?><tr><td colspan="5" class="text-center py-4 text-muted">No upcoming expiries</td></tr><?php endif; ?>
                    <?php foreach ($riders as $r): $days = (int) $r['days_left']; $badge = $days <= 7 ? 'bg-danger' : 'bg-warning text-dark'; ?>
                        <tr>
                            <td><strong><?= h($r['full_name']) ?></strong></td>
                            <td><?= h($r['bike_plate']) ?></td>
                            <td><?= formatDate($r['expiry_date']) ?></td>
                            <td><span class="badge <?= $badge ?>"><?= $days ?> days</span></td>
                            <td class="no-export">
                                <form method="post" class="d-inline" onsubmit="return confirm('Send renewal reminder?');">
                                    <?= csrf_field() ?><input type="hidden" name="rider_id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning"><i class="fas fa-bell"></i> Remind</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
