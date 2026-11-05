<?php
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_role(['rider']);
$riderId = $_SESSION['rider_id'];
if (!$riderId) redirect('/login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    runQuery("UPDATE notifications SET is_read = 1 WHERE rider_id = ?", [$riderId]);
    redirect('/pages/rider/notifications.php');
}

$notifications = fetchAll("SELECT * FROM notifications WHERE rider_id = ? ORDER BY created_at DESC", [$riderId]);
$portalNoticesOff = (int) fetchValue("SELECT notify_in_app FROM users WHERE id = ?", [$_SESSION['user_id']]) !== 1;
$icons = [
    'Renewal Reminder' => ['fa-bell', 'warning'], 'Payment Confirmed' => ['fa-check-circle', 'success'],
    'Important Update' => ['fa-info-circle', 'primary'], 'ID Card Issued' => ['fa-id-card', 'primary'],
    'Registration Complete' => ['fa-user-check', 'success'], 'Payment Failed' => ['fa-times-circle', 'danger'],
    'Data Request' => ['fa-file-shield', 'primary'],
];
portal_start('rider', 'notifications', t('rider.notes.title', 'Messages'), [
    'subtitle' => t('rider.notes.subtitle', 'Receipts, reminders and answers from the council'),
    'actions' => $notifications ? '<form method="post">' . csrf_field() . '<button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-check-double me-1" aria-hidden="true"></i>' . h(t('rider.notes.mark_read', 'Mark all as read')) . '</button></form>' : '',
]);
?>
<?php if ($portalNoticesOff): ?>
    <div class="alert alert-info d-flex align-items-center gap-2" data-keep><i class="fas fa-bell-slash" aria-hidden="true"></i><span><?= h(t('rider.notes.off', 'New messages are switched off for your account, so only older ones appear here.')) ?> <a href="<?= BASE_URL ?>/pages/account/settings.php#notifications"><?= h(t('rider.notes.turn_on', 'Turn them back on')) ?></a></span></div>
<?php endif; ?>
<section class="card measure-wide">
    <div class="card-body p-0">
        <?php if (!$notifications): ?>
            <?= empty_state('fa-bell', t('rider.notes.empty', 'No messages yet'), t('rider.notes.empty_text', 'Payment receipts and renewal reminders will appear here.')) ?>
        <?php endif; ?>
        <ul class="list-unstyled mb-0 notification-list">
        <?php foreach ($notifications as $n): [$icon, $color] = $icons[$n['type']] ?? ['fa-bell', 'secondary']; ?>
            <li class="<?= $n['is_read'] ? '' : 'is-unread' ?>">
                <span class="text-<?= $color ?>"><i class="fas <?= $icon ?> fa-lg" aria-hidden="true"></i></span>
                <div>
                    <div class="fw-semibold"><?= h(notification_label($n['type'])) ?><?= $n['is_read'] ? '' : ' <span class="badge bg-primary ms-1">' . h(t('rider.notes.new', 'New')) . '</span>' ?></div>
                    <p class="mb-1"><?= h($n['message']) ?></p>
                    <div class="small text-muted"><?= h(timeAgo($n['created_at'])) ?></div>
                </div>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php portal_end(); ?>
