<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['rider']);
$active = 'payment-history';
$riderId = $_SESSION['rider_id'];
if (!$riderId) redirect('/login.php');

$rider = fetchOne("SELECT * FROM riders WHERE id = ?", [$riderId]);
$payments = fetchAll("SELECT * FROM payments WHERE rider_id = ? ORDER BY paid_at DESC", [$riderId]);
$totalPaid = array_sum(array_column(array_filter($payments, fn($p) => $p['status']==='Confirmed'), 'amount'));
$currentYearAmt = 0;
foreach ($payments as $p) { if ($p['fiscal_year'] === CURRENT_FISCAL_YEAR && $p['status']==='Confirmed') $currentYearAmt += $p['amount']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Payment History - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-rider.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Payment History'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area">
        <div class="row g-3 mb-4">
            <div class="col-md-4 col-6"><div class="card"><div class="card-body text-center"><h4 class="fw-bold"><?= h(formatCurrency($currentYearAmt)) ?></h4><small class="text-muted">Current Year Tax</small></div></div></div>
            <div class="col-md-4 col-6"><div class="card"><div class="card-body text-center"><h4 class="fw-bold"><?= count($payments) ?></h4><small class="text-muted">Total Payments</small></div></div></div>
            <div class="col-md-4 col-6"><div class="card"><div class="card-body text-center"><h4 class="fw-bold"><?= h(formatCurrency($totalPaid)) ?></h4><small class="text-muted">Total Paid</small></div></div></div>
        </div>

        <div class="d-flex justify-content-end mb-2 no-print">
            <?php $exportTableId='myPaymentsTable'; $exportFilename='my_payment_history'; $exportTitle='My Payment History'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
        </div>
        <div class="card"><div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="myPaymentsTable">
                    <thead><tr><th>Year</th><th>Amount</th><th>Date Paid</th><th>Receipt #</th><th>Method</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$payments): ?><tr><td colspan="6" class="text-center py-4 text-muted">No payment history yet</td></tr><?php endif; ?>
                    <?php foreach ($payments as $p): $sc = ['Confirmed'=>'bg-success','Pending'=>'bg-warning text-dark','Failed'=>'bg-danger']; ?>
                        <tr>
                            <td><?= h($p['fiscal_year']) ?></td>
                            <td><?= h(formatCurrency($p['amount'])) ?></td>
                            <td><?= formatDate($p['paid_at']) ?></td>
                            <td><code><?= h($p['receipt_number']) ?></code></td>
                            <td><?= h($p['payment_method']) ?></td>
                            <td><span class="badge <?= $sc[$p['status']] ?>"><?= h($p['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
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
