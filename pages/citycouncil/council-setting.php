<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['city_admin']);
$active = 'council-setting';
$cityId = $_SESSION['city_id'];

$errors = []; $saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $fee = (float) ($_POST['annual_fee'] ?? 0);
    $currency = $_POST['currency'] ?? 'UGX';
    $fiscal = trim($_POST['fiscal_year'] ?? '2026/2027');
    $sc = (int) ($_POST['split_city'] ?? 0);
    $sa = (int) ($_POST['split_assoc'] ?? 0);
    $sp = (int) ($_POST['split_platform'] ?? 0);
    $gateway = $_POST['payment_gateway'] ?? 'MTN MoMo';
    $target = (int) ($_POST['compliance_target'] ?? 70);
    $grace = (int) ($_POST['grace_period_days'] ?? 30);
    $reminder = (int) ($_POST['reminder_days'] ?? 14);

    if ($sc + $sa + $sp !== 100) {
        $errors[] = 'Revenue split must total 100%.';
    } else {
        runQuery("UPDATE cities SET annual_fee=?,currency=?,fiscal_year=?,revenue_split_city=?,revenue_split_association=?,revenue_split_platform=?,
                  payment_gateway=?,compliance_target=?,grace_period_days=?,reminder_days=? WHERE id=?",
            [$fee,$currency,$fiscal,$sc,$sa,$sp,$gateway,$target,$grace,$reminder,$cityId]);
        audit_log('UPDATE', 'city', $cityId, 'Council settings updated');
        $saved = true;
    }
}

$city = fetchOne("SELECT * FROM cities WHERE id = ?", [$cityId]);
$totalRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND deleted_at IS NULL", [$cityId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Council Settings - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">Council Settings</span></h5>
            </div>
        </div>
    </header>

    <div class="content-area">
        <?php if ($saved): ?><div class="alert alert-success">Settings updated successfully!</div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e); ?></div><?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card"><div class="card-body">
                        <h5 class="fw-bold mb-3"><i class="fas fa-sliders-h text-primary me-2"></i>General Settings</h5>
                        <div class="mb-3"><label class="form-label fw-semibold">City Name</label><input type="text" class="form-control" value="<?= h($city['name']) ?>" disabled></div>
                        <div class="mb-3"><label class="form-label fw-semibold">Annual Tax Amount (UGX)</label><input type="number" class="form-control" name="annual_fee" value="<?= (float)$city['annual_fee'] ?>"></div>
                        <div class="mb-3"><label class="form-label fw-semibold">Currency</label>
                            <select class="form-select" name="currency">
                                <option value="UGX" <?= $city['currency']==='UGX'?'selected':'' ?>>UGX - Ugandan Shilling</option>
                                <option value="KES" <?= $city['currency']==='KES'?'selected':'' ?>>KES - Kenyan Shilling</option>
                                <option value="TZS" <?= $city['currency']==='TZS'?'selected':'' ?>>TZS - Tanzanian Shilling</option>
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label fw-semibold">Fiscal Year</label>
                            <select class="form-select" name="fiscal_year">
                                <?php foreach (['2025/2026','2026/2027','2027/2028'] as $fy): ?>
                                    <option value="<?= $fy ?>" <?= $city['fiscal_year']===$fy?'selected':'' ?>><?= $fy ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div></div>
                </div>

                <div class="col-lg-6">
                    <div class="card"><div class="card-body">
                        <h5 class="fw-bold mb-3"><i class="fas fa-credit-card text-danger me-2"></i>Revenue Split</h5>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Revenue Distribution</label>
                            <div class="row g-2">
                                <div class="col-4"><label class="small text-muted">City Council</label><div class="input-group"><input type="number" class="form-control" name="split_city" value="<?= (int)$city['revenue_split_city'] ?>"><span class="input-group-text">%</span></div></div>
                                <div class="col-4"><label class="small text-muted">Boda Assoc.</label><div class="input-group"><input type="number" class="form-control" name="split_assoc" value="<?= (int)$city['revenue_split_association'] ?>"><span class="input-group-text">%</span></div></div>
                                <div class="col-4"><label class="small text-muted">Kakebe Tech</label><div class="input-group"><input type="number" class="form-control" name="split_platform" value="<?= (int)$city['revenue_split_platform'] ?>"><span class="input-group-text">%</span></div></div>
                            </div>
                            <small class="text-muted">Total must equal 100%</small>
                        </div>
                        <div class="mb-3"><label class="form-label fw-semibold">Payment Gateway</label>
                            <select class="form-select" name="payment_gateway">
                                <option value="MTN MoMo" <?= $city['payment_gateway']==='MTN MoMo'?'selected':'' ?>>MTN MoMo API</option>
                                <option value="Airtel Money" <?= $city['payment_gateway']==='Airtel Money'?'selected':'' ?>>Airtel Money API</option>
                                <option value="Both" <?= $city['payment_gateway']==='Both'?'selected':'' ?>>Both</option>
                            </select>
                        </div>
                    </div></div>
                </div>

                <div class="col-lg-6">
                    <div class="card"><div class="card-body">
                        <h5 class="fw-bold mb-3"><i class="fas fa-check-circle text-success me-2"></i>Compliance Settings</h5>
                        <div class="mb-3"><label class="form-label fw-semibold">Compliance Threshold</label><div class="input-group"><input type="number" class="form-control" name="compliance_target" value="<?= (int)$city['compliance_target'] ?>"><span class="input-group-text">%</span></div><small class="text-muted">Stages below this threshold will be flagged</small></div>
                        <div class="mb-3"><label class="form-label fw-semibold">Grace Period (Days)</label><input type="number" class="form-control" name="grace_period_days" value="<?= (int)$city['grace_period_days'] ?>"></div>
                        <div class="mb-3"><label class="form-label fw-semibold">Auto-Reminder Days</label><div class="input-group"><input type="number" class="form-control" name="reminder_days" value="<?= (int)$city['reminder_days'] ?>"><span class="input-group-text">days before expiry</span></div></div>
                    </div></div>
                </div>

                <div class="col-lg-6">
                    <div class="card"><div class="card-body">
                        <h5 class="fw-bold mb-3"><i class="fas fa-server text-primary me-2"></i>System Status</h5>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span>System Version</span><span class="fw-bold">v2.0.0 (PHP/MySQL)</span></div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span>Database Status</span><span class="badge bg-success">Connected</span></div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span>Total Riders</span><span class="fw-bold"><?= number_format($totalRiders) ?></span></div>
                        <div class="d-flex justify-content-between align-items-center py-2"><span>City Status</span><span class="badge bg-<?= $city['status']==='active'?'success':'warning' ?>"><?= strtoupper($city['status']) ?></span></div>
                    </div></div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save me-1"></i>Save All Settings</button>
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
