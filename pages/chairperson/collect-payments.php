<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['chairperson']);
$active = 'collect-payments';
$stageId = $_SESSION['stage_id'];
$cityId = $_SESSION['city_id'];
$city = fetchOne("SELECT * FROM cities WHERE id = ?", [$cityId]);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $riderId = (int) ($_POST['rider_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $method = $_POST['method'] ?? 'Cash';
    $rider = fetchOne("SELECT * FROM riders WHERE id = ? AND stage_id = ?", [$riderId, $stageId]);

    if (!$rider) $errors[] = 'Please select a valid rider.';
    if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';

    if (!$errors) {
        runQuery("INSERT INTO payments (rider_id,city_id,amount,payment_method,receipt_number,status,fiscal_year,paid_at,collected_by)
                  VALUES (?,?,?,?,?,'Confirmed',?,NOW(),?)",
            [$riderId,$cityId,$amount,$method,'TMP-'.uniqid(),CURRENT_FISCAL_YEAR,$_SESSION['user_id']]);
        $paymentId = (int) db()->insert_id;
        $receipt = nextReceiptNumber($paymentId);
        runQuery("UPDATE payments SET receipt_number = ? WHERE id = ?", [$receipt, $paymentId]);

        runQuery("UPDATE riders SET status='active', expiry_date = DATE_ADD(CURDATE(), INTERVAL 365 DAY) WHERE id = ?", [$riderId]);
        if ($rider['user_id']) {
            runQuery("INSERT INTO notifications (rider_id,type,message) VALUES (?, 'Payment Confirmed', ?)",
                [$riderId, 'Your annual tax payment has been confirmed. Thank you!']);
        }
        audit_log('CREATE', 'payment', $receipt, "Collected {$amount} from {$rider['full_name']}");
        redirect('/pages/chairperson/collect-payments.php?receipt=' . urlencode($receipt));
    }
}

$stageRiders = fetchAll("SELECT id, full_name, bike_plate FROM riders WHERE stage_id = ? AND deleted_at IS NULL ORDER BY full_name", [$stageId]);
$revenue = (float) fetchValue("SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.status='Confirmed' AND p.rider_id IN (SELECT id FROM riders WHERE stage_id=?)", [$stageId]);
$activeRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status='active' AND deleted_at IS NULL", [$stageId]);
$defaulters = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status='expired' AND deleted_at IS NULL", [$stageId]);
$thisMonth = (float) fetchValue("SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.status='Confirmed' AND MONTH(p.paid_at)=MONTH(CURDATE()) AND p.rider_id IN (SELECT id FROM riders WHERE stage_id=?)", [$stageId]);

$recentPayments = fetchAll("SELECT p.*, r.full_name FROM payments p JOIN riders r ON r.id = p.rider_id
    WHERE r.stage_id = ? ORDER BY p.paid_at DESC LIMIT 15", [$stageId]);

$receiptMsg = $_GET['receipt'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Collect Payments - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Collect Payments'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area">
        <?php if ($receiptMsg): ?><div class="alert alert-success">Payment recorded! Receipt: <strong><?= h($receiptMsg) ?></strong></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e) . '<br>'; ?></div><?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="card"><div class="card-body"><span class="fs-4 fw-bold"><?= h(formatCurrency($revenue)) ?></span><div class="text-muted small">Total Collected</div></div></div></div>
            <div class="col-md-3 col-6"><div class="card"><div class="card-body"><span class="fs-4 fw-bold text-success"><?= $activeRiders ?></span><div class="text-muted small">Active Riders</div></div></div></div>
            <div class="col-md-3 col-6"><div class="card"><div class="card-body"><span class="fs-4 fw-bold text-danger"><?= $defaulters ?></span><div class="text-muted small">Defaulters</div></div></div></div>
            <div class="col-md-3 col-6"><div class="card"><div class="card-body"><span class="fs-4 fw-bold"><?= h(formatCurrency($thisMonth)) ?></span><div class="text-muted small">This Month</div></div></div></div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card"><div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="fas fa-money-bill-wave text-primary me-2"></i>Record Payment</h6>
                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="mb-3"><label class="form-label fw-semibold">Select Rider <span class="text-danger">*</span></label>
                            <select class="form-select" name="rider_id" required>
                                <option value="">Choose rider...</option>
                                <?php foreach ($stageRiders as $r): ?><option value="<?= (int)$r['id'] ?>"><?= h($r['full_name']) ?> (<?= h($r['bike_plate']) ?>)</option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text">UGX</span><input type="number" class="form-control" name="amount" value="<?= (int)$city['annual_fee'] ?>" required></div>
                        </div>
                        <div class="mb-3"><label class="form-label fw-semibold">Payment Method</label>
                            <select class="form-select" name="method"><option value="Mobile Money">Mobile Money</option><option value="Cash">Cash</option><option value="Bank Transfer">Bank Transfer</option></select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-check me-1"></i>Record Payment</button>
                    </form>
                </div></div>
            </div>
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-2 no-print">
                    <h6 class="fw-bold mb-0">Recent Payments</h6>
                    <?php $exportTableId='recentPaymentsTable'; $exportFilename='stage_payments'; $exportTitle='Stage Payment History'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
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
                    </div>
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
