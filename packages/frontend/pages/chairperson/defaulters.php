<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_once __DIR__ . '/../../../backend/sms.php';
require_role(['chairperson']);
$active = 'defaulters';
$stageId = $_SESSION['stage_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $riderId = (int) ($_POST['rider_id'] ?? 0);
    $rider = fetchOne("SELECT id, user_id, full_name FROM riders WHERE id = ? AND stage_id = ?", [$riderId, $stageId]);
    if ($rider) {
        $message = mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 120) ?: 'Your permit has expired. Renew it with me at the stage to keep riding legally.';
        flash('success', rider_send_reminder($riderId, $message));
        audit_log('CREATE', 'reminder', (string) $riderId, 'Reminder sent to ' . $rider['full_name']);
    }
    redirect('/pages/chairperson/defaulters.php');
}

$defaulters = fetchAll(
    "SELECT r.*, (SELECT MAX(paid_at) FROM payments p WHERE p.rider_id=r.id AND p.status='Confirmed') AS last_payment,
     DATEDIFF(CURDATE(), r.expiry_date) AS overdue_days
     FROM riders r WHERE r.stage_id = ? AND r.status = 'expired' AND r.deleted_at IS NULL ORDER BY overdue_days DESC", [$stageId]
);
$critical = count(array_filter($defaulters, fn($d) => $d['overdue_days'] > 90));
$amountOwed = array_sum(array_column($defaulters, 'annual_tax'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Defaulters | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Riders owing'; $pageSubtitle = 'Riders with expired permits who owe annual tax'; require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <div class="row g-3 row-cols-1 row-cols-md-3 mb-4">
            <div class="col"><div class="stat-hero-card tone-red"><div class="stat-hero-icon"><i class="fas fa-user-times"></i></div><div><div class="stat-hero-value"><?= count($defaulters) ?></div><div class="stat-hero-label">Total defaulters</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-dark"><div class="stat-hero-icon"><i class="fas fa-fire"></i></div><div><div class="stat-hero-value"><?= $critical ?></div><div class="stat-hero-label">Critical (&gt;90 days)</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-gold"><div class="stat-hero-icon"><i class="fas fa-coins"></i></div><div><div class="stat-hero-value"><?= h(formatCurrency($amountOwed)) ?></div><div class="stat-hero-label">Amount owed</div></div></div></div>
        </div>

        <div class="d-flex justify-content-end mb-2 no-print">
            <?php $exportTableId='defaultersTable'; $exportFilename='my_defaulters'; $exportTitle='Riders Owing'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
        </div>
        <div class="card"><div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="defaultersTable">
                    <thead><tr><th>#</th><th>Rider</th><th>Plate</th><th>Last payment</th><th>Overdue</th><th>Amount</th><th>Status</th><th class="no-export">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($defaulters as $i => $d): $days = (int) $d['overdue_days'];
                        [$badge, $label] = $days > 90 ? ['bg-danger', 'Critical'] : ($days > 60 ? ['bg-warning text-dark', 'Warning'] : ['bg-secondary', 'Normal']); ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><strong><?= h($d['full_name']) ?></strong></td>
                            <td><?= h($d['bike_plate']) ?></td>
                            <td><?= $d['last_payment'] ? formatDate($d['last_payment']) : 'Never' ?></td>
                            <td><?= $days ?> days</td>
                            <td><?= h(formatCurrency($d['annual_tax'])) ?></td>
                            <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
                            <td class="no-export">
                                <form method="post" class="d-inline" data-confirm="Send reminder to <?= h($d['full_name']) ?>?">
                                    <?= csrf_field() ?><input type="hidden" name="rider_id" value="<?= (int)$d['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning"><i class="fas fa-bell"></i> Remind</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$defaulters): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h5>No defaulters in your stage!</h5>
                    <p>Every rider in your stage is currently compliant.</p>
                </div>
                <?php endif; ?>
            </div>
        </div></div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
</body>
</html>
