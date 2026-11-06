<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['city_admin']);
require_once __DIR__ . '/../../../backend/anomaly_engine.php';

$active = 'data-flags';
$flagPageUrl = BASE_URL . '/pages/citycouncil/data-flags.php';
$flagScopeCity = $_SESSION['city_id'];
$flagCanReview = true;    // review flags of this city only; scans stay with the Super Admin
$flagCanScan = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    csrf_verify();
    $id = (int) ($_POST['flag_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    if ((string) fetchValue("SELECT city_id FROM anomaly_flags WHERE id = ?", [$id]) === (string) $flagScopeCity
        && anomaly_review($id, $decision, (string) ($_POST['note'] ?? ''), (int) $_SESSION['user_id'])) {
        audit_log('UPDATE', 'anomaly_flag', (string) $id, 'Flag ' . $decision);
        $_SESSION['flash_review'] = 'Flag #' . $id . ' updated (' . $decision . ').';
    }
    redirect('/pages/citycouncil/data-flags.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}
$flagScanResult = null;
$flagReviewMsg = $_SESSION['flash_review'] ?? null; unset($_SESSION['flash_review']);
anomaly_scan_if_stale('auto');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Data integrity flags | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Data integrity flags'; $pageSubtitle = 'Records in your city that need attention: confirm a real problem or dismiss a false alarm'; require __DIR__ . '/../../partials/topheader.php'; ?>
    <div class="content-area">
        <?php require __DIR__ . '/../../partials/flags-panel.php'; ?>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
</body>
</html>
