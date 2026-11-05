<?php
// Include once, after the page's form, on any admin page with <textarea data-rich-text>.
$ckV = @filemtime(__DIR__ . '/../assets/vendor/ckeditor5/ckeditor5.umd.js') ?: 1;
?>
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<meta name="bodaerp-base-url" content="<?= h(BASE_URL) ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/ckeditor5/ckeditor5.css?v=<?= $ckV ?>">
<style>.ck-editor__editable_inline { min-height: 320px; } .ck.ck-editor { width: 100%; }</style>
<script src="<?= BASE_URL ?>/assets/vendor/ckeditor5/ckeditor5.umd.js?v=<?= $ckV ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/editor.js?v=<?= @filemtime(__DIR__ . '/../assets/js/editor.js') ?: 1 ?>"></script>
