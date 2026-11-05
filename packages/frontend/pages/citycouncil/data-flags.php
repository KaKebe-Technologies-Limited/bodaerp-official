<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['city_admin']);
require_once __DIR__ . '/../../../backend/anomaly_engine.php';

$active = 'data-flags';
$flagPageUrl = BASE_URL . '/pages/citycouncil/data-flags.php';
$flagScopeCity = $_SESSION['city_id'];
$flagCanReview = false;
$flagScanResult = null;
$flagReviewMsg = null;
anomaly_scan_if_stale('auto');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Data Integrity Flags | BodaERP'; require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Data Integrity Flags'; $pageSubtitle = 'Records in your city that need attention (read-only)'; require __DIR__ . '/../../partials/topheader.php'; ?>
    <div class="content-area">
        <?php require __DIR__ . '/../../partials/flags-panel.php'; ?>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
</body>
</html>
