<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']);
$active = 'platform-settings';

$errors = []; $saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name     = trim($_POST['platform_name'] ?? 'BodaERP');
    $operator = trim($_POST['operator_name'] ?? '');
    $email    = trim($_POST['support_email'] ?? '');
    $phone    = trim($_POST['support_phone'] ?? '');
    $sc = (int) ($_POST['default_split_city'] ?? 0);
    $sa = (int) ($_POST['default_split_assoc'] ?? 0);
    $sp = (int) ($_POST['default_split_platform'] ?? 0);
    $req2fa = isset($_POST['require_2fa']) ? 1 : 0;
    $maint  = isset($_POST['maintenance_mode']) ? 1 : 0;

    if ($sc + $sa + $sp !== 100) {
        $errors[] = 'Default revenue split must total 100%.';
    } else {
        runQuery("UPDATE platform_settings SET platform_name=?,operator_name=?,support_email=?,support_phone=?,
                  default_split_city=?,default_split_association=?,default_split_platform=?,require_2fa=?,maintenance_mode=? WHERE id=1",
            [$name,$operator,$email,$phone,$sc,$sa,$sp,$req2fa,$maint]);
        audit_log('UPDATE', 'platform_settings', '1', 'Platform settings updated');
        $saved = true;
    }
}

$settings = fetchOne("SELECT * FROM platform_settings WHERE id = 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Platform Settings - BodaERP Platform'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-superadmin.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">Platform Settings</span></h5>
            </div>
        </div>
    </header>

    <div class="content-area">
        <?php if ($saved): ?><div class="alert alert-success">Platform settings saved.</div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e); ?></div><?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="fas fa-building text-primary me-2"></i>General</h6>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-semibold">Platform Name</label><input type="text" class="form-control" name="platform_name" value="<?= h($settings['platform_name']) ?>"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Operator</label><input type="text" class="form-control" name="operator_name" value="<?= h($settings['operator_name']) ?>"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Support Email</label><input type="email" class="form-control" name="support_email" value="<?= h($settings['support_email']) ?>"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Support Phone</label><input type="text" class="form-control" name="support_phone" value="<?= h($settings['support_phone']) ?>"></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="fas fa-percentage text-primary me-2"></i>Default New-City Revenue Split</h6>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4"><label class="small text-muted">City Council %</label><input type="number" class="form-control" name="default_split_city" value="<?= (int)$settings['default_split_city'] ?>" min="0" max="100"></div>
                        <div class="col-md-4"><label class="small text-muted">Boda Association %</label><input type="number" class="form-control" name="default_split_assoc" value="<?= (int)$settings['default_split_association'] ?>" min="0" max="100"></div>
                        <div class="col-md-4"><label class="small text-muted">Kakebe Tech (Platform) %</label><input type="number" class="form-control" name="default_split_platform" value="<?= (int)$settings['default_split_platform'] ?>" min="0" max="100"></div>
                    </div>
                    <small class="text-muted d-block mt-2">Used to pre-fill the split when a new city is added.</small>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="fas fa-shield-alt text-primary me-2"></i>Security</h6>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="require_2fa" id="require2fa" <?= $settings['require_2fa'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="require2fa">Require 2FA for city admins</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenanceMode" <?= $settings['maintenance_mode'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="maintenanceMode">Maintenance mode (blocks non-admin logins)</label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Settings</button>
        </form>
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
