<?php
$nav = [
    'dashboard'         => ['dashboard.php', 'fa-th-large', 'Platform Overview', null],
    'cities'            => ['cities.php', 'fa-city', 'Manage Cities', null],
    'users'             => ['users.php', 'fa-users-cog', 'All Users', null],
    'revenue-splits'    => ['revenue-splits.php', 'fa-percentage', 'Revenue Splits', null],
    'platform-reports'  => ['platform-reports.php', 'fa-chart-bar', 'Platform Reports', null],
    'audit-logs'        => ['audit-logs.php', 'fa-history', 'Audit Logs', null],
    'platform-settings' => ['platform-settings.php', 'fa-cog', 'Platform Settings', null],
];
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="BodaERP">
        <h5 class="text-gradient-blue">Boda<span style="background: var(--secondary-red-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">ERP</span></h5>
        <small>Super Admin</small>
    </div>
    <ul class="sidebar-menu">
        <?php foreach ($nav as $key => [$href, $icon, $label, $badge]): ?>
        <li class="<?= $active === $key ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/pages/superadmin/<?= $href ?>">
                <i class="fas <?= $icon ?>"></i>
                <span><?= h($label) ?></span>
                <?php if ($badge): ?><span class="badge <?= $badge[0] ?>"><?= (int) $badge[1] ?></span><?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
        <?php require __DIR__ . '/logout-item.php'; ?>
