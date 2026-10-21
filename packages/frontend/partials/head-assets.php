<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? 'BodaERP') ?></title>
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/admin.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/component.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/component.css') ?>">
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.0"></script>

<!-- CKEditor 5 Rich Text Editor -->
<link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/44.3.1/classic.css">
<script src="https://cdn.ckeditor.com/ckeditor5/44.3.1/build/classic.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/44.3.1/build/easy-image.js"></script>
<script>
window.CKEDITOR_BASE_URL = 'https://cdn.ckeditor.com/ckeditor5/44.3.1/';
</script>
