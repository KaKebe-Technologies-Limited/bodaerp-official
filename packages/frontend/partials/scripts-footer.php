<?php
// End of every portal page: the shared scripts (loaded once, here only) and any flash messages not
// already shown by topheader.php, as toasts.
$toasts = flash_take();
?>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090">
<?php foreach ($toasts as [$toastType, $toastMsg]): ?>
    <div class="toast align-items-center text-bg-<?= $toastType === 'error' ? 'danger' : ($toastType === 'info' ? 'primary' : 'success') ?> border-0" role="<?= $toastType === 'error' ? 'alert' : 'status' ?>" aria-live="<?= $toastType === 'error' ? 'assertive' : 'polite' ?>" aria-atomic="true" data-bs-delay="<?= $toastType === 'error' ? 10000 : 6000 ?>" data-boda-toast>
        <div class="d-flex"><div class="toast-body"><?= h($toastMsg) ?></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>
    </div>
<?php endforeach; ?>
</div>
<script src="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/vendor/jspdf/jspdf.umd.min.js"></script>
<script src="<?= BASE_URL ?>/assets/vendor/jspdf/jspdf.plugin.autotable.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/table-tools.js?v=<?= @filemtime(__DIR__ . '/../assets/js/table-tools.js') ?: 1 ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= @filemtime(__DIR__ . '/../assets/js/main.js') ?: 1 ?>"></script>
