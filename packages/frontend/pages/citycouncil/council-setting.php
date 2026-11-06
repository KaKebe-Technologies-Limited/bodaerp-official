<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['city_admin']);
$active = 'council-setting';
$cityId = $_SESSION['city_id'];

$errors = []; $saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $fee = (float) ($_POST['annual_fee'] ?? 0);
    $currency = $_POST['currency'] ?? 'UGX';
    $fiscal = CURRENT_FISCAL_YEAR;
    $sc = (int) ($_POST['split_city'] ?? 0);
    $sa = (int) ($_POST['split_assoc'] ?? 0);
    $sg = (int) ($_POST['split_government'] ?? 0);
    $sp = (int) ($_POST['split_platform'] ?? 0);
    $gateway = in_array($_POST['payment_gateway'] ?? '', ['MTN MoMo', 'Airtel Money', 'Both'], true) ? $_POST['payment_gateway'] : 'Both';
    $target = (int) ($_POST['compliance_target'] ?? 70);
    $grace = (int) ($_POST['grace_period_days'] ?? 30);
    $reminder = (int) ($_POST['reminder_days'] ?? 14);
    $lateType = ($_POST['late_fee_type'] ?? 'flat') === 'percent' ? 'percent' : 'flat';
    $lateFee = max(0, round((float) ($_POST['late_fee'] ?? 0), 2));

    if ($sc + $sa + $sg + $sp !== 100) {
        $errors[] = 'Revenue split must total 100%.';
    } elseif ($grace < 0 || $grace > 365) {
        $errors[] = 'The grace period must be between 0 and 365 days.';
    } elseif ($lateType === 'percent' && $lateFee > 100) {
        $errors[] = 'A late fee in percent cannot be more than 100% of the permit fee.';
    } elseif ($lateType === 'flat' && $lateFee > $fee && $fee > 0) {
        $errors[] = 'A flat late fee cannot be more than the permit fee itself.';
    } else {
        runQuery("UPDATE cities SET annual_fee=?,currency=?,fiscal_year=?,revenue_split_city=?,revenue_split_association=?,revenue_split_government=?,revenue_split_platform=?,
                  payment_gateway=?,compliance_target=?,grace_period_days=?,reminder_days=?,late_fee=?,late_fee_type=? WHERE id=?",
            [$fee,$currency,$fiscal,$sc,$sa,$sg,$sp,$gateway,$target,$grace,$reminder,$lateFee,$lateType,$cityId]);
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
    <?php $pageTitle = 'Council settings | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <?php
    $pageTitle = 'Council settings';
    $pageSubtitle = '';
    require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <?php if ($saved): ?><div class="alert alert-success">Settings updated successfully!</div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e); ?></div><?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card"><div class="card-body">
                        <h5 class="fw-bold mb-3">General settings</h5>
                        <div class="mb-3"><label for="f-show-city-name" class="form-label fw-semibold">City name</label><input id="f-show-city-name" type="text" class="form-control" value="<?= h($city['name']) ?>" disabled></div>
                        <div class="mb-3"><label for="f-annual-fee" class="form-label fw-semibold">Annual tax amount (UGX)</label><input id="f-annual-fee" type="number" class="form-control" name="annual_fee" value="<?= (float)$city['annual_fee'] ?>"></div>
                        <div class="mb-3"><label for="f-currency" class="form-label fw-semibold">Currency</label>
                            <select id="f-currency" class="form-select" name="currency">
                                <option value="UGX" <?= $city['currency']==='UGX'?'selected':'' ?>>UGX - Ugandan shilling</option>
                                <option value="KES" <?= $city['currency']==='KES'?'selected':'' ?>>KES - Kenyan shilling</option>
                                <option value="TZS" <?= $city['currency']==='TZS'?'selected':'' ?>>TZS - Tanzanian shilling</option>
                            </select>
                        </div>
                        <div class="mb-3"><span class="form-label fw-semibold d-block">Permit year</span>
                            <p class="mb-0"><?= h(CURRENT_FISCAL_YEAR) ?> <span class="text-muted small">(July to June, Uganda's fiscal year; it moves on by itself on 1 July)</span></p>
                        </div>
                    </div></div>
                </div>

                <div class="col-lg-6">
                    <div class="card"><div class="card-body">
                        <h5 class="fw-bold mb-3">Revenue split</h5>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Revenue distribution</label>
                            <div class="row g-2">
                                <div class="col-3"><label for="csSplitCity" class="small text-muted">City council</label><div class="input-group"><input type="number" class="form-control" name="split_city" id="csSplitCity" value="<?= (int)$city['revenue_split_city'] ?>" data-call-input="updateCsSplitTotal"><span class="input-group-text">%</span></div></div>
                                <div class="col-3"><label for="csSplitAssoc" class="small text-muted">Boda assoc.</label><div class="input-group"><input type="number" class="form-control" name="split_assoc" id="csSplitAssoc" value="<?= (int)$city['revenue_split_association'] ?>" data-call-input="updateCsSplitTotal"><span class="input-group-text">%</span></div></div>
                                <div class="col-3"><label for="csSplitGov" class="small text-muted">Government</label><div class="input-group"><input type="number" class="form-control" name="split_government" id="csSplitGov" value="<?= (int)$city['revenue_split_government'] ?>" data-call-input="updateCsSplitTotal"><span class="input-group-text">%</span></div></div>
                                <div class="col-3"><label for="csSplitPlatform" class="small text-muted">Platform</label><div class="input-group"><input type="number" class="form-control" name="split_platform" id="csSplitPlatform" value="<?= (int)$city['revenue_split_platform'] ?>" data-call-input="updateCsSplitTotal"><span class="input-group-text">%</span></div></div>
                            </div>
                            <small class="text-muted">Total: <span id="csSplitTotal" class="fw-bold" style="color:#198754;">100%</span>: must equal 100%</small>
                        </div>
                        <div class="mb-3"><label for="f-payment-gateway" class="form-label fw-semibold">Mobile money riders may pay with</label>
                            <select id="f-payment-gateway" class="form-select" name="payment_gateway">
                                <option value="MTN MoMo" <?= $city['payment_gateway']==='MTN MoMo'?'selected':'' ?>>MTN Mobile Money only</option>
                                <option value="Airtel Money" <?= $city['payment_gateway']==='Airtel Money'?'selected':'' ?>>Airtel Money only</option>
                                <option value="Both" <?= $city['payment_gateway']==='Both'?'selected':'' ?>>MTN and Airtel</option>
                            </select>
                            <small class="text-muted">A rider paying from a number on another network is told which network to use.</small>
                        </div>
                    </div></div>
                </div>

                <div class="col-lg-6">
                    <div class="card"><div class="card-body">
                        <h5 class="fw-bold mb-3">Compliance settings</h5>
                        <div class="mb-3"><label for="f-compliance-target" class="form-label fw-semibold">Compliance threshold</label><div class="input-group"><input id="f-compliance-target" type="number" class="form-control" name="compliance_target" value="<?= (int)$city['compliance_target'] ?>"><span class="input-group-text">%</span></div><small class="text-muted">Stages below this threshold will be flagged</small></div>
                        <div class="mb-3"><label for="f-grace-period-days" class="form-label fw-semibold">Grace period after expiry</label><div class="input-group"><input id="f-grace-period-days" type="number" min="0" max="365" class="form-control" name="grace_period_days" value="<?= (int)$city['grace_period_days'] ?>"><span class="input-group-text">days</span></div><small class="text-muted">No late fee is charged during these days.</small></div>
                        <div class="mb-3"><label for="f-late-fee" class="form-label fw-semibold">Late fee after the grace period</label>
                            <div class="input-group"><input id="f-late-fee" type="number" min="0" step="any" class="form-control" name="late_fee" value="<?= h((string) (float) $city['late_fee']) ?>">
                                <label class="visually-hidden" for="f-late-fee-type">Late fee type</label>
                                <select id="f-late-fee-type" class="form-select flex-grow-0 w-auto" name="late_fee_type"><option value="flat"<?= $city['late_fee_type'] === 'flat' ? ' selected' : '' ?>>UGX</option><option value="percent"<?= $city['late_fee_type'] === 'percent' ? ' selected' : '' ?>>% of fee</option></select></div>
                            <small class="text-muted">0 means no late fee. Added once to the renewal, the same for cash, mobile money and USSD.</small></div>
                        <div class="mb-3"><label for="f-reminder-days" class="form-label fw-semibold">Auto-reminder days</label><div class="input-group"><input id="f-reminder-days" type="number" class="form-control" name="reminder_days" value="<?= (int)$city['reminder_days'] ?>"><span class="input-group-text">days before expiry</span></div></div>
                    </div></div>
                </div>

                <div class="col-lg-6">
                    <div class="card"><div class="card-body">
                        <h5 class="fw-bold mb-3">Council summary</h5>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span>Total riders</span><span class="fw-bold"><?= number_format($totalRiders) ?></span></div>
                        <div class="d-flex justify-content-between align-items-center py-2"><span>City status</span><span class="badge bg-<?= $city['status']==='active'?'success':'warning' ?>"><?= h(ucfirst(str_replace('_', ' ', $city['status']))) ?></span></div>
                    </div></div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save me-1"></i>Save all settings</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script nonce="<?= csp_nonce() ?>">
    function updateCsSplitTotal() {
        const total = (parseFloat(document.getElementById('csSplitCity').value)||0)
            + (parseFloat(document.getElementById('csSplitAssoc').value)||0)
            + (parseFloat(document.getElementById('csSplitGov').value)||0)
            + (parseFloat(document.getElementById('csSplitPlatform').value)||0);
        const el = document.getElementById('csSplitTotal');
        el.textContent = total + '%';
        el.style.color = total === 100 ? '#198754' : '#dc3545';
    }

</script>
</body>
</html>
