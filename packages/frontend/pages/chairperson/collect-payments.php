<?php
require_once __DIR__ . '/../../../backend/permits.php';
require_role(['chairperson']);
$active = 'collect-payments';
$stageId = $_SESSION['stage_id'];
$cityId = $_SESSION['city_id'];
$city = fetchOne("SELECT * FROM cities WHERE id = ?", [$cityId]);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $riderId = (int) ($_POST['rider_id'] ?? 0);
    $method = (string) ($_POST['method'] ?? 'Cash');
    $rider = fetchOne("SELECT * FROM riders WHERE id = ? AND stage_id = ? AND deleted_at IS NULL", [$riderId, $stageId]);

    if (!$rider) $errors[] = 'Choose a rider from your stage.';
    elseif ((float) $rider['annual_tax'] <= 0) $errors[] = 'This rider has no permit fee set. Ask the city council to check the city fee.';
    elseif ($reason = permit_payment_block_reason($rider)) $errors[] = $reason;
    if (!in_array($method, PAYMENT_METHODS, true)) $errors[] = 'Choose how the rider paid.';

    if (!$errors) {
        $receipt = record_manual_payment($rider, $method, (int) $_SESSION['user_id']);
        redirect('/pages/chairperson/collect-payments.php?receipt=' . urlencode($receipt));
    }
}

$stageRiders = fetchAll("SELECT id, full_name, bike_plate FROM riders WHERE stage_id = ? AND deleted_at IS NULL ORDER BY full_name", [$stageId]);
$revenue = (float) fetchValue("SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.status='Confirmed' AND p.rider_id IN (SELECT id FROM riders WHERE stage_id=?)", [$stageId]);
$activeRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status='active' AND deleted_at IS NULL", [$stageId]);
$defaulters = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status='expired' AND deleted_at IS NULL", [$stageId]);
$thisMonth = (float) fetchValue("SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.status='Confirmed' AND YEAR(p.paid_at)=YEAR(CURDATE()) AND MONTH(p.paid_at)=MONTH(CURDATE()) AND p.rider_id IN (SELECT id FROM riders WHERE stage_id=?)", [$stageId]);

$recentPayments = fetchAll("SELECT p.*, r.full_name FROM payments p JOIN riders r ON r.id = p.rider_id
    WHERE r.stage_id = ? ORDER BY p.paid_at DESC LIMIT 15", [$stageId]);

$receiptMsg = $_GET['receipt'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Collect Payments - ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Collect Payments'; $pageSubtitle = 'Record and track rider tax payments for your stage'; require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <?php if ($receiptMsg): ?><div class="alert alert-success">Payment recorded! Receipt: <strong><?= h($receiptMsg) ?></strong></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e) . '<br>'; ?></div><?php endif; ?>

        <div class="row g-3 row-cols-2 row-cols-xl-4 mb-4">
            <div class="col"><div class="stat-hero-card tone-blue"><div class="stat-hero-icon"><i class="fas fa-money-bill-wave"></i></div><div><div class="stat-hero-value"><?= h(formatCurrency($revenue)) ?></div><div class="stat-hero-label">Total Collected</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-green"><div class="stat-hero-icon"><i class="fas fa-user-check"></i></div><div><div class="stat-hero-value"><?= $activeRiders ?></div><div class="stat-hero-label">Active Riders</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-red"><div class="stat-hero-icon"><i class="fas fa-exclamation-triangle"></i></div><div><div class="stat-hero-value"><?= $defaulters ?></div><div class="stat-hero-label">Defaulters</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-gold"><div class="stat-hero-icon"><i class="fas fa-calendar-alt"></i></div><div><div class="stat-hero-value"><?= h(formatCurrency($thisMonth)) ?></div><div class="stat-hero-label">This Month</div></div></div></div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card"><div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-badge blue"><i class="fas fa-money-bill-wave"></i></div>
                        <h6 class="fw-bold mb-0">Record Payment</h6>
                    </div>
                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="mb-3"><label class="form-label fw-semibold">Select Rider <span class="text-danger">*</span></label>
                            <select class="form-select" name="rider_id" required>
                                <option value="">Choose rider...</option>
                                <?php foreach ($stageRiders as $r): ?><option value="<?= (int)$r['id'] ?>"><?= h($r['full_name']) ?> (<?= h($r['bike_plate']) ?>)</option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text">UGX</span><input type="text" class="form-control" value="<?= number_format((float) $city['annual_fee']) ?>" readonly aria-describedby="feeHint"></div>
                            <small class="text-muted" id="feeHint">The annual permit fee set by <?= h($city['name']) ?>. The rider's permit is renewed for one year from its current expiry date.</small>
                        </div>
                        <div class="mb-3"><label class="form-label fw-semibold">Payment Method</label>
                            <select class="form-select" name="method"><?php foreach (PAYMENT_METHODS as $m): ?><option value="<?= h($m) ?>"><?= h($m === 'Mobile Money' ? 'Mobile money (paid to the stage number)' : ($m === 'Bank' ? 'Bank deposit' : $m)) ?></option><?php endforeach; ?></select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-check me-1"></i>Record Payment</button>
                    </form>
                </div></div>
            </div>
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-2 no-print">
                    <h6 class="fw-bold mb-0">Recent Payments</h6>
                    <?php $exportTableId='recentPaymentsTable'; $exportFilename='stage_payments'; $exportTitle='Stage Payment History'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
                </div>
                <div class="card"><div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="recentPaymentsTable">
                            <thead><tr><th>Date</th><th>Rider</th><th>Amount</th><th>Method</th><th>Receipt</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($recentPayments as $p): $sc = ['Confirmed'=>'bg-success','Pending'=>'bg-warning text-dark','Failed'=>'bg-danger']; ?>
                                <tr>
                                    <td><?= formatDate($p['paid_at']) ?></td>
                                    <td><?= h($p['full_name']) ?></td>
                                    <td><?= h(formatCurrency($p['amount'])) ?></td>
                                    <td><?= h($p['payment_method']) ?></td>
                                    <td><code><?= h($p['receipt_number']) ?></code></td>
                                    <td><span class="badge <?= $sc[$p['status']] ?>"><?= h($p['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (!$recentPayments): ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <h5>No payments yet</h5>
                            <p>Payments recorded for your stage will appear here.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div></div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
</body>
</html>
