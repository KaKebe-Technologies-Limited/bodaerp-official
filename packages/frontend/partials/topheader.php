<?php
// Portal top bar and page header. The bar is navy like the sidebar, so the two read as one frame: menu button on
// phones, where you are (group and page), the public site and the account menu. Under it the page header holds the
// title, the optional subtitle ($pageSubtitle) and page actions ($headerActions, trusted HTML built by the page).
// Flash messages appear under the header.
$tbName = (string) ($_SESSION['name'] ?? '');
$tbRole = match ($_SESSION['role'] ?? '') {
    'city_admin' => $_SESSION['city_name'] ?? 'City council',
    'government' => $_SESSION['agency_name'] ?? 'Government',
    default => role_label((string) ($_SESSION['role'] ?? '')),
};
$tbInitials = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($tbName)) ?: [], 0, 2))));
$tbAvatar = !empty($_SESSION['avatar_path']) ? media_url($_SESSION['avatar_path']) : null;
$tbTitle = preg_replace('/\s+[|-]\s+' . preg_quote(get_setting('site_name', 'BodaERP'), '/') . '$/', '', (string) ($pageTitle ?? ''));
$tbTrail = [];
foreach (($portalNav['groups'] ?? []) as $tbGroup => $tbLinks) {
    if (isset($tbLinks[$active ?? ''])) { $tbTrail = count($tbLinks) > 1 ? [$tbGroup, $tbLinks[$active][2]] : [$tbLinks[$active][2]]; break; }
}
if (!$tbTrail && ($active ?? '') === 'my-account') $tbTrail = ['My account'];
?>
<header class="top-header">
    <button class="topbar-icon d-lg-none" type="button" id="toggleSidebar" aria-controls="sidebar" aria-expanded="false" aria-label="Open menu">
        <i class="fas fa-bars" aria-hidden="true"></i>
    </button>
    <nav class="topbar-trail" aria-label="You are here">
        <ol>
            <li><?= h($portalNav['scope'] ?? $tbRole) ?></li>
            <?php foreach ($tbTrail as $i => $crumb): ?><li<?= $i === count($tbTrail) - 1 ? ' aria-current="page"' : '' ?>><?= h($crumb) ?></li><?php endforeach; ?>
        </ol>
    </nav>
    <a class="topbar-icon" href="<?= BASE_URL ?>/" target="_blank" rel="noopener" aria-label="Open the public website in a new tab" data-tip="Public website"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
    <div class="dropdown account-menu">
        <button class="account-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu for <?= h($tbName) ?>">
            <?php if ($tbAvatar): ?><img src="<?= h($tbAvatar) ?>" alt="" width="36" height="36"><?php else: ?><span class="avatar-initials" aria-hidden="true"><?= h($tbInitials) ?></span><?php endif; ?>
            <span class="account-text"><span class="account-name"><?= h($tbName) ?></span><span class="account-role"><?= h($tbRole) ?></span></span>
            <i class="fas fa-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end">
            <div class="account-head">
                <strong><?= h($tbName) ?></strong>
                <span><?= h($_SESSION['email'] ?? '') ?></span>
            </div>
            <a class="dropdown-item<?= ($active ?? '') === 'my-account' ? ' active' : '' ?>" href="<?= BASE_URL ?>/pages/account/settings.php"><i class="fas fa-user-gear" aria-hidden="true"></i> My account</a>
            <div class="dropdown-divider"></div>
            <form method="post" action="<?= BASE_URL ?>/logout.php" id="logoutForm">
                <?= csrf_field() ?>
                <button type="submit" class="dropdown-item text-danger"><i class="fas fa-right-from-bracket" aria-hidden="true"></i> Sign out</button>
            </form>
        </div>
    </div>
</header>
<div class="page-head">
    <div class="page-head-title">
        <h1><?= h($tbTitle) ?></h1>
        <?php if (!empty($pageSubtitle)): ?><p><?= h($pageSubtitle) ?></p><?php endif; ?>
    </div>
    <?php if (!empty($headerActions)): ?><div class="page-head-actions"><?= $headerActions ?></div><?php endif; ?>
</div>
<?php foreach (flash_take() as [$flashType, $flashMsg]): ?>
<div class="flash-row"><div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'info' ? 'info' : 'success') ?> mb-0" role="<?= $flashType === 'error' ? 'alert' : 'status' ?>"><?= h($flashMsg) ?></div></div>
<?php endforeach; ?>
