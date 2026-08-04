<?php
$badgeUnread = fetchValue("SELECT COUNT(*) FROM notifications WHERE rider_id = ? AND is_read = 0", [$_SESSION['rider_id']]);
$nav = [
    'dashboard'        => ['dashboard.php', 'fa-chart-line', 'Dashboard', null],
    'my-profile'       => ['my-profile.php', 'fa-user', 'My Profile', null],
    'my-id-card'       => ['my-id-card.php', 'fa-id-card', 'My ID Card', null],
    'payment-history'  => ['payment-history.php', 'fa-receipt', 'Payment History', null],
    'renewal-status'   => ['renewal-status.php', 'fa-sync-alt', 'Renewal Status', null],
    'notifications'    => ['notifications.php', 'fa-bell', 'Notifications', $badgeUnread ? ['bg-danger', $badgeUnread] : null],
];
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="BodaERP">
        <h5 class="text-gradient-blue">Boda<span style="background: var(--secondary-red-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">ERP</span></h5>
        <small><?= h($_SESSION['city_name'] ?? 'Rider') ?></small>
    </div>
    <ul class="sidebar-menu">
        <?php foreach ($nav as $key => [$href, $icon, $label, $badge]): ?>
        <li class="<?= $active === $key ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/pages/rider/<?= $href ?>">
                <i class="fas <?= $icon ?>"></i>
                <span><?= h($label) ?></span>
                <?php if ($badge): ?><span class="badge <?= $badge[0] ?>"><?= (int) $badge[1] ?></span><?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
        <?php require __DIR__ . '/logout-item.php'; ?>
