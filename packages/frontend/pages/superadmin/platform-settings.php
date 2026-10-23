<?php
require_once "/var/www/html/packages/backend/auth.php";
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
    $sg = (int) ($_POST['default_split_government'] ?? 0);
    $sp = (int) ($_POST['default_split_platform'] ?? 0);
    $req2fa = isset($_POST['require_2fa']) ? 1 : 0;
    $maint  = isset($_POST['maintenance_mode']) ? 1 : 0;

    if ($sc + $sa + $sg + $sp !== 100) {
        $errors[] = 'Default revenue split must total 100%.';
    } else {
        runQuery("UPDATE platform_settings SET platform_name=?,operator_name=?,support_email=?,support_phone=?,
                  default_split_city=?,default_split_association=?,default_split_government=?,default_split_platform=?,require_2fa=?,maintenance_mode=? WHERE id=1",
            [$name,$operator,$email,$phone,$sc,$sa,$sg,$sp,$req2fa,$maint]);
        audit_log('UPDATE', 'platform_settings', '1', 'Platform settings updated');
        $saved = true;
    }
}

$settings = fetchOne("SELECT * FROM platform_settings WHERE id = 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Platform Settings - BodaERP Platform'; require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-superadmin.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <div>
                    <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">Platform Settings</span></h5>
                    <small class="text-muted d-none d-sm-block">Global configuration for the BodaERP platform</small>
                </div>
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
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-badge blue"><i class="fas fa-building"></i></div>
                        <div><h6 class="fw-bold mb-0">General</h6><small class="text-muted">Platform identity and contact details</small></div>
                    </div>
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
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-badge gold"><i class="fas fa-percentage"></i></div>
                        <div><h6 class="fw-bold mb-0">Default New-City Revenue Split</h6><small class="text-muted">Pre-fills the split when a new city is added</small></div>
                    </div>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3"><label class="small text-muted">City Council %</label><input type="number" class="form-control" name="default_split_city" id="defSplitCity" value="<?= (int)$settings['default_split_city'] ?>" min="0" max="100" oninput="updateDefaultSplitTotal()"></div>
                        <div class="col-md-3"><label class="small text-muted">Boda Association %</label><input type="number" class="form-control" name="default_split_assoc" id="defSplitAssoc" value="<?= (int)$settings['default_split_association'] ?>" min="0" max="100" oninput="updateDefaultSplitTotal()"></div>
                        <div class="col-md-3"><label class="small text-muted">Government %</label><input type="number" class="form-control" name="default_split_government" id="defSplitGov" value="<?= (int)$settings['default_split_government'] ?>" min="0" max="100" oninput="updateDefaultSplitTotal()"></div>
                        <div class="col-md-3"><label class="small text-muted">Kakebe Tech (Platform) %</label><input type="number" class="form-control" name="default_split_platform" id="defSplitPlatform" value="<?= (int)$settings['default_split_platform'] ?>" min="0" max="100" oninput="updateDefaultSplitTotal()"></div>
                    </div>
                    <div class="mt-2">Total: <span id="defSplitTotal" class="fw-bold" style="color:#198754;">100%</span></div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-badge red"><i class="fas fa-shield-alt"></i></div>
                        <div><h6 class="fw-bold mb-0">Security</h6><small class="text-muted">Authentication and access controls</small></div>
                    </div>
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

        <div class="card mt-4"><div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-badge green"><i class="fas fa-server"></i></div>
                <div><h6 class="fw-bold mb-0">System &amp; Updates</h6><small class="text-muted">Database updates, data packs (add a city), automatic jobs and backups.</small></div>
            </div>
            <a href="<?= BASE_URL ?>/pages/superadmin/system.php" class="btn btn-outline-primary btn-sm">Open System &amp; Updates</a>
        </div></div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script>
    function updateDefaultSplitTotal() {
        const total = (parseFloat(document.getElementById('defSplitCity').value)||0)
            + (parseFloat(document.getElementById('defSplitAssoc').value)||0)
            + (parseFloat(document.getElementById('defSplitGov').value)||0)
            + (parseFloat(document.getElementById('defSplitPlatform').value)||0);
        const el = document.getElementById('defSplitTotal');
        el.textContent = total + '%';
        el.style.color = total === 100 ? '#198754' : '#dc3545';
    }

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
