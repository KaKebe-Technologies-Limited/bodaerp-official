<?php
// Site header with the DB-driven menu (Super Admin → Content → Menus). $currentPath marks the active link.
$currentPath = $currentPath ?? '';
$siteName = get_setting('site_name', 'BodaERP');
$nameA = str_ends_with($siteName, 'ERP') ? mb_substr($siteName, 0, -3) : $siteName;
$nameB = str_ends_with($siteName, 'ERP') ? 'ERP' : '';
$signedIn = session_is_current();
$initials = $signedIn ? mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim((string) ($_SESSION['name'] ?? ''))) ?: [], 0, 2)))) : '';
?>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header">
    <div class="container bar">
        <a class="brand-mark" href="<?= BASE_URL ?>/">
            <img src="<?= h(brand_logo_url()) ?>" alt="" width="40" height="40">
            <span class="brand-word"><span class="b1"><?= h($nameA) ?></span><span class="b2"><?= h($nameB) ?></span></span>
        </a>
        <nav class="site-nav" id="siteNav" aria-label="Main">
            <?php foreach (menu_items('header') as $menuItem): $href = site_link($menuItem['url']); ?>
                <a href="<?= h($href) ?>"<?= $currentPath !== '' && rtrim($menuItem['url'], '/') === rtrim($currentPath, '/') ? ' aria-current="page"' : '' ?><?= $menuItem['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= h($menuItem['label']) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="header-actions">
            <?php if ($signedIn): ?>
            <a class="account-btn is-signed-in" href="<?= BASE_URL ?>/login.php"
               aria-label="<?= h('Open your portal (signed in as ' . ($_SESSION['name'] ?? '') . ')') ?>" title="Open your portal">
                <?php if ($initials !== ''): ?><span aria-hidden="true"><?= h($initials) ?></span><?php else: ?><i class="fas fa-user" aria-hidden="true"></i><?php endif; ?>
            </a>
            <?php else: ?>
            <a class="btn-brand btn-signin" href="<?= BASE_URL ?>/login.php">Sign in</a>
            <?php endif; ?>
            <button class="nav-toggle" type="button" aria-controls="siteNav" aria-expanded="false" data-nav-toggle>
                <i class="fas fa-bars" aria-hidden="true"></i><span class="visually-hidden">Menu</span>
            </button>
        </div>
    </div>
</header>
