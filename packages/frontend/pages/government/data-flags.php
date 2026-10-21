<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['government']);
require_once __DIR__ . '/../../../backend/anomaly_engine.php';

$active = 'data-flags';
$flagPageUrl = BASE_URL . '/pages/government/data-flags.php';
$flagScopeCity = null;
$flagCanReview = false;
$flagScanResult = null;
$flagReviewMsg = null;
anomaly_scan_if_stale('auto');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Data Integrity Flags - BodaERP'; require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-government.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Data Integrity Flags'; $pageSubtitle = 'National view of duplicates, fraud signals and inconsistent records (read-only)'; require __DIR__ . '/../../partials/topheader.php'; ?>
    <div class="content-area">
        <?php require __DIR__ . '/../../partials/flags-panel.php'; ?>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script>
    document.getElementById('toggleSidebar')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    });
    document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
    });
</script>
</body>
</html>
