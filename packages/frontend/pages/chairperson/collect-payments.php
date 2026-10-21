<?php
require_once __DIR__ . '/../../../packages/backend/auth.php';
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
    <?php $pageTitle = get_setting('chairperson_payment_initiated') . ' - ' . get_setting('site_name') ?? 'Collect Payment - BodaERP'; require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Collect Payments'; $pageSubtitle = 'Record and track rider tax payments for your stage'; require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <?php if ($receiptMsg): ?><div class="alert alert-success">Payment recorded! Receipt: <strong><?= h($receiptMsg) ?></strong></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e) . '<br>'; ?></div><?php endif; ?>

        <div class="row g-3 row-cols-2 row-cols-xl-4 mb-4">
            <div class="col"><div class="stat-hero-card grad-blue"><div class="stat-hero-icon"><i class="fas fa-money-bill-wave"></i></div><div><div class="stat-hero-value"><?= h(formatCurrency($revenue)) ?></div><div class="stat-hero-label">Total Collected</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-green"><div class="stat-hero-icon"><i class="fas fa-user-check"></i></div><div><div class="stat-hero-value"><?= $activeRiders ?></div><div class="stat-hero-label">Active Riders</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-red"><div class="stat-hero-icon"><i class="fas fa-exclamation-triangle"></i></div><div><div class="stat-hero-value"><?= $defaulters ?></div><div class="stat-hero-label">Defaulters</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-gold"><div class="stat-hero-icon"><i class="fas fa-calendar-alt"></i></div><div><div class="stat-hero-value"><?= h(formatCurrency($thisMonth)) ?></div><div class="stat-hero-label">This Month</div></div></div></div>
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
