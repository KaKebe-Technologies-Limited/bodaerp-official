<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['rider']);
$active = 'notifications';
$riderId = $_SESSION['rider_id'];
if (!$riderId) redirect('/login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    runQuery("UPDATE notifications SET is_read = 1 WHERE rider_id = ?", [$riderId]);
    redirect('/pages/rider/notifications.php');
}

$notifications = fetchAll("SELECT * FROM notifications WHERE rider_id = ? ORDER BY created_at DESC", [$riderId]);
$icons = [
    'Renewal Reminder' => ['fa-bell', 'warning'], 'Payment Confirmed' => ['fa-check-circle', 'success'],
    'Important Update' => ['fa-info-circle', 'primary'], 'ID Card Issued' => ['fa-id-card', 'primary'],
    'Registration Complete' => ['fa-user-check', 'success'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Notifications - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-rider.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Notifications'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area">
        <div class="d-flex justify-content-end mb-3">
            <form method="post"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-check-double me-1"></i>Mark All Read</button></form>
        </div>
        <div class="card"><div class="card-body p-0">
            <?php if (!$notifications): ?><div class="text-center py-5 text-muted">No notifications</div><?php endif; ?>
            <?php foreach ($notifications as $n): [$icon, $color] = $icons[$n['type']] ?? ['fa-bell', 'secondary']; ?>
                <div class="d-flex align-items-start gap-3 p-3 border-bottom <?= $n['is_read'] ? '' : 'bg-light' ?>">
                    <div class="text-<?= $color ?>"><i class="fas <?= $icon ?> fa-lg"></i></div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?= h($n['type']) ?> <?= $n['is_read'] ? '' : '<span class="badge bg-primary ms-1">New</span>' ?></div>
                        <div class="text-muted small"><?= h($n['message']) ?></div>
                        <div class="text-muted" style="font-size:0.7rem;"><?= timeAgo($n['created_at']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
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
