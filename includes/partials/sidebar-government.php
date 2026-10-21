<?php
require_once __DIR__ . '/../anomaly_engine.php';
$badgeFlags = anomaly_open_count('high');
$nav = [
    'dashboard'    => ['dashboard.php', 'fa-globe-africa', 'National Dashboard', null],
    'comparison'   => ['comparison.php', 'fa-balance-scale', 'District & City Comparison', null],
    'registry'     => ['registry.php', 'fa-id-card', 'National Rider Registry', null],
    'enforcement'  => ['enforcement.php', 'fa-gavel', 'National Enforcement', null],
    'data-flags'   => ['data-flags.php', 'fa-shield-alt', 'Data Integrity Flags', $badgeFlags ? ['bg-danger', $badgeFlags] : null],
    'reports'      => ['reports.php', 'fa-file-invoice', 'National Reports', null],
];
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="BodaERP">
        <h5 class="text-gradient-blue">Boda<span style="background: var(--secondary-red-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">ERP</span></h5>
        <small>Government Portal</small>
    </div>
    <ul class="sidebar-menu">
        <?php foreach ($nav as $key => [$href, $icon, $label, $badge]): ?>
        <li class="<?= $active === $key ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/pages/government/<?= $href ?>">
                <i class="fas <?= $icon ?>"></i>
                <span><?= h($label) ?></span>
                <?php if ($badge): ?><span class="badge <?= $badge[0] ?>"><?= (int) $badge[1] ?></span><?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
        <?php require __DIR__ . '/logout-item.php'; ?>
