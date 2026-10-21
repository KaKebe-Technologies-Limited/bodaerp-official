<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['super_admin']);
require_once __DIR__ . '/../../../backend/anomaly_engine.php';

$active = 'data-quality';
$flagPageUrl = BASE_URL . '/pages/superadmin/data-quality.php';
$flagScopeCity = null;
$flagCanReview = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    if ($act === 'scan') {
        $_SESSION['flash_scan'] = run_anomaly_scan('manual:' . (int) $_SESSION['user_id']);
        audit_log('CREATE', 'anomaly_scan', null, 'Manual data-integrity scan');
    } elseif ($act === 'review') {
        $id = (int) ($_POST['flag_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        if (anomaly_review($id, $decision, $_POST['note'] ?? '', (int) $_SESSION['user_id'])) {
            audit_log('UPDATE', 'anomaly_flag', (string) $id, 'Flag ' . $decision);
            $_SESSION['flash_review'] = 'Flag #' . $id . ' updated (' . $decision . ').';
        }
    }
    redirect('/pages/superadmin/data-quality.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$flagScanResult = $_SESSION['flash_scan'] ?? null;   unset($_SESSION['flash_scan']);
$flagReviewMsg  = $_SESSION['flash_review'] ?? null; unset($_SESSION['flash_review']);
if (!$flagScanResult) anomaly_scan_if_stale('auto');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Data Quality & Flags - BodaERP Platform'; require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-superadmin.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Data Quality & Flags'; $pageSubtitle = 'Detect duplicates, fraud signals and inconsistent records'; require __DIR__ . '/../../partials/topheader.php'; ?>
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
