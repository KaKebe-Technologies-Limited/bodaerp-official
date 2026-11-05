<?php
// Portal top bar: page title, optional page actions ($headerActions, trusted HTML built by the page) and the
// account menu. It stays pinned to the top while the page scrolls. Flash messages appear under it.
$tbName = (string) ($_SESSION['name'] ?? '');
$tbRole = match ($_SESSION['role'] ?? '') {
    'city_admin' => $_SESSION['city_name'] ?? 'City council',
    'government' => $_SESSION['agency_name'] ?? 'Government',
    default => role_label((string) ($_SESSION['role'] ?? '')),
};
$tbInitials = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($tbName)) ?: [], 0, 2))));
$tbAvatar = !empty($_SESSION['avatar_path']) ? media_url($_SESSION['avatar_path']) : null;
$tbTitle = preg_replace('/\s+-\s+' . preg_quote(get_setting('site_name', 'BodaERP'), '/') . '$/', '', (string) ($pageTitle ?? ''));
?>
<header class="top-header">
    <button class="topbar-icon d-lg-none" type="button" id="toggleSidebar" aria-controls="sidebar" aria-expanded="false" aria-label="Open menu">
        <i class="fas fa-bars" aria-hidden="true"></i>
    </button>
    <div class="topbar-title">
        <h1><?= h($tbTitle) ?></h1>
        <?php if (!empty($pageSubtitle)): ?><p><?= h($pageSubtitle) ?></p><?php endif; ?>
    </div>
    <?php if (!empty($headerActions)): ?><div class="topbar-actions"><?= $headerActions ?></div><?php endif; ?>
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
            <a class="dropdown-item" href="<?= BASE_URL ?>/" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> Public website</a>
            <div class="dropdown-divider"></div>
            <form method="post" action="<?= BASE_URL ?>/logout.php" id="logoutForm">
                <?= csrf_field() ?>
                <button type="submit" class="dropdown-item text-danger"><i class="fas fa-right-from-bracket" aria-hidden="true"></i> Sign out</button>
            </form>
        </div>
    </div>
</header>
<?php foreach (flash_take() as [$flashType, $flashMsg]): ?>
<div class="flash-row"><div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'info' ? 'info' : 'success') ?> mb-0" role="<?= $flashType === 'error' ? 'alert' : 'status' ?>"><?= h($flashMsg) ?></div></div>
<?php endforeach; ?>
