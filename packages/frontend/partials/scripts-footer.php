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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" integrity="sha384-JcnsjUPPylna1s1fvi1u12X5qjY5OL56iySh75FdtrwhO/SWXgMjoVqcKyIIWOLk" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js" integrity="sha384-fCAW/rDWORTbQXSiB7mOg0QtQ5c+r0f544y6XoKjuVva0nMBlCpNUjiFeG5iMdS3" crossorigin="anonymous"></script>
<script src="<?= BASE_URL ?>/assets/js/table-tools.js?v=<?= @filemtime(__DIR__ . '/../assets/js/table-tools.js') ?: 1 ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= @filemtime(__DIR__ . '/../assets/js/main.js') ?: 1 ?>"></script>
