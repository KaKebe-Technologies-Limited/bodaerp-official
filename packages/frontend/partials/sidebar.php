<?php
// Portal sidebar for every role, built from portal_nav() (packages/backend/portal_nav.php).
// The page sets $active to its menu key. Profile, My Account and Sign out live in the top bar.
require_once __DIR__ . '/../../backend/portal_nav.php';
$portalNav = portal_nav((string) ($_SESSION['role'] ?? ''));
$siteName = get_setting('site_name', 'BodaERP');
$active = $active ?? '';
?>
<a class="skip-link" href="#mainContent">Skip to main content</a>
<nav class="sidebar" id="sidebar" aria-label="Portal">
    <a class="sidebar-brand" href="<?= BASE_URL ?>/pages/<?= h($portalNav['folder']) ?>/dashboard.php">
        <img src="<?= h(brand_logo_url()) ?>" alt="" width="36" height="36">
        <span>
            <span class="brand-word"><?php if (str_ends_with($siteName, 'ERP')): ?><?= h(mb_substr($siteName, 0, -3)) ?><b>ERP</b><?php else: ?><?= h($siteName) ?><?php endif; ?></span>
            <span class="brand-scope"><?= h($portalNav['scope']) ?></span>
        </span>
    </a>
    <div class="sidebar-scroll">
        <?php foreach ($portalNav['groups'] as $groupLabel => $groupLinks): ?>
            <p class="sidebar-group" id="nav-<?= h(preg_replace('/[^a-z]+/', '-', strtolower($groupLabel))) ?>"><?= h($groupLabel) ?></p>
            <ul class="sidebar-menu" aria-labelledby="nav-<?= h(preg_replace('/[^a-z]+/', '-', strtolower($groupLabel))) ?>">
                <?php foreach ($groupLinks as $key => [$file, $icon, $label, $badge]): $isActive = $active === $key; ?>
                    <li<?= $isActive ? ' class="active"' : '' ?>>
                        <a href="<?= BASE_URL ?>/pages/<?= h($portalNav['folder']) ?>/<?= h($file) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                            <i class="fas <?= h($icon) ?>" aria-hidden="true"></i>
                            <span><?= h($label) ?></span>
                            <?php if ($badge && (int) $badge[0] > 0): ?><span class="nav-count tone-<?= h($badge[1]) ?>"><?= (int) $badge[0] > 99 ? '99+' : (int) $badge[0] ?></span><?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </div>
</nav>
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>
