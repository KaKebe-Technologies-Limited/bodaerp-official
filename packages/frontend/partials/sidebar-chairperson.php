<?php
$badgeRiders     = fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ?", [$_SESSION['stage_id']]);
$badgeDefaulters = fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status = 'expired'", [$_SESSION['stage_id']]);
$badgeExpiry     = fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status = 'active' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)", [$_SESSION['stage_id']]);
$nav = [
    'dashboard'        => ['dashboard.php', 'fa-chart-line', 'Dashboard', null],
    'my-riders'        => ['my-riders.php', 'fa-users', 'My Riders', ['bg-primary', $badgeRiders]],
    'register-rider'   => ['register-rider.php', 'fa-user-plus', 'Add Rider', null],
    'collect-payments' => ['collect-payments.php', 'fa-money-bill-wave', 'Payments', null],
    'defaulters'       => ['defaulters.php', 'fa-exclamation-triangle', 'Defaulters', ['bg-danger', $badgeDefaulters]],
    'expiry-alerts'    => ['expiry-alerts.php', 'fa-bell', 'Expiry Alerts', ['bg-warning text-dark', $badgeExpiry]],
    'stage-report'     => ['stage-report.php', 'fa-file-alt', 'Stage Report', null],
    'stage-settings'   => ['stage-settings.php', 'fa-cog', 'Settings', null],
];
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="BodaERP">
        <h5 class="text-gradient-blue">Boda<span style="background: var(--secondary-red-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">ERP</span></h5>
        <small><?= h($_SESSION['city_name'] ?? 'Chairperson') ?></small>
    </div>
    <ul class="sidebar-menu">
        <?php foreach ($nav as $key => [$href, $icon, $label, $badge]): ?>
        <li class="<?= $active === $key ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/pages/chairperson/<?= $href ?>">
                <i class="fas <?= $icon ?>"></i>
                <span><?= h($label) ?></span>
                <?php if ($badge): ?><span class="badge <?= $badge[0] ?>"><?= (int) $badge[1] ?></span><?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
        <?php require __DIR__ . '/logout-item.php'; ?>
