<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['chairperson']);
$active = 'expiry-alerts';
$stageId = $_SESSION['stage_id'];

$within7 = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id=? AND status='active' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)", [$stageId]);
$within14 = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id=? AND status='active' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)", [$stageId]);
$within30 = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id=? AND status='active' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)", [$stageId]);

$riders = fetchAll(
    "SELECT *, DATEDIFF(expiry_date, CURDATE()) AS days_left FROM riders
     WHERE stage_id = ? AND status = 'active' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY expiry_date ASC", [$stageId]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Expiring Soon - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Expiring Soon'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area">
        <div class="row g-3 mb-4">
            <div class="col-md-4 col-6"><div class="card"><div class="card-body"><span class="fs-4 fw-bold text-danger"><?= $within7 ?></span><div class="text-muted small">Within 7 days</div></div></div></div>
            <div class="col-md-4 col-6"><div class="card"><div class="card-body"><span class="fs-4 fw-bold text-warning"><?= $within14 ?></span><div class="text-muted small">Within 14 days</div></div></div></div>
            <div class="col-md-4 col-6"><div class="card"><div class="card-body"><span class="fs-4 fw-bold"><?= $within30 ?></span><div class="text-muted small">Within 30 days</div></div></div></div>
        </div>

        <div class="d-flex justify-content-end mb-2 no-print">
            <?php $exportTableId='expiringSoonTable'; $exportFilename='expiring_soon'; $exportTitle='Expiring Soon'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
        </div>
        <div class="card"><div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="expiringSoonTable">
                    <thead><tr><th>#</th><th>Rider</th><th>Plate</th><th>Expiry Date</th><th>Days Left</th><th class="no-export">Status</th></tr></thead>
                    <tbody>
                    <?php if (!$riders): ?><tr><td colspan="6" class="text-center py-4 text-muted">No riders expiring soon</td></tr><?php endif; ?>
                    <?php foreach ($riders as $i => $r): $days = (int) $r['days_left']; $badge = $days <= 7 ? 'bg-danger' : ($days <= 14 ? 'bg-warning text-dark' : 'bg-secondary'); ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><strong><?= h($r['full_name']) ?></strong></td>
                            <td><?= h($r['bike_plate']) ?></td>
                            <td><?= formatDate($r['expiry_date']) ?></td>
                            <td><span class="badge <?= $badge ?>"><?= $days ?> days</span></td>
                            <td class="no-export"><a href="<?= BASE_URL ?>/pages/chairperson/rider-profile.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
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
