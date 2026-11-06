<?php require_once __DIR__ . '/../../backend/site.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? 'BodaERP') ?></title>
<meta name="theme-color" content="<?= h(brand_color('brand_color_dark', BRAND_DEFAULT_DARK)) ?>">
<?= brand_favicon_tags() ?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/outfit/outfit.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css?v=<?= @filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/component.css?v=<?= @filemtime(__DIR__ . '/../assets/css/component.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/portal-shell.css?v=<?= @filemtime(__DIR__ . '/../assets/css/portal-shell.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/controls.css?v=<?= @filemtime(__DIR__ . '/../assets/css/controls.css') ?: 1 ?>">
<style><?= brand_css_vars() ?></style>
<script nonce="<?= csp_nonce() ?>">try { if (localStorage.getItem('bodaerp-sidebar-folded') === '1') document.documentElement.classList.add('sidebar-folded'); } catch (e) { /* storage blocked: the menu opens with labels */ }</script>
<script src="<?= BASE_URL ?>/assets/vendor/apexcharts/apexcharts.min.js"></script>
