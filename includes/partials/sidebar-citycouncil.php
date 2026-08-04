<?php
$badgeRiders     = fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ?", [$_SESSION['city_id']]);
$badgeStages     = fetchValue("SELECT COUNT(*) FROM stages WHERE city_id = ?", [$_SESSION['city_id']]);
$badgeDefaulters = fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND status = 'expired'", [$_SESSION['city_id']]);
$nav = [
    'dashboard'              => ['dashboard.php', 'fa-chart-line', 'Dashboard', null],
    'revenue-analytics'      => ['revenue-analytics.php', 'fa-money-bill-wave', 'Revenue Analytics', null],
    'compliance-monitoring'  => ['compliance-monitoring.php', 'fa-check-circle', 'Compliance', null],
    'city-riders'            => ['city-riders.php', 'fa-users', 'All Riders', ['bg-primary', $badgeRiders]],
    'all-stages'             => ['all-stages.php', 'fa-map-marker-alt', 'Stages', ['bg-primary', $badgeStages]],
    'stage-management'       => ['stage-management.php', 'fa-sitemap', 'Manage Stages', null],
    'city-defaulters'        => ['city-defaulters.php', 'fa-exclamation-triangle', 'Defaulters', ['bg-danger', $badgeDefaulters]],
    'enforcement-actions'    => ['enforcement-actions.php', 'fa-gavel', 'Enforcements', null],
    'payments_management'    => ['payments_management.php', 'fa-credit-card', 'Payments', null],
    'stage-performance'      => ['stage-performance.php', 'fa-trophy', 'Stage Performance', null],
    'council-setting'        => ['council-setting.php', 'fa-cog', 'Settings', null],
];
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="<?= h(BASE_URL . ($_SESSION['city_logo'] ?: '/assets/images/logo.png')) ?>" alt="BodaERP" onerror="this.src='<?= BASE_URL ?>/assets/images/logo.png'">
        <h5 class="text-gradient-blue">Boda<span style="background: var(--secondary-red-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">ERP</span></h5>
        <small><?= h($_SESSION['city_name'] ?? 'City Council') ?></small>
    </div>
    <ul class="sidebar-menu">
        <?php foreach ($nav as $key => [$href, $icon, $label, $badge]): ?>
        <li class="<?= $active === $key ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/pages/citycouncil/<?= $href ?>">
                <i class="fas <?= $icon ?>"></i>
                <span><?= h($label) ?></span>
                <?php if ($badge): ?><span class="badge <?= $badge[0] ?>"><?= (int) $badge[1] ?></span><?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
        <?php require __DIR__ . '/logout-item.php'; ?>
