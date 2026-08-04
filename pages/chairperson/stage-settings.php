<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['chairperson']);
$active = 'stage-settings';
$stageId = $_SESSION['stage_id'];
$city = fetchOne("SELECT * FROM cities WHERE id = ?", [$_SESSION['city_id']]);

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $route = trim($_POST['route'] ?? '');
    $chairName = trim($_POST['chairperson_name'] ?? '');
    $chairPhone = '+256' . preg_replace('/\D/', '', $_POST['chairperson_phone'] ?? '');
    if ($name !== '') {
        runQuery("UPDATE stages SET name=?,location=?,route=?,chairperson_name=?,chairperson_phone=? WHERE id=?",
            [$name,$location,$route,$chairName,$chairPhone,$stageId]);
        audit_log('UPDATE', 'stage', (string) $stageId, 'Stage settings updated');
        $saved = true;
    }
}

$stage = fetchOne("SELECT * FROM stages WHERE id = ?", [$stageId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Stage Settings - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Stage Settings'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area">
        <?php if ($saved): ?><div class="alert alert-success">Stage settings updated!</div><?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card"><div class="card-body">
                    <h5 class="fw-bold mb-3">Stage Information</h5>
                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="mb-3"><label class="form-label fw-semibold">Stage Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="<?= h($stage['name']) ?>" required></div>
                        <div class="mb-3"><label class="form-label fw-semibold">Location</label><input type="text" name="location" class="form-control" value="<?= h($stage['location']) ?>"></div>
                        <div class="mb-3"><label class="form-label fw-semibold">Route</label><input type="text" name="route" class="form-control" value="<?= h($stage['route']) ?>"></div>
                        <div class="mb-3"><label class="form-label fw-semibold">Chairperson Name</label><input type="text" name="chairperson_name" class="form-control" value="<?= h($stage['chairperson_name']) ?>"></div>
                        <div class="mb-3"><label class="form-label fw-semibold">Chairperson Phone</label>
                            <div class="input-group"><span class="input-group-text">+256</span><input type="tel" name="chairperson_phone" class="form-control" value="<?= h(preg_replace('/^\+256/', '', $stage['chairperson_phone'] ?? '')) ?>"></div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                    </form>
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="card"><div class="card-body">
                    <h5 class="fw-bold mb-3">Notifications & Tax (set by City Council)</h5>
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" checked disabled><label class="form-check-label">Notify on new rider registration</label></div>
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" checked disabled><label class="form-check-label">Notify on payment received</label></div>
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" checked disabled><label class="form-check-label">Notify on expiry approaching</label></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Default Tax Amount</label><div class="input-group"><span class="input-group-text">UGX</span><input type="text" class="form-control" value="<?= number_format($city['annual_fee']) ?>" disabled></div></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Payment Reminder Days</label><input type="text" class="form-control" value="<?= (int)$city['reminder_days'] ?> days before expiry" disabled></div>
                    <small class="text-muted">These values are managed city-wide in Council Settings.</small>
                </div></div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
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
