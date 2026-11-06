<?php
// Portal sidebar for every role, built from portal_nav() (packages/backend/portal_nav.php). A group with several
// pages is a button that opens its pages as a dropdown; groups stay open until closed, and the group of the
// current page always starts open. On a laptop the sidebar folds to icons (main.js remembers the choice). The page sets
// $active to its menu key. Profile, My account and Sign out live in the top bar.
require_once __DIR__ . '/../../backend/portal_nav.php';
$portalNav = portal_nav_current();
$siteName = get_setting('site_name', 'BodaERP');
$active = $active ?? '';
$navHref = fn(string $file) => BASE_URL . '/pages/' . $portalNav['folder'] . '/' . $file;
$navCount = fn(?array $badge) => $badge && (int) $badge[0] > 0 ? '<span class="nav-count tone-' . h($badge[1]) . '">' . ((int) $badge[0] > 99 ? '99+' : (int) $badge[0]) . '</span>' : '';
?>
<a class="skip-link" href="#mainContent">Skip to main content</a>
<nav class="sidebar" id="sidebar" aria-label="Portal">
    <div class="sidebar-head">
        <a class="sidebar-brand" href="<?= h($navHref('dashboard.php')) ?>">
            <img src="<?= h(brand_logo_url()) ?>" alt="" width="36" height="36">
            <span class="sidebar-brand-text">
                <span class="brand-word"><?php if (str_ends_with($siteName, 'ERP')): ?><?= h(mb_substr($siteName, 0, -3)) ?><b>ERP</b><?php else: ?><?= h($siteName) ?><?php endif; ?></span>
                <span class="brand-scope"><?= h($portalNav['scope']) ?></span>
            </span>
        </a>
        <button type="button" class="sidebar-fold" data-sidebar-fold aria-label="Fold the menu to icons" aria-pressed="false"><i class="fas fa-angles-left" aria-hidden="true"></i></button>
        <button type="button" class="sidebar-close d-lg-none" data-sidebar-close aria-label="Close menu"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    </div>
    <div class="sidebar-scroll">
        <ul class="nav-tree">
        <?php foreach ($portalNav['groups'] as $groupLabel => $groupLinks):
            $groupId = 'navg-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($groupLabel));
            $groupIcon = $portalNav['icons'][$groupLabel] ?? 'fa-folder';
            $current = isset($groupLinks[$active]);
            if (count($groupLinks) === 1):
                $key = array_key_first($groupLinks);
                [$file, $icon, $label, $badge] = $groupLinks[$key]; ?>
            <li class="nav-node">
                <a class="nav-parent<?= $current ? ' is-active' : '' ?>" href="<?= h($navHref($file)) ?>"<?= $current ? ' aria-current="page"' : '' ?> data-tip="<?= h($label) ?>">
                    <i class="fas <?= h($icon) ?> nav-icon" aria-hidden="true"></i><span class="nav-label"><?= h($label) ?></span><?= $navCount($badge) ?>
                </a>
            </li>
            <?php else:
                $total = array_sum(array_map(fn($l) => $l[3] && (int) $l[3][0] > 0 ? (int) $l[3][0] : 0, $groupLinks));
                $urgent = array_filter($groupLinks, fn($l) => $l[3] && (int) $l[3][0] > 0 && $l[3][1] === 'danger'); ?>
            <li class="nav-node<?= $current ? ' is-current is-open' : '' ?>">
                <button type="button" class="nav-parent" aria-expanded="<?= $current ? 'true' : 'false' ?>" aria-controls="<?= h($groupId) ?>" data-nav-group data-tip="<?= h($groupLabel) ?>">
                    <i class="fas <?= h($groupIcon) ?> nav-icon" aria-hidden="true"></i><span class="nav-label"><?= h($groupLabel) ?></span>
                    <?php if ($total): ?><span class="nav-dot tone-<?= $urgent ? 'danger' : 'info' ?>" aria-label="<?= $total ?> waiting"></span><?php endif; ?>
                    <i class="fas fa-chevron-down nav-chevron" aria-hidden="true"></i>
                </button>
                <ul class="nav-children" id="<?= h($groupId) ?>"<?= $current ? '' : ' hidden' ?>>
                    <?php foreach ($groupLinks as $key => [$file, $icon, $label, $badge]): $isActive = $active === $key; ?>
                    <li><a class="nav-child<?= $isActive ? ' is-active' : '' ?>" href="<?= h($navHref($file)) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><span><?= h($label) ?></span><?= $navCount($badge) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        </ul>
    </div>
</nav>
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>
