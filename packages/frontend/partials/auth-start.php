<?php
// Opening half of the sign-in shell used by login, login-code, forgot-password, reset-password and verify-email:
// a centred card with the logo and no site header or footer. Set $seo first; close with auth-end.php.
$siteName = get_setting('site_name', 'BodaERP');
$brandA = str_ends_with($siteName, 'ERP') ? mb_substr($siteName, 0, -3) : $siteName;
$brandB = str_ends_with($siteName, 'ERP') ? 'ERP' : '';
$authCardClass = $authCardClass ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/public-head.php'; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/login.css?v=<?= @filemtime(__DIR__ . '/../assets/css/login.css') ?: 1 ?>">
</head>
<body class="auth-page">
<main id="main" class="login-page">
    <div class="login-container">
        <div class="login-card <?= h($authCardClass) ?>">
            <a href="<?= BASE_URL ?>/" class="logo-section" aria-label="<?= h($siteName) ?> home page">
                <img class="logo-icon" src="<?= h(brand_logo_url()) ?>" alt="" width="64" height="64">
                <span class="brand-name"><span class="b1"><?= h($brandA) ?></span><span class="b2"><?= h($brandB) ?></span></span>
            </a>
