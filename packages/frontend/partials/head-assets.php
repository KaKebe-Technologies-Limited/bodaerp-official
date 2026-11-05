<?php require_once __DIR__ . '/../../backend/site.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? 'BodaERP') ?></title>
<meta name="theme-color" content="<?= h(brand_color('brand_color_dark', BRAND_DEFAULT_DARK)) ?>">
<?= brand_favicon_tags() ?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha384-PPIZEGYM1v8zp5Py7UjFb79S58UeqCL9pYVnVPURKEqvioPROaVAJKKLzvH2rDnI" crossorigin="anonymous">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css?v=<?= @filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/component.css?v=<?= @filemtime(__DIR__ . '/../assets/css/component.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/portal-shell.css?v=<?= @filemtime(__DIR__ . '/../assets/css/portal-shell.css') ?>">
<style><?= brand_css_vars() ?></style>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js" integrity="sha384-5u6RO0Q8xx8HD9yfm1MUPRgt4kTTlZxjJmXIFZxa5pEZy7U9XOtkAZgbF1fSYmsw" crossorigin="anonymous"></script>
